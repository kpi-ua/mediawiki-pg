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

## Features

- Based on the official MediaWiki Docker image
- Includes PostgreSQL PHP extensions (`pgsql` and `pdo_pgsql`)
- Compatible with PostgreSQL databases
- Supports all standard MediaWiki configuration options

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.