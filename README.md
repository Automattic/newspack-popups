# newspack-popups

AMP-compatible popup notifications.

## Config file

The special config file for this plugin's API should be created automatically. If it's not, create is as `newspack-popups-config.php` in the WP directory.

Here's a blueprint:

```
<?php
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'root' );
define( 'DB_NAME', 'local' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_PREFIX', 'wp_' );
```

## Segmentation features

The segmentation features rely on visit logging. This is currently opt-in, managed by the `ENABLE_CAMPAIGN_EVENT_LOGGING` flag defined in the aforementioned file:

```
define( 'ENABLE_CAMPAIGN_EVENT_LOGGING', true );
```
