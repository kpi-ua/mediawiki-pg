# settings.d

Drop `*.php` files here (`/etc/mediawiki/settings.d` in the image, or another
path via `MW_SETTINGS_DIR`) to add wiki-specific configuration on top of the
environment-driven defaults. They are included in filename order at the end of
`LocalSettings.php`, so they can override anything set there.

```dockerfile
FROM kpiua/mediawiki-pg:1.45.4-pg
COPY 10-permissions.php /etc/mediawiki/settings.d/
```

Keep credentials out of these files — they belong in environment variables, so
they can come from Secrets Manager or SSM Parameter Store at runtime.
