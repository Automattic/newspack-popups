# Newspack Campaigns (newspack-popups): Agent Instructions

This file covers what is specific to `newspack-popups`. Shared conventions (Docker commands, `n` script, coding standards, git rules, etc.) are in the root `newspack-workspace/AGENTS.md`.

## Quick Reference (Code Pointers)

**Commands:** All lint/build/test scripts are in `package.json`. Run PHP lint separately: `npm run lint:php`.

**PHP backend at a glance:**
- Constants & CPT registration → top of `includes/class-newspack-popups.php`
- Placement types → `$overlay_placements` / `$inline_placements` in `includes/class-newspack-popups-model.php`
- Post meta fields → `register_meta()` in `includes/class-newspack-popups.php` (lines 183–622)
- REST routes → `register_api_endpoints()` in `includes/class-newspack-popups-api.php`. **Note:** the public `/custom-placement` endpoint lives in `Newspack_Popups_Custom_Placements::rest_api_init()` with `'permission_callback' => '__return_true'` (no auth).
- WP-CLI commands → `includes/cli/` + `register_cli_commands()` in the main class
- Logging → `Newspack_Popups_Logger::log()`, delegates to `\Newspack\Logger` or `error_log()`; gated by `NEWSPACK_LOG_LEVEL`

**Frontend at a glance:**
- Webpack entry points (7 entries) → `webpack.config.js`
- Editor sidebar panels → `registerPlugin` calls in `src/editor/index.js`
- Blocks (2) → `src/blocks/` (Custom Placement, Single Prompt), both server-rendered via `view.php`
- Data attributes on `.newspack-popup-container` → template in `includes/class-newspack-popups-model.php` + `src/view/utils/segments.js`. **Note:** `data-frequency` is CSV-encoded as `start,between,max,reset_period`.

**Testing:**
- PHP tests in `tests/`, extend `WP_UnitTestCase` or [`WP_UnitTestCase_PageWithPopups`](https://github.com/Automattic/newspack-popups/blob/trunk/tests/wp-unittestcase-pagewithpopups.php#L11). Run with `n test-php`.
- JS tests colocated with source (`.test.js` suffix). Run with `npm run test`.

## Common Gotchas

- `npm run lint` runs JS + SCSS only. PHP linting requires a separate `npm run lint:php`.
- After adding a new PHP file, run `composer dump-autoload` to update the classmap (Composer uses `classmap`, not PSR-4).
- The Inserter removes its `the_content` filter during Homepage Posts block rendering (via `newspack_blocks_homepage_posts_before_render` / `newspack_blocks_homepage_posts_after_render` hooks) to prevent popups from appearing inside post excerpts.
- Two different capability checks exist: REST API endpoints use `manage_options` directly ([`Newspack_Popups_API::permission_callback()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-api.php#L188)), while the general admin check ([`Newspack_Popups::is_user_admin()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups.php#L962)) defaults to `edit_others_pages` and is filterable via the `newspack_popups_admin_user_capability` filter.
- Segmentation features require the main Newspack plugin (`\Newspack\Reader_Data` class). Without it, [`Newspack_Popups::$segmentation_enabled`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups.php#L61) is `false`.
- The standalone Settings page (Campaigns > Settings) is only used when the main Newspack plugin UI is not available.
- Shortcode `[newspack-popup id="..." class="..."]` renders a specific prompt inline. Handled by [`Newspack_Popups_Inserter::popup_shortcode()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-inserter.php#L782).

## Architecture Deep Dive

### Class Initialization Patterns

The codebase uses a mix of patterns:

- **Singleton**: [`Newspack_Popups::instance()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups.php#L69), [`Newspack_Popups_Segmentation::instance()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-segmentation.php#L44), [`Newspack_Popups_Custom_Placements::instance()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-custom-placements.php#L32), [`Newspack_Popups_View_As::instance()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-view-as.php#L27).
- **File-level instantiation**: `Newspack_Popups_API` and `Newspack_Popups_Inserter` are instantiated via `new` at the bottom of their respective files (not in the main class constructor). The main constructor only `include_once`s the files.
- **Static `init()`**: [`Newspack_Popups_Settings::init()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-settings.php#L19) (only when `is_admin()`), [`Newspack_Popups_Criteria::init()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-criteria.php#L37), [`Newspack_Popups_Expiry::init()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-expiry.php#L27), [`Newspack_Popups_Data_Api::init()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-data-api.php#L27), [`Newspack_Segments_Model::init()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-segments-model.php#L27), [`Newspack\Campaigns\Merge_Tags::init_hooks()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/merge-tags/class-merge-tags.php#L26).

### Namespace Map

| Namespace | Directory |
|-----------|-----------|
| *(global)* | `includes/` (most classes: `Newspack_Popups`, `Newspack_Popups_Model`, etc.) |
| `Newspack\Campaigns` | `includes/merge-tags/`, `includes/schemas/class-schema.php` |
| `Newspack\Campaigns\CLI` | `includes/cli/` |
| `Newspack\Campaigns\Schemas` | `includes/schemas/` (except `class-schema.php`) |

Most classes in `includes/` use the global namespace with a `Newspack_Popups_` prefix. Newer code under `cli/`, `merge-tags/`, and `schemas/` uses the `Newspack\Campaigns` namespace.

### Content Insertion Algorithm

The Inserter (`class-newspack-popups-inserter.php`) controls how prompts appear in post content:

1. **Content filter**: Hooks `the_content` at priority 1. The [`$the_content_has_rendered`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-inserter.php#L39) flag prevents duplicate insertion on subsequent calls.
2. **Block parsing**: Parses post content into blocks via `parse_blocks()`, converts classic blocks to structured blocks, and filters empty blocks.
3. **Inline insertion**: For each inline prompt, inserts at block boundaries based on `trigger_blocks_count` (number of blocks before the prompt). Skips blocks that shouldn't be followed by prompts (headings, floated images — see [`can_block_be_followed_by_prompt()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-inserter.php#L158)).
4. **Archive insertion**: Uses `archive_insertion_posts_count` to insert between posts, with an optional `archive_insertion_is_repeating` flag.
5. **Above-header insertion**: Hooks `wp_body_open` for `above_header` placement.
6. **Shortcode**: `[newspack-popup id="..." class="..."]` via [`popup_shortcode()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-inserter.php#L782) for manual placement.
7. **Homepage Posts block**: Removes and restores the `the_content` filter around Homepage Posts block rendering to prevent prompts inside excerpts.

### Frontend Segmentation Flow

The `view` entry point (`src/view/index.js`) orchestrates the client-side prompt display:

1. Logs a pageview via Reader Data (`window.newspackRAS`).
2. If prompts are not disabled, collects all `.newspack-popup-container` elements.
3. `segmentation.js` determines the reader's best-priority matching segment using localized segment config.
4. Each prompt is checked against its assigned segments and frequency settings.
5. Matching prompts are unhidden by removing the `.hidden` class. Overlay prompts use `IntersectionObserver` for scroll triggers and delay timers for time triggers.
6. Only one overlay prompt displays per pageview. [`closeOverlay()`](https://github.com/Automattic/newspack-popups/blob/trunk/src/view/utils/prompts.js#L29) handles dismissal.
7. `analytics/` modules track loaded, seen, clicked, and dismissed events via GA4.

### Criteria System

Display criteria determine whether a prompt is shown to a reader based on reader data (articles read, device, donation status, etc.). Criteria are registered in PHP and evaluated client-side against data from the Reader Data Library (`window.newspackRAS`).

#### Registration API

The primary PHP method is [`Newspack_Popups_Criteria::register_criteria( $id, $config )`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-criteria.php#L149) (in `includes/class-newspack-popups-criteria.php`).

**Config keys:**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `name` | string | Derived from ID | Human-readable label |
| `category` | string | `'reader_activity'` | Organizational grouping (see categories below) |
| `description` | string | — | Description shown in the segment editor |
| `help` | string | — | Help text for the input control |
| `matching_function` | string | `'default'` | How the criteria value is compared to the segment config (see matching functions below) |
| `matching_attribute` | string | The criteria ID | Key used to look up the reader's value from the Reader Data Library store |
| `options` | array | — | Array of `[ 'label' => ..., 'value' => ..., 'params' => [...] ]` for predefined choices. `params` provides extra data to the matching function (e.g., viewport width ranges for `devices`) |

**Categories** (organizational, used for grouping in the segment editor UI):
- `reader_engagement` — Articles read, favorite categories, devices
- `reader_activity` — User account status
- `reader_revenue` — Donations, subscriptions, memberships
- `newsletter` — Newsletter subscription status and lists
- `referrer_sources` — Traffic source matching/exclusion

**Matching functions** (defined in `src/criteria/matching-functions.js`):

| Function | Behavior |
|----------|----------|
| `default` | Exact match: `criteria.value === config.value` |
| `list__in` | True if the criteria value (or any element if array) appears in the config's comma-separated list |
| `list__not_in` | True if the criteria value is empty or not in the config's list |
| `range` | True if the criteria value falls within `{ min, max }` from the config |

Custom matching functions can also be provided as a JS function (see JS-side registration below).

Default criteria are registered in `src/criteria/default/index.php` with corresponding JS modules in `src/criteria/default/*.js`. The `newspack_popups_default_criteria` filter is applied before registration.

#### Reader Data Library Integration

Criteria values come from the Reader Data Library (`window.newspackRAS`), provided by the main Newspack plugin:
- **Frontend (all readers)**: `window.newspackRAS.store.get('key')` reads a value; `window.newspackRAS.push(ras => ras.store.set('key', value))` sets a value.
- **PHP (registered users)**: `\Newspack\Reader_Data::update_item( $user_id, 'key', wp_json_encode( $value ) )`.

The `matchingAttribute` config key maps to the Reader Data Library store key. If it's a string, the criteria system calls `ras.store.get( matchingAttribute )` to get the value. If it's a function, the function is called with the `ras` instance and should return the value directly.

#### JS-Side Registration

[`registerCriteria( id, config )`](https://github.com/Automattic/newspack-popups/blob/trunk/src/criteria/utils.js#L21) in `src/criteria/utils.js` registers criteria on the client side. Each PHP-registered criteria must have a corresponding JS registration (done automatically for default criteria via their JS modules).

Config options:
- `matchingFunction` — A string referencing a built-in function (`'default'`, `'list__in'`, `'list__not_in'`, `'range'`), or a custom function `( segmentConfig, ras, criteria ) => boolean`.
- `matchingAttribute` — A string (Reader Data Library store key) or a function `( ras ) => value`.

Helper functions for lazy configuration (can be called before or after `registerCriteria`):
- [`setMatchingAttribute( id, matchingAttribute )`](https://github.com/Automattic/newspack-popups/blob/trunk/src/criteria/utils.js#L130) — Sets or overrides the matching attribute.
- [`setMatchingFunction( id, matchingFunction )`](https://github.com/Automattic/newspack-popups/blob/trunk/src/criteria/utils.js#L148) — Sets or overrides the matching function.

#### PHP Filters

- `newspack_popups_default_criteria` — Applied to the default criteria array in `src/criteria/default/index.php` before [`register_criteria()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-criteria.php#L149) is called. Allows modifying, adding, or removing built-in criteria.
- `newspack_popups_registered_criteria` — Applied in [`get_registered_criteria()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-criteria.php#L80) after all criteria are registered. Receives the full flat array of criteria configs. Can add, modify, or remove criteria.

#### Key Source Files

- `includes/class-newspack-popups-criteria.php` — PHP registration, config localization, script enqueuing.
- `src/criteria/default/index.php` — Default criteria definitions (PHP).
- `src/criteria/default/index.js` — Imports all default criteria JS modules.
- `src/criteria/default/*.js` — Individual JS modules that call [`setMatchingAttribute()`](https://github.com/Automattic/newspack-popups/blob/trunk/src/criteria/utils.js#L130) to provide value-fetching logic.
- `src/criteria/utils.js` — [`registerCriteria()`](https://github.com/Automattic/newspack-popups/blob/trunk/src/criteria/utils.js#L21), [`setMatchingAttribute()`](https://github.com/Automattic/newspack-popups/blob/trunk/src/criteria/utils.js#L130), [`setMatchingFunction()`](https://github.com/Automattic/newspack-popups/blob/trunk/src/criteria/utils.js#L148), [`getCriteria()`](https://github.com/Automattic/newspack-popups/blob/trunk/src/criteria/utils.js#L114).
- `src/criteria/matching-functions.js` — Built-in matching function implementations.

## Cross-Plugin Integration

### Integration with newspack-plugin

The plugin checks for these Newspack classes at runtime (all with graceful fallback):
- `\Newspack\Reader_Data` — Enables segmentation features.
- `\Newspack\AMP_Enhancements` — Detects AMP+ configuration for frontend scripts.
- `\Newspack\Logger` — Enhanced logging (falls back to `error_log()`).
- `\Newspack\Patches` — Theme compatibility patches.
- `\Newspack\Donations` — Donation settings for presets.
- `\Newspack\Metering` — Content metering integration.
- `\Newspack\Data_Events` — Event handling for reader actions.

### Form & E-commerce Integration

`Newspack_Popups_Data_Api` hooks into WooCommerce and newsletter/auth forms to track which prompt triggered a conversion:
- `woocommerce_checkout_create_order_line_item` — Attaches prompt metadata to WooCommerce order line items.
- `newspack_blocks_modal_checkout_cart_item_data` — Adds prompt data to checkout cart items.
- `newspack_auth_form_metadata`, `newspack_register_reader_form_metadata`, `newspack_newsletters_subscription_form_metadata` — Attaches prompt metadata to reader registration and newsletter subscription forms.

## Configuration Reference

### Localized JS Globals

| Global | Script | Content |
|--------|--------|---------|
| `newspack_popups_data` | `editor` | Editor data: placements, sizes, taxonomies, preview URLs, post types |
| `newspack_popups_view` | `view` | Frontend data: segments config, settings, debug flags, prompt suppression state |
| `newspack_popups_admin` | `admin` | Admin bar: toggle labels, nonces |
| `newspack_popups_settings` | `settings` | Settings page: full settings array |
| `newspack_popups_blocks_data` | `blocks` | Block data: custom placements list |
| `newspackPopupsCriteria` | `criteria` | Criteria config and user state |
| `newspack_popups_merge_tags` | `editor` | Available merge tags for the editor |

### Settings & Data Storage

| Mechanism | Key/Pattern | Purpose |
|-----------|-------------|---------|
| `wp_options` | `newspack_popups_donor_landing_page`, etc. | Individual settings (see [`Newspack_Popups_Settings::get_settings()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-settings.php#L215)) |
| `wp_options` | `newspack_popups_segments` | Segment definitions |
| `wp_options` | `newspack_popups_custom_placements` | Custom placement definitions |
| `wp_options` | `newspack_popups_ras_prompts` | Preset prompt cache |
| `wp_options` | `newspack_popups_expiry_migrated_to_hourly` | Migration flag |
| Post meta | See `register_meta()` in main class | Per-prompt configuration |

### Key Hooks

**Actions:**
- `newspack_campaigns_after_campaign_render` — Fires after a popup renders (used by Data API for analytics).
- `newspack_popups_check_expiry` — Hourly cron hook for expiring prompts.

**Filters:**
- `newspack_popups_popup_content` — Process popup content before output (used by merge tags).
- `newspack_popups_registered_criteria` — Extend the list of display criteria types.
- `newspack_popups_assess_has_disabled_popups` — Return `true` to disable all popups.
- `newspack_popups_should_display_prompt` — Override whether a specific prompt displays.
- `newspack_popups_admin_user_capability` — Change the admin capability (default: `edit_others_pages`).
- `newspack_popups_size_options` — Modify available overlay size options.
- `newspack_campaigns_post_types_for_campaigns` — Modify supported post types for campaign display.
- `newspack_campaigns_archive_page_types_for_campaigns` — Modify supported archive page types.
- `newspack_campaigns_default_supported_post_types` — Modify default supported post types.

## Recipes

### Add a new display criteria type

**For criteria built into the plugin:**

1. Add the criteria definition to `src/criteria/default/index.php`:
   ```php
   'my_criteria' => [
       'name'              => __( 'My Criteria', 'newspack-popups' ),
       'category'          => 'reader_engagement',
       'matching_function' => 'default', // or 'range', 'list__in', 'list__not_in'
       'matching_attribute' => 'my_criteria', // Reader Data Library store key
   ],
   ```
2. Create a JS module in `src/criteria/default/my-criteria.js` that calls [`setMatchingAttribute()`](https://github.com/Automattic/newspack-popups/blob/trunk/src/criteria/utils.js#L130) from `../utils` to define how the value is fetched:
   ```js
   import { setMatchingAttribute } from '../utils';
   setMatchingAttribute( 'my_criteria', ras => {
       return ras?.store?.get( 'my_criteria' );
   } );
   ```
3. Import the module from `src/criteria/default/index.js`.
4. Set the reader data value so criteria evaluation has data to match against:
   - Frontend: `window.newspackRAS.push( ras => ras.store.set( 'my_criteria', 'value' ) );`
   - PHP (registered users): `\Newspack\Reader_Data::update_item( $user_id, 'my_criteria', wp_json_encode( $value ) );`
5. Add tests in `src/criteria/index.test.js`.

**For third-party criteria (from another plugin):**

1. Register in PHP using the [`register_criteria()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-criteria.php#L149) API:
   ```php
   add_action( 'init', function() {
       if ( class_exists( 'Newspack_Popups_Criteria' ) ) {
           Newspack_Popups_Criteria::register_criteria( 'my_criteria', [
               'name'              => 'My Criteria',
               'category'          => 'reader_engagement',
               'matching_function' => 'default',
           ] );
       }
   } );
   ```
2. Enqueue a JS script that imports from the `criteria` webpack entry (or uses `window.newspackPopupsCriteria`) to call [`setMatchingAttribute()`](https://github.com/Automattic/newspack-popups/blob/trunk/src/criteria/utils.js#L130) for value fetching.
3. Set reader data values as described above.

### Add a new merge tag

1. In `includes/merge-tags/class-merge-tags.php`, call [`self::register_tag()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/merge-tags/class-merge-tags.php#L82) inside [`register_default_tags()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/merge-tags/class-merge-tags.php#L37).
2. Provide a `title`, `description`, and `callback` that returns the replacement value.
3. The tag is automatically available in the editor (via localized `newspack_popups_merge_tags`) and processed at render time via the `newspack_popups_popup_content` filter.

### Add a new REST API endpoint

1. Add the route in [`Newspack_Popups_API::register_api_endpoints()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-api.php#L25) under the `newspack-popups/v1` namespace.
2. Use `$this->permission_callback` for authorization.
3. Follow existing patterns: `sanitize_callback` on all args, return `WP_REST_Response` or `WP_Error`.

### Add a new CLI command

1. Create a class in `includes/cli/` under the `Newspack\Campaigns\CLI` namespace.
2. Register it in [`Newspack_Popups::register_cli_commands()`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups.php#L139) via `WP_CLI::add_command()`.
3. Run `composer dump-autoload`.

## Debugging

### PHP

- Set `NEWSPACK_LOG_LEVEL` in `wp-config.php`: `0` = off, `1` = basic, `2` = verbose.
- [`Newspack_Popups_Logger::log( $payload )`](https://github.com/Automattic/newspack-popups/blob/trunk/includes/class-newspack-popups-logger.php#L19) outputs with header `NEWSPACK-POPUPS`.
- Delegates to `\Newspack\Logger` when available, otherwise `error_log()`.

### JavaScript (Frontend)

- Set `WP_DEBUG` or define `NEWSPACK_POPUPS_DEBUG` in `wp-config.php`.
- Inspect `window.newspack_popups_debug` on the frontend for prompt visibility decisions and segment matching data.
- The `view` script's localized data (`newspack_popups_view`) includes debug flags.

### Admin Bar

When logged in, the admin bar shows a prompt preview toggle that lets you show/hide all prompts on the current page without clearing frequency/dismissal state. Controlled by the `admin` entry point.
