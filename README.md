# mediawiki-pg

MediaWiki Docker image with PostgreSQL support.

## Overview

This repository provides a Docker setup for running [MediaWiki](https://www.mediawiki.org/) with [PostgreSQL](https://www.postgresql.org/) as the database backend. The official MediaWiki Docker image only includes MySQL/MariaDB support by default, so this image adds the necessary PostgreSQL PHP extensions (`pgsql` and `pdo_pgsql`).

## Quick Start

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)

### Setup

1. Clone this repository:
   ```bash
   git clone https://github.com/kpi-ua/mediawiki-pg.git
   cd mediawiki-pg
   ```

2. Create environment file:
   ```bash
   cp .env.example .env
   # Edit .env with your preferred database credentials
   ```

3. Start the containers (first run without LocalSettings.php):
   ```bash
   # Remove the LocalSettings.php volume mount temporarily for initial setup
   docker compose up -d db
   docker compose up -d mediawiki
   ```

4. Access MediaWiki at http://localhost:8080 and complete the installation wizard:
   - Select **PostgreSQL** as the database type
   - Use `db` as the database host
   - Enter the database credentials from your `.env` file

5. Download the generated `LocalSettings.php` and place it in the repository root.

6. Restart the containers:
   ```bash
   docker compose down
   docker compose up -d
   ```

## Building the Image

To build the image locally:

```bash
docker build -t mediawiki-pg .
```

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `MEDIAWIKI_DB_NAME` | `mediawiki` | PostgreSQL database name |
| `MEDIAWIKI_DB_USER` | `mediawiki` | PostgreSQL user |
| `MEDIAWIKI_DB_PASSWORD` | `mediawiki_password` | PostgreSQL password |

### Volumes

- `mediawiki_images`: Stores uploaded files
- `db_data`: PostgreSQL data persistence

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.