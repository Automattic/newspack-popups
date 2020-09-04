# Lightweight API

The lightweight API is a stripped-down alternative to the WordPress REST API. Campaigns will often be present on every page of a site, which can lead to performance issues as a result of a large number of uncacheable API requests. The lightweight API will be used in two scenarios:

## DB Connection Environment Variables
If `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables are set, the lightweight API is used and database connection is made using these credentials (DB_HOST is assumed to be `localhost`).

## Special Config File
If the environment variable approach isn't possible. include a config file named `newspack-popups-config.php` at the root of the WordPress installation. The file must contain these four constants which should be identical to the values in `wp-config.php`: `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`. Example of the complete file:

```
<?php

define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wordpress_db_user' );
define( 'DB_PASSWORD', 'password123' );
define( 'DB_HOST', 'localhost' );
```
