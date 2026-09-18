# mediawiki-pg

MediaWiki Docker image with PostgreSQL support.

## Overview

This repository provides a Docker image for running [MediaWiki](https://www.mediawiki.org/) with [PostgreSQL](https://www.postgresql.org/) as the database backend. The official MediaWiki Docker image only includes MySQL/MariaDB support by default, so this image adds the necessary PostgreSQL PHP extensions (`pgsql` and `pdo_pgsql`).

## Usage

Pull the image from Docker Hub:

```bash
docker pull kpiua/mediawiki:1.44.2-pg
```

Or use it in your own Docker setup by referencing `kpiua/mediawiki:1.44.2-pg` as the base image.

## Building the Image

To build the image locally:

```bash
docker build -t kpiua/mediawiki:1.44.2-pg .
```

## Running the Image

To run the image (requires a PostgreSQL database):

```bash
docker run -d \
  --name mediawiki \
  -p 8080:80 \
  -e MEDIAWIKI_DB_TYPE=postgres \
  -e MEDIAWIKI_DB_HOST=your-postgres-host \
  -e MEDIAWIKI_DB_NAME=mediawiki \
  -e MEDIAWIKI_DB_USER=mediawiki \
  -e MEDIAWIKI_DB_PASSWORD=your-password \
  kpiua/mediawiki:1.44.2-pg
```

Access MediaWiki at http://localhost:8080

## Running on AWS (and other managed runtimes)

On ECS, EKS or App Runner the `LocalSettings.php` bind mount used with Docker
Compose is not available: there is no S3 volume type, the ECS task definition
`secrets` block injects environment variables rather than files, and a mount
target is always a directory, so nothing can be mounted over
`/var/www/html/LocalSettings.php`. Without that file MediaWiki only shows the
installer — "LocalSettings.php not found."

The image therefore ships an environment-driven configuration at
`/etc/mediawiki/LocalSettings.php`. The entrypoint selects it through
`MW_CONFIG_FILE`, and only when the document root has no `LocalSettings.php` of
its own, so existing deployments that mount or bake in their own configuration
are unaffected, and with no database variables set the web installer still comes
up as before.

```bash
docker run -d -p 8080:80 \
  -e MW_SERVER=https://wiki.example.org \
  -e MW_SITENAME='Example Wiki' \
  -e MW_DB_SERVER=postgres-db.eu-west-1.rds.amazonaws.com \
  -e MW_DB_NAME=wikidb \
  -e MW_DB_USER=wikiadmin \
  -e MW_DB_PASSWORD=... \
  -e MW_SECRET_KEY=... \
  kpiua/mediawiki-pg:1.44.2-pg
```

`examples/ecs-task-definition.json` is the same thing as a Fargate task
definition, with passwords coming from Secrets Manager.

### Configuration variables

Values without a default are mandatory; a missing one fails at startup with a
named error instead of starting a half-configured wiki.

| Variable | Default | Purpose |
|----------|---------|---------|
| `MW_SERVER` (`MW_URL`) | — | Public URL including the scheme; must be `https://...` behind a load balancer that terminates TLS |
| `MW_SCRIPT_PATH` | `` | URL path to the wiki |
| `MW_ARTICLE_PATH` | `$MW_SCRIPT_PATH/index.php/$1` | Article path |
| `MW_SITENAME` | `MediaWiki` | Wiki name |
| `MW_META_NAMESPACE` | `` | Project namespace |
| `MW_DB_SERVER` (`MW_DB_HOST`) | — | Database endpoint |
| `MW_DB_NAME` / `MW_DB_USER` / `MW_DB_PASSWORD` | — | Database credentials |
| `MW_DB_TYPE` | `postgres` | `postgres` or `mysql` |
| `MW_DB_PORT` | `5432` / `3306` | Depends on `MW_DB_TYPE` |
| `MW_DB_SCHEMA` | `mediawiki` | PostgreSQL schema |
| `MW_DB_SSL` | `true` | TLS to the database, as RDS expects |
| `MW_SECRET_KEY` | — | Must be identical across containers and stable across deployments; changing it invalidates every session |
| `MW_UPGRADE_KEY` | `` | Web updater key |
| `MW_AUTH_TOKEN_VERSION` | `1` | Bumping it logs everyone out |
| `MW_MEMCACHED_SERVERS` | unset | Comma-separated ElastiCache endpoints; unset keeps cache and sessions in the database |
| `MW_CACHE_DIR` | `/tmp/mw-cache` | Localisation cache; keep it container-local, never on a network filesystem |
| `MW_TRUSTED_PROXIES` | unset | CIDRs allowed to set `X-Forwarded-For`, e.g. the load balancer subnets |
| `MW_LANGUAGE_CODE` | `en` | Site language |
| `MW_TIMEZONE` | `UTC` | Site time zone |
| `MW_DEFAULT_SKIN` | `vector` | Default skin |
| `MW_SKINS` | `Vector` | Comma-separated skins to load |
| `MW_EXTENSIONS` | unset | Comma-separated extensions to load |
| `MW_LOGO` / `MW_LOGO_ICON` / `MW_FAVICON` | unset | Branding URLs |
| `MW_ANON_READ` / `MW_ANON_EDIT` / `MW_ANON_CREATE_ACCOUNT` | `true` / `false` / `false` | Anonymous permissions |
| `MW_ENABLE_UPLOADS` | `false` | Needs shared storage for `images/`; container-local uploads are lost when a container is replaced |
| `MW_ENABLE_EMAIL` / `MW_ENABLE_USER_EMAIL` | set when `MW_SMTP_HOST` is | Email features |
| `MW_SMTP_HOST` | unset | Includes the scheme, e.g. `tls://email-smtp.eu-west-1.amazonaws.com` |
| `MW_SMTP_IDHOST` | host without the scheme | SMTP IDHost |
| `MW_SMTP_PORT` | `465` | SMTP port |
| `MW_SMTP_USERNAME` (`MW_SMTP_USER`) / `MW_SMTP_PASSWORD` | `` | SES SMTP credentials |
| `MW_SMTP_AUTH` | `true` | SMTP authentication |
| `MW_PASSWORD_SENDER` / `MW_EMERGENCY_CONTACT` | `` | Sender and contact addresses |
| `MW_SHOW_EXCEPTION_DETAILS` | `false` | `true` only while debugging: exception pages print configuration |
| `MW_LOG_TO_STDERR` | `true` | Database errors to stderr, for CloudWatch Logs |
| `MW_SETTINGS_DIR` | `/etc/mediawiki/settings.d` | Directory of extra `*.php` configuration |
| `MW_CONFIG_FILE` | chosen by the entrypoint | Set it explicitly to override that choice |

### Wiki-specific configuration

Anything the variables do not cover goes into `*.php` files under
`/etc/mediawiki/settings.d`, included in filename order at the end of
`LocalSettings.php` and able to override every value it sets:

```dockerfile
FROM kpiua/mediawiki-pg:1.44.2-pg
COPY 10-permissions.php /etc/mediawiki/settings.d/
```

### Health checks

`/healthz.php` always answers `200`. Use it for load balancer target groups and
container health checks: a wiki with `MW_ANON_READ=false` answers `403` on `/`,
which would mark every container unhealthy. It checks Apache and PHP only, not
the database, so a database blip does not recycle every container at once.

### Sessions across several containers

With no `MW_MEMCACHED_SERVERS` the object cache and sessions live in the
database, so any container can serve any request. That is why the bundled
configuration never uses `CACHE_NONE`, which would tie each user to one
container.

### Maintenance scripts

The entrypoint exports `MW_CONFIG_FILE` for the web process; a shell started
separately does not inherit it, so pass it explicitly:

```bash
docker exec -e MW_CONFIG_FILE=/etc/mediawiki/LocalSettings.php mediawiki \
  php maintenance/run.php update --quick
```

## Features

- Based on the official MediaWiki Docker image
- Includes PostgreSQL PHP extensions (`pgsql` and `pdo_pgsql`)
- Compatible with PostgreSQL databases
- Environment-driven configuration for AWS and other managed runtimes, with the
  web installer and mounted `LocalSettings.php` still working as before
- `/healthz.php` endpoint for load balancer and container health checks
- Supports all standard MediaWiki configuration options

## License

This project is licensed under the Mozilla Public License 2.0 - see the [LICENSE](LICENSE) file for details.