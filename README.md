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
