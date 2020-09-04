# Lightweight API

The lightweight API is a stripped-down alternative to the WordPress REST API. Campaigns will often be present on every page of a site, which can lead to performance issues as a result of a large number of uncacheable API requests.

If `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables are set, the lightweight API is used and database connection is made using these credentials (DB_HOST is assumed to be `localhost`). If not, `wp-config.php` will be parse to extract the DB connection constants.

For the moment, the feature is only enabled if this constant is set in `wp-config.php`:

```
define( 'NEWSPACK_POPUPS_EXPERIMENTAL_MODE', true );
```
