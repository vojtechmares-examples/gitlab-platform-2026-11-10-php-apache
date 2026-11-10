#!/usr/bin/env bash
# shellcheck disable=SC2029  # the variables are meant to expand on this side, into the remote command
# NOT a zero-downtime deployment, on purpose:
#   1. stop Apache            -> Caddy cannot reach it and shows the static maintenance page (503)
#   2. rsync the new release  -> releases/<pipeline id>/
#   3. switch "current"       -> atomic symlink rename
#   4. start Apache           -> the new version
# Usage:  deploy/release.sh staging|production
set -euo pipefail

ENVIRONMENT="${1:?usage: release.sh staging|production}"
APP="${APP:-php-apache}"
BUILD_ID="${CI_PIPELINE_ID:?CI_PIPELINE_ID is not set (run this from a pipeline)}"
KEEP="${KEEP_RELEASES:-5}"

BASE="/srv/${APP}/${ENVIRONMENT}"
RELEASE="${BASE}/releases/${BUILD_ID}"
UNIT="${APP}-${ENVIRONMENT}"
URL="https://${UNIT}.${EXAMPLES_DOMAIN:?EXAMPLES_DOMAIN is not set}"

# However this script ends early, do not leave the site on the maintenance page: start the unit again (the symlink
# still points at the previous release unless the switch itself succeeded).
finished=false
restart_on_failure() {
  if [ "${finished}" != "true" ]; then
    echo "ERROR: deployment did not finish, starting ${UNIT} again" >&2
    ssh deploy "sudo systemctl start '${UNIT}'" || true
  fi
}
trap restart_on_failure EXIT

echo "==> stop ${UNIT}: visitors now get the maintenance page from Caddy"
ssh deploy "sudo systemctl stop '${UNIT}'"
code="$(curl -s -o /tmp/maintenance.html -w '%{http_code}' "${URL}/")"
if [ "${code}" != "503" ] || ! grep -q 'deploying a new version' /tmp/maintenance.html; then
  echo "ERROR: expected the maintenance page (503) while Apache is stopped, got HTTP ${code}" >&2
  exit 1
fi
echo "    ${URL}/ answers 503 with the maintenance page"

echo "==> upload the release to ${RELEASE}"
ssh deploy "mkdir -p '${RELEASE}'"
# Files from CI artifacts can be world-writable; the server gets 755 directories and 644 files.
rsync -az --delete --chmod=D755,F644 --info=stats1 -e ssh \
  src public vendor build.json composer.json composer.lock "deploy:${RELEASE}/"

echo "==> switch the symlink (atomic rename) and start ${UNIT}"
ssh deploy "set -e; ln -sfn '${RELEASE}' '${BASE}/current.new'; mv -T '${BASE}/current.new' '${BASE}/current'; sudo systemctl start '${UNIT}'"

echo "==> wait for ${URL}"
curl -fsS -o /dev/null --retry 30 --retry-delay 2 --retry-all-errors "${URL}/health"
curl -fsS "${URL}/info" | tee /dev/stderr | grep -q "\"build\":\"${BUILD_ID}\"" \
  || { echo; echo "ERROR: ${URL} is up but does not run build ${BUILD_ID}" >&2; exit 1; }
echo
finished=true

echo "==> keep the last ${KEEP} releases"
ssh deploy "cd '${BASE}/releases' && current=\$(readlink -f '${BASE}/current') && ls -1dt */ | tail -n +$((KEEP + 1)) | while read -r d; do [ \"\$(readlink -f \"\$d\")\" = \"\$current\" ] || rm -rf -- \"\$d\"; done; ls -1 ."
