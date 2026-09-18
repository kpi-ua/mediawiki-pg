<?php
/**
 * Environment-driven MediaWiki configuration.
 *
 * Shipped at /etc/mediawiki/LocalSettings.php and selected through the
 * MW_CONFIG_FILE environment variable by docker-entrypoint.sh, which only does
 * so when no LocalSettings.php exists in the document root. A configuration
 * file mounted or baked at /var/www/html/LocalSettings.php therefore keeps
 * working exactly as before, and with no database variables set the web
 * installer still comes up.
 *
 * It exists for managed runtimes — ECS/Fargate, EKS, App Runner — where the
 * container filesystem is immutable and secrets arrive as environment
 * variables (Secrets Manager or SSM Parameter Store via the task definition
 * "secrets" block), because there is no way to mount a single configuration
 * file from S3 or from a secret store.
 *
 * Everything wiki-specific — extensions, permissions, skins, branding — goes
 * into *.php files under MW_SETTINGS_DIR (/etc/mediawiki/settings.d), which are
 * included at the end of this file.
 *
 * See README.md for the full list of variables.
 */

# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

/**
 * Read a setting from the environment.
 *
 * Passing no default makes the variable mandatory: a missing value raises an
 * error instead of silently starting a half-configured wiki. $aliases lets a
 * setting keep an older variable name.
 */
function mwEnv( string $name, ?string $default = null, array $aliases = [] ): string {
	foreach ( array_merge( [ $name ], $aliases ) as $candidate ) {
		$value = getenv( $candidate );
		if ( $value !== false && $value !== '' ) {
			return $value;
		}
	}
	if ( $default === null ) {
		throw new RuntimeException( "mediawiki-pg: required environment variable $name is not set" );
	}
	return $default;
}

function mwEnvBool( string $name, bool $default = false ): bool {
	return filter_var( mwEnv( $name, $default ? 'true' : 'false' ), FILTER_VALIDATE_BOOLEAN );
}

/** Split a comma-separated variable into a trimmed list. */
function mwEnvList( string $name, string $default = '' ): array {
	$value = mwEnv( $name, $default );
	if ( $value === '' ) {
		return [];
	}
	return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
}

## Diagnostics.
## Exception pages print configuration values, so this stays off by default.
$wgShowExceptionDetails = mwEnvBool( 'MW_SHOW_EXCEPTION_DETAILS', false );
if ( mwEnvBool( 'MW_LOG_TO_STDERR', true ) ) {
	$wgDBerrorLog = 'php://stderr';
}

## Identity
$wgSitename = mwEnv( 'MW_SITENAME', 'MediaWiki' );
$wgMetaNamespace = mwEnv( 'MW_META_NAMESPACE', '' );

## Public URL, including the scheme. Behind a load balancer that terminates
## TLS this must be the https:// address, otherwise MediaWiki emits http links
## and non-secure cookies.
$wgServer = mwEnv( 'MW_SERVER', null, [ 'MW_URL' ] );
$wgScriptPath = mwEnv( 'MW_SCRIPT_PATH', '' );
$wgResourceBasePath = $wgScriptPath;
$wgArticlePath = mwEnv( 'MW_ARTICLE_PATH', $wgScriptPath . '/index.php/$1' );

$logo = mwEnv( 'MW_LOGO', '' );
if ( $logo !== '' ) {
	$wgLogos = [ '1x' => $logo, 'icon' => mwEnv( 'MW_LOGO_ICON', $logo ) ];
}
$favicon = mwEnv( 'MW_FAVICON', '' );
if ( $favicon !== '' ) {
	$wgFavicon = $favicon;
}

## Proxies allowed to set X-Forwarded-For, so logs, rate limits and blocks see
## the real client address instead of the load balancer's.
$wgCdnServersNoPurge = mwEnvList( 'MW_TRUSTED_PROXIES' );

## Database. Defaults target RDS for PostgreSQL, which is what this image adds
## the pgsql extensions for.
$wgDBtype     = mwEnv( 'MW_DB_TYPE', 'postgres' );
$wgDBserver   = mwEnv( 'MW_DB_SERVER', null, [ 'MW_DB_HOST' ] );
$wgDBname     = mwEnv( 'MW_DB_NAME' );
$wgDBuser     = mwEnv( 'MW_DB_USER' );
$wgDBpassword = mwEnv( 'MW_DB_PASSWORD' );
$wgDBport     = mwEnv( 'MW_DB_PORT', $wgDBtype === 'postgres' ? '5432' : '3306' );
$wgDBssl      = mwEnvBool( 'MW_DB_SSL', true );
if ( $wgDBtype === 'postgres' ) {
	$wgDBmwschema = mwEnv( 'MW_DB_SCHEMA', 'mediawiki' );
}

## Cache and sessions.
## More than one task or pod can serve the same wiki, so the session store has
## to be shared: ElastiCache when MW_MEMCACHED_SERVERS is set, the database
## otherwise. CACHE_NONE would tie each user to one container.
$memcachedServers = mwEnvList( 'MW_MEMCACHED_SERVERS' );
if ( $memcachedServers ) {
	$wgMainCacheType = CACHE_MEMCACHED;
	$wgMemCachedServers = $memcachedServers;
} else {
	$wgMainCacheType = CACHE_DB;
	$wgMemCachedServers = [];
}
$wgSessionCacheType = $wgMainCacheType;

## Localisation cache directory. Keep it on container-local storage: it is
## rebuildable, and on a network filesystem it makes every request slow.
$wgCacheDirectory = mwEnv( 'MW_CACHE_DIR', '/tmp/mw-cache' );

## Keys. $wgSecretKey must be the same for every container and must survive
## redeployment — sessions and tokens are derived from it.
$wgSecretKey = mwEnv( 'MW_SECRET_KEY' );
$wgUpgradeKey = mwEnv( 'MW_UPGRADE_KEY', '' );
$wgAuthenticationTokenVersion = mwEnv( 'MW_AUTH_TOKEN_VERSION', '1' );

## Uploads are off by default: the upload directory is container-local, so
## enabling them without shared storage (EFS, or an S3 file backend) loses
## files as soon as a container is replaced.
$wgEnableUploads = mwEnvBool( 'MW_ENABLE_UPLOADS', false );
if ( $wgEnableUploads ) {
	$wgUseImageMagick = true;
	$wgImageMagickConvertCommand = mwEnv( 'MW_IMAGEMAGICK_CONVERT', '/usr/bin/convert' );
	$wgStrictFileExtensions = true;
}

$wgUseInstantCommons = mwEnvBool( 'MW_USE_INSTANT_COMMONS', false );
$wgPingback = mwEnvBool( 'MW_PINGBACK', false );

$wgLanguageCode = mwEnv( 'MW_LANGUAGE_CODE', 'en' );
$wgLocaltimezone = mwEnv( 'MW_TIMEZONE', 'UTC' );

$wgDiff3 = mwEnv( 'MW_DIFF3', '/usr/bin/diff3' );

## Anonymous access
$wgGroupPermissions['*']['read'] = mwEnvBool( 'MW_ANON_READ', true );
$wgGroupPermissions['*']['edit'] = mwEnvBool( 'MW_ANON_EDIT', false );
$wgGroupPermissions['*']['createaccount'] = mwEnvBool( 'MW_ANON_CREATE_ACCOUNT', false );

## Email. SES SMTP is the usual endpoint on AWS; MW_SMTP_HOST carries the
## scheme, e.g. tls://email-smtp.eu-west-1.amazonaws.com.
$smtpHost = mwEnv( 'MW_SMTP_HOST', '' );
$wgEnableEmail = mwEnvBool( 'MW_ENABLE_EMAIL', $smtpHost !== '' );
$wgEnableUserEmail = mwEnvBool( 'MW_ENABLE_USER_EMAIL', $wgEnableEmail );
$wgEmailAuthentication = mwEnvBool( 'MW_EMAIL_AUTHENTICATION', true );
$wgEnotifUserTalk = mwEnvBool( 'MW_ENOTIF_USER_TALK', false );
$wgEnotifWatchlist = mwEnvBool( 'MW_ENOTIF_WATCHLIST', false );
$wgEmergencyContact = mwEnv( 'MW_EMERGENCY_CONTACT', '' );
$wgPasswordSender = mwEnv( 'MW_PASSWORD_SENDER', '' );
if ( $smtpHost !== '' ) {
	$wgSMTP = [
		'host' => $smtpHost,
		'IDHost' => mwEnv( 'MW_SMTP_IDHOST', preg_replace( '#^[a-z]+://#', '', $smtpHost ) ),
		'port' => (int)mwEnv( 'MW_SMTP_PORT', '465' ),
		'auth' => mwEnvBool( 'MW_SMTP_AUTH', true ),
		'username' => mwEnv( 'MW_SMTP_USERNAME', '', [ 'MW_SMTP_USER' ] ),
		'password' => mwEnv( 'MW_SMTP_PASSWORD', '' ),
	];
}

## Skins and extensions, by name, e.g. MW_SKINS=Vector,MonoBook
$wgDefaultSkin = mwEnv( 'MW_DEFAULT_SKIN', 'vector' );
foreach ( mwEnvList( 'MW_SKINS', 'Vector' ) as $skin ) {
	wfLoadSkin( $skin );
}
foreach ( mwEnvList( 'MW_EXTENSIONS' ) as $extension ) {
	wfLoadExtension( $extension );
}

## Wiki-specific configuration. Anything this file does not cover goes into
## *.php files here — they are included in filename order and can override
## every value set above.
$settingsDir = mwEnv( 'MW_SETTINGS_DIR', '/etc/mediawiki/settings.d' );
if ( is_dir( $settingsDir ) ) {
	$extraSettings = glob( rtrim( $settingsDir, '/' ) . '/*.php' );
	sort( $extraSettings );
	foreach ( $extraSettings as $extraSetting ) {
		require_once $extraSetting;
	}
}
