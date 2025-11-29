# MediaWiki Docker image with PostgreSQL support
# Based on official MediaWiki image with added PostgreSQL PHP extensions

FROM mediawiki:1.43

# Install PostgreSQL PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pgsql pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
