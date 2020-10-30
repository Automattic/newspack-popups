# Lightweight API

The lightweight API is a stripped-down alternative to the WordPress REST API. Campaigns will often be present on every page of a site, which can lead to performance issues as a result of a large number of uncacheable API requests.

The feature requires a custom config file named `newspack-popups-config.php` at the root of the site, which will be created at plugin initialisation.

To include information about database, cache and time in the response, add this constant to that config file:

```
define( 'NEWSPACK_POPUPS_DEBUG', true );
```

## Manually creating the config file

If the config file was not created automatically, you can add it yourself. Here's an example:

```
<?php
define( 'DB_USER', 'wp' );
define( 'DB_PASSWORD', 'wp' );
define( 'DB_NAME', 'local' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_PREFIX', 'wp_' );
```
