# MediaWiki Docker image with PostgreSQL support
# Based on official MediaWiki image with added PostgreSQL PHP extensions

FROM mediawiki:1.44.2

# Install PostgreSQL PHP extensions. curl comes along for container health
# checks against healthz.php and is a no-op when the base image already has it.
RUN apt-get update && apt-get install -y \
    libpq-dev \
    curl \
    && docker-php-ext-install pgsql pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Environment-driven configuration for managed runtimes (ECS, EKS, App Runner),
# where the filesystem is immutable and secrets arrive as environment variables.
# It lives outside the document root and is selected by the entrypoint only when
# no LocalSettings.php is mounted or baked in, so existing setups are unaffected.
COPY config/LocalSettings.php /etc/mediawiki/LocalSettings.php
COPY config/settings.d/ /etc/mediawiki/settings.d/
COPY config/healthz.php /var/www/html/healthz.php
COPY docker-entrypoint.sh /usr/local/bin/mediawiki-pg-entrypoint

RUN chmod 0444 /etc/mediawiki/LocalSettings.php \
    && chmod 0755 /usr/local/bin/mediawiki-pg-entrypoint

ENTRYPOINT ["mediawiki-pg-entrypoint"]
CMD ["apache2-foreground"]
