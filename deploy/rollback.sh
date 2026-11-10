#!/usr/bin/env bash
# shellcheck disable=SC2029  # the variables are meant to expand on this side, into the remote command
# Go back to the release before the current one.  deploy/rollback.sh staging|production
set -euo pipefail

ENVIRONMENT="${1:?usage: rollback.sh staging|production}"
APP="${APP:-php-apache}"
BASE="/srv/${APP}/${ENVIRONMENT}"
UNIT="${APP}-${ENVIRONMENT}"
URL="https://${UNIT}.${EXAMPLES_DOMAIN:?EXAMPLES_DOMAIN is not set}"

previous="$(ssh deploy "cd '${BASE}/releases' && current=\$(basename \"\$(readlink -f '${BASE}/current')\") && ls -1t | grep -v -x \"\$current\" | head -n 1")"
[ -n "${previous}" ] || { echo "no earlier release to go back to" >&2; exit 1; }

echo "==> roll back ${UNIT} to release ${previous}"
ssh deploy "set -e; sudo systemctl stop '${UNIT}'; ln -sfn '${BASE}/releases/${previous}' '${BASE}/current.new'; mv -T '${BASE}/current.new' '${BASE}/current'; sudo systemctl start '${UNIT}'"
curl -fsS -o /dev/null --retry 30 --retry-delay 2 --retry-all-errors "${URL}/health"
curl -fsS "${URL}/info"
echo
