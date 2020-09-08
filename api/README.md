# Lightweight API

The lightweight API is a stripped-down alternative to the WordPress REST API. Campaigns will often be present on every page of a site, which can lead to performance issues as a result of a large number of uncacheable API requests.

The feature requires a custom config file named `newspack-popups-config.php` at the root of the site, with the following constants:

```
define( 'DB_NAME', 'dbname' );
define( 'DB_USER', 'dbuser' );
define( 'DB_PASSWORD', 'dbpass' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_PREFIX', 'wp_' );
```

Copy the WP_CACHE_KEY_SALT define from `wp-content.php`.

To include information about database, cache and time in the response, add this constant:

```
define( 'NEWSPACK_POPUPS_DEBUG', true );
```

