# php-apache

PHP 8.5 application served by **Apache** (mod_php), with Composer dependencies installed in the pipeline.

| Topic | How |
|-------|-----|
| Build | Rocky Linux 10 job image, PHP 8.5 from the Remi module stream, Composer from EPEL |
| Tests | PHPUnit in the `test` job (JUnit report in the pipeline) |
| Dependencies | `composer install --no-dev --optimize-autoloader` in the `build` job, shipped as an artifact |
| Upload | `rsync` over SSH into a directory **per deployment**: `/srv/php-apache/<env>/releases/<pipeline id>/` |
| Switching versions | the symlink `/srv/php-apache/<env>/current` is replaced atomically; Apache's `DocumentRoot` is `current/public` |
| **Not zero-downtime** | `deploy/release.sh` stops Apache, uploads, switches the symlink and starts Apache. In between Caddy answers **503 with a static maintenance page** (`/srv/caddy/maintenance`, configured by the Ansible project); the job checks that it really does |
| Run | one Apache per environment: `php-apache-<env>.service` (user `svc-php`), listening on 127.0.0.1 |
| HTTPS | Caddy: https://php-apache-staging.examples.maresdemo.com, https://php-apache-production.examples.maresdemo.com |
| Environments | `staging` (automatic on `main`), `production` (manual job; protected environment) |
| Rollback | manual `rollback:<env>` job; the last 5 releases are kept |

## CI configuration

The deploy jobs read the CI/CD variables below (*Settings > CI/CD > Variables*). Variables that start with `CI_` are predefined by GitLab. This project needs none of its own (`KEEP_RELEASES` is optional) and has no database.

| Variable | Set on | Type | Used for |
|----------|--------|------|----------|
| `DEPLOY_HOST` | group `examples` | Variable | Address of the deploy server |
| `DEPLOY_SSH_PORT` | group `examples` | Variable | SSH port of the deploy server (`6022`) |
| `DEPLOY_USER` | group `examples` | Variable | Account the pipelines log in as (`deploy`) |
| `SSH_KNOWN_HOSTS` | group `examples` | Variable | Host keys of the deploy server; the connection uses `StrictHostKeyChecking yes` |
| `SSH_PRIVATE_KEY` | group `examples` | File, **protected** | Private key of the `deploy` account |
| `EXAMPLES_DOMAIN` | group `examples` | Variable | Parent domain of the applications (`examples.maresdemo.com`); `deploy/release.sh` checks the maintenance page and waits for `https://php-apache-<env>.$EXAMPLES_DOMAIN` |
| `KEEP_RELEASES` | optional, this project | Variable | How many release directories `deploy/release.sh` keeps on the server (default `5`) |

The group variables are inherited by every project in the `examples` group, so nothing has to be set for them here. The `.ssh-setup` snippet of `examples/ci-templates` turns the first five into `ssh deploy`.
A **protected** variable is passed only to pipelines on protected branches and tags (`main`), which is where the deploy jobs run.
