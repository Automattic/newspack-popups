# Newspack Campaigns (newspack-popups): Agent Instructions

This file covers what is specific to `newspack-popups`. Shared conventions (Docker commands, `n` script, coding standards, git rules, etc.) are in the root `newspack-workspace/AGENTS.md`.

## Linting Commands

```bash
npm run lint             # JS + SCSS only (see gotchas)
npm run lint:js          # JavaScript linting
npm run lint:scss        # SCSS linting
npm run lint:php         # PHP linting (PHPCS)
npm run fix:js           # Auto-fix JS issues
npm run fix:php          # Auto-fix PHP issues (PHPCBF)
```

## Common Gotchas

- `npm run lint` runs JS + SCSS only. PHP linting requires a separate `npm run lint:php`.
- After adding a new PHP file, run `composer dump-autoload` to update the classmap (Composer uses `classmap`, not PSR-4).
- The Inserter removes its `the_content` filter during Homepage Posts block rendering (via `newspack_blocks_homepage_posts_before_render` / `newspack_blocks_homepage_posts_after_render` hooks) to prevent popups from appearing inside post excerpts.
- Two different capability checks exist: REST API endpoints use `manage_options` directly (`Newspack_Popups_API::permission_callback()`), while the general admin check (`Newspack_Popups::is_user_an_admin()`) defaults to `edit_others_pages` and is filterable via the `newspack_popups_admin_user_capability` filter.
- Segmentation features require the main Newspack plugin (`\Newspack\Reader_Data` class). Without it, `Newspack_Popups::$segmentation_enabled` is `false`.
- The standalone Settings page (Campaigns > Settings) is only used when the main Newspack plugin UI is not available.
- Shortcode `[newspack-popup id="..." class="..."]` renders a specific prompt inline. Handled by `Newspack_Popups_Inserter::popup_shortcode()`.

## PHP Backend

### Bootstrap & Autoloading

- **`newspack-popups.php`**: Main plugin file. Defines `NEWSPACK_POPUPS_PLUGIN_FILE`, loads Composer autoloader, and instantiates `Newspack_Popups`.
- **`includes/class-newspack-popups.php`**: Singleton main class. Manually `include_once`s all other classes in the constructor.
- **Autoloading**: Composer `classmap` strategy covering `includes/` and `includes/schemas/`. After adding a new PHP file, run `composer dump-autoload`.

### Key Constants

Defined in `class-newspack-popups.php`:

| Constant | Value |
|----------|-------|
| `NEWSPACK_POPUPS_CPT` | `'newspack_popups_cpt'` |
| `NEWSPACK_POPUPS_TAXONOMY` | `'newspack_popups_taxonomy'` |
| `NEWSPACK_POPUPS_ACTIVE_CAMPAIGN_GROUP` | `'newspack_popups_active_campaign_group'` |
| `NEWSPACK_POPUP_PREVIEW_QUERY_PARAM` | `'pid'` |
| `NEWSPACK_POPUP_PRESET_QUERY_PARAM` | `'preset'` |
| `NEWSPACK_POPUPS_TAXONOMY_STATUS` | `'newspack_popups_taxonomy_status'` |
| `PREVIEW_QUERY_KEYS` | Array of 24 meta-key-to-short-param mappings for preview URLs (see `:22-47`) |

### Class Initialization Patterns

The codebase uses a mix of patterns:

- **Singleton**: `Newspack_Popups::instance()`, `Newspack_Popups_Segmentation::instance()`, `Newspack_Popups_Custom_Placements::instance()`, `Newspack_Popups_View_As::instance()`.
- **File-level instantiation**: `Newspack_Popups_API` and `Newspack_Popups_Inserter` are instantiated via `new` at the bottom of their respective files (not in the main class constructor). The main constructor only `include_once`s the files.
- **Static `init()`**: `Newspack_Popups_Settings::init()` (only when `is_admin()`), `Newspack_Popups_Criteria::init()`, `Newspack_Popups_Expiry::init()`, `Newspack_Popups_Data_Api::init()`, `Newspack_Segments_Model::init()`, `Newspack\Campaigns\Merge_Tags::init_hooks()`.

### Namespace Map

| Namespace | Directory |
|-----------|-----------|
| *(global)* | `includes/` (most classes: `Newspack_Popups`, `Newspack_Popups_Model`, etc.) |
| `Newspack\Campaigns` | `includes/merge-tags/`, `includes/schemas/class-schema.php` |
| `Newspack\Campaigns\CLI` | `includes/cli/` |
| `Newspack\Campaigns\Schemas` | `includes/schemas/` (except `class-schema.php`) |

Most classes in `includes/` use the global namespace with a `Newspack_Popups_` prefix. Newer code under `cli/`, `merge-tags/`, and `schemas/` uses the `Newspack\Campaigns` namespace.

### Custom Post Type & Taxonomies

- **CPT**: `newspack_popups_cpt` (Label: "Prompts"). Supports editor, title, custom-fields, thumbnail, revisions. Not publicly queryable.
- **Campaign taxonomy**: `newspack_popups_taxonomy` — Hierarchical taxonomy for grouping prompts into campaigns (label: "Campaigns").
- **Segment taxonomy**: `popup_segment` — Hierarchical. Managed via `Newspack_Segments_Model`.

### Placement Types Reference

| Slug | Type | Behavior |
|------|------|----------|
| `top` | Overlay | Fixed at top of viewport |
| `bottom` | Overlay | Fixed at bottom of viewport |
| `center` | Overlay | Centered modal |
| `top_right` | Overlay | Top-right corner |
| `top_left` | Overlay | Top-left corner |
| `bottom_right` | Overlay | Bottom-right corner |
| `bottom_left` | Overlay | Bottom-left corner |
| `center_right` | Overlay | Right-side panel |
| `center_left` | Overlay | Left-side panel |
| `inline` | Inline | Inserted between content blocks |
| `above_header` | Inline | Before the page header |
| `archives` | Inline | Inserted between posts on archive pages |
| `manual` | Special | Shortcode-only: `[newspack-popup id="..." class="..."]` |
| `custom1`–`custom3`+ | Special | Rendered via Custom Placement blocks; more can be created in settings |

Placements are defined in `Newspack_Popups_Model`: `$overlay_placements` (line 19) and `$inline_placements` (line 26).

### Content Insertion Algorithm

The Inserter (`class-newspack-popups-inserter.php`) controls how prompts appear in post content:

1. **Content filter**: Hooks `the_content` at priority 1. The `$the_content_has_rendered` flag prevents duplicate insertion on subsequent calls.
2. **Block parsing**: Parses post content into blocks via `parse_blocks()`, converts classic blocks to structured blocks, and filters empty blocks.
3. **Inline insertion**: For each inline prompt, inserts at block boundaries based on `trigger_blocks_count` (number of blocks before the prompt). Skips blocks that shouldn't be followed by prompts (headings, floated images — see `can_block_be_followed_by_prompt()`).
4. **Archive insertion**: Uses `archive_insertion_posts_count` to insert between posts, with an optional `archive_insertion_is_repeating` flag.
5. **Above-header insertion**: Hooks `wp_body_open` for `above_header` placement.
6. **Shortcode**: `[newspack-popup id="..." class="..."]` via `popup_shortcode()` for manual placement.
7. **Homepage Posts block**: Removes and restores the `the_content` filter around Homepage Posts block rendering to prevent prompts inside excerpts.

### Post Meta

Registered in `Newspack_Popups::register_meta()` (lines 183–622). All meta keys below use `object_subtype => newspack_popups_cpt` unless noted.

**Trigger:**
- `trigger_type` (string) — `scroll` or `time`
- `trigger_scroll_progress` (integer) — Scroll percentage for scroll trigger
- `trigger_blocks_count` (integer) — Number of blocks before inline prompt insertion
- `trigger_delay` (integer) — Delay in ms for time trigger

**Archive:**
- `archive_insertion_posts_count` (integer) — Posts between prompt in archives
- `archive_insertion_is_repeating` (boolean) — Whether to repeat in archives

**Frequency:**
- `frequency` (string) — Frequency type (e.g., `once`, `daily`, `always`, `custom`)
- `frequency_max` (integer, default: 0) — Max display count
- `frequency_start` (integer, default: 0) — Pageviews before first display
- `frequency_between` (integer, default: 0) — Pageviews between displays
- `frequency_reset` (string, default: `'month'`) — Reset period

**Placement:**
- `placement` (string) — Placement slug (see Placement Types Reference)
- `post_types` (array) — Post types to display on
- `archive_page_types` (array) — Archive page types to display on
- `utm_suppression` (string) — UTM parameter value to suppress prompt

**Styling:**
- `background_color` (string), `overlay_color` (string), `overlay_opacity` (integer), `overlay_size` (string, default: `'medium'`), `no_overlay_background` (boolean, default: false)
- `close_button_background_color` (string), `enable_close_button_background` (boolean, default: false)
- `hide_border` (boolean), `large_border` (boolean), `no_padding` (boolean)
- `additional_classes` (string, default: `''`)

**Content:**
- `excluded_categories` (array of integers, default: []), `excluded_tags` (array of integers, default: [])
- `duplicate_of` (integer, default: 0) — ID of the original prompt if this is a duplicate

**Dates:**
- `expiration_date` (string), `activation_date` (string), `deactivation_date` (string)

**Global (all post types — no `object_subtype`):**
- `newspack_popups_has_disabled_popups` (boolean) — Per-post toggle to disable all prompts on that post/page.

### REST API

Base namespace: `newspack-popups/v1`

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/settings` | `manage_options` | Update plugin settings |
| GET | `/prompts` | `manage_options` | Get inline/manual prompts with filtering |
| GET | `/{original_id}/{id}/duplicate` | `manage_options` | Get suggested duplicate title |
| POST | `/{id}/duplicate` | `manage_options` | Duplicate a popup |
| GET | `/audience-management/campaign` | `manage_options` | Get Reader Activation campaign settings |
| POST | `/audience-management/campaign` | `manage_options` | Update Reader Activation campaign settings |
| GET | `/custom-placement` | **Public** | Get prompts for a custom placement (no auth required) |

The first 6 endpoints use `Newspack_Popups_API::permission_callback()` (`manage_options`). The `/custom-placement` endpoint is registered in `Newspack_Popups_Custom_Placements::rest_api_init()` with `'permission_callback' => '__return_true'`.

### Settings & Data Storage

| Mechanism | Key/Pattern | Purpose |
|-----------|-------------|---------|
| `wp_options` | `newspack_popups_donor_landing_page`, etc. | Individual settings (see `Newspack_Popups_Settings::get_settings()`) |
| `wp_options` | `newspack_popups_segments` | Segment definitions |
| `wp_options` | `newspack_popups_custom_placements` | Custom placement definitions |
| `wp_options` | `newspack_popups_ras_prompts` | Preset prompt cache |
| `wp_options` | `newspack_popups_expiry_migrated_to_hourly` | Migration flag |
| Post meta | See Post Meta section above | Per-prompt configuration |

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

### Logging

`Newspack_Popups_Logger::log( $payload )` logs with header `NEWSPACK-POPUPS`. Delegates to `\Newspack\Logger` if available, otherwise falls back to `error_log()`. Gated by the `NEWSPACK_LOG_LEVEL` constant.

### WP-CLI Commands

Namespace: `newspack-popups`, defined in `includes/cli/`.

| Command | Description |
|---------|-------------|
| `wp newspack-popups export [--file=<file>]` | Export prompts |
| `wp newspack-popups import <file>` | Import prompts |
| `wp newspack-popups prune-data` | Prune analytics data |
| `wp newspack-popups delete-all` | Delete all prompts |

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

### PHP Testing

```bash
npm run lint:php         # PHP linting (PHPCS)
npm run fix:php          # Auto-fix PHP issues (PHPCBF)
```

- Tests live in `tests/`, extend `WP_UnitTestCase` (or `WP_UnitTestCase_PageWithPopups` from `tests/wp-unittestcase-pagewithpopups.php`).
- Bootstrap: `tests/bootstrap.php`. Defines `IS_TEST_ENV` constant (used to skip script enqueuing in tests).
- No `@group` annotations are used. Run all tests via `n test-php` from the repo directory.
- Test files cover: blocks, classic-block-encoding, content-insertion, criteria, e2e, exporter, importer, insertion-cpt, insertion, merge-tags, model, popups-expiry, presets, schemas, segmentation, segments.

## Frontend (JS/React)

### Architecture

No TypeScript — the frontend is entirely JavaScript. No custom `@wordpress/data` stores. Editor components use WordPress core stores (`core/editor`, `core/edit-post`) via `withSelect`/`withDispatch` and `useSelect`/`useDispatch`. Settings page and editor sidebar use standard `@wordpress/components` and `@wordpress/api-fetch`.

### Webpack Entry Points

7 hardcoded entries in `webpack.config.js`:

| Entry | Source | Purpose |
|-------|--------|---------|
| `editor` | `src/editor/` | Block editor sidebar panels (placement, frequency, colors, styles, preview, merge tags) |
| `view` | `src/view/` | Frontend: segmentation, analytics, merge tag resolution |
| `admin` | `src/view/admin.js` | Admin bar prompt preview toggle |
| `documentSettings` | `src/document-settings/` | Per-post prompt visibility settings |
| `settings` | `src/settings/` | Standalone settings page (fallback when newspack-plugin UI is unavailable) |
| `blocks` | `src/blocks/` | Custom Placement and Single Prompt blocks |
| `criteria` | `src/criteria/` | Display criteria evaluation scripts |

### Editor Sidebar Panels

Registered via `registerPlugin` in `src/editor/index.js`. These define the prompt editing experience:

| Plugin ID | Panel Title | Component |
|-----------|-------------|-----------|
| `newspack-popups` | Settings | `Sidebar` — Placement, trigger type, size |
| `newspack-popups-styles` | Styles | `StylesSidebar` — Border, padding options |
| `newspack-popups-frequency` | Frequency | `FrequencySidebar` — Display frequency (conditional: only when segmentation is enabled) |
| `newspack-popups-colors` | Color | `ColorsSidebar` — Background, overlay, close button colors |
| `newspack-popups-post-types` | Post Types | `PostTypesPanel` — Target post types and archive types |
| `newspack-popups-expiration` | Expiration | `ExpirationPanel` — Expiration/activation dates |
| `newspack-popups-advanced` | Advanced Settings | `AdvancedSidebar` — Additional classes, UTM suppression |
| `newspack-popups-preview` | *(Post Status)* | `Preview` + `Duplicate` buttons in the post status section |
| `newspack-popups-editor` | *(hidden)* | `EditorAdditions` — Segment help text and campaign term management |
| `newspack-popups-disable-newspack-blocks-deduplication` | *(hidden)* | Hides the Homepage Posts deduplication toggle for overlay prompts |

Merge tag toolbar control is added via `editor.BlockEdit` filter (not `registerPlugin`) for paragraph, heading, list-item, quote, pullquote, verse, and preformatted blocks.

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

### Blocks

Two blocks in `src/blocks/`, both with server-side rendering (`view.php`):

- **`newspack-popups/custom-placement`** — Renders prompts at a custom insertion point.
- **`newspack-popups/single-prompt`** — Renders a specific prompt by ID.

### Data Attributes Reference

Data attributes on `.newspack-popup-container` elements drive frontend display logic:

| Attribute | Purpose |
|-----------|---------|
| `data-id` | Popup post ID |
| `data-delay` | Display delay in ms (overlay time trigger) |
| `data-scroll` | Scroll trigger marker element ID |
| `data-frequency` | Encoded as `start,between,max,reset_period` |
| `data-suppression` | UTM suppression value |
| `data-segments` | Comma-separated segment IDs |

Key CSS classes: `.newspack-popup-container`, `.newspack-lightbox` (overlay wrapper), `.newspack-lightbox__close` (close button), `.newspack-lightbox-overlay` (backdrop), `.hidden` (initially applied, removed to show prompt).

### Frontend Segmentation Flow

The `view` entry point (`src/view/index.js`) orchestrates the client-side prompt display:

1. Logs a pageview via Reader Data (`window.newspackRAS`).
2. If prompts are not disabled, collects all `.newspack-popup-container` elements.
3. `segmentation.js` determines the reader's best-priority matching segment using localized segment config.
4. Each prompt is checked against its assigned segments and frequency settings.
5. Matching prompts are unhidden by removing the `.hidden` class. Overlay prompts use `IntersectionObserver` for scroll triggers and delay timers for time triggers.
6. Only one overlay prompt displays per pageview. `closeOverlay()` handles dismissal.
7. `analytics/` modules track loaded, seen, clicked, and dismissed events via GA4.

### Criteria System

Display criteria determine whether a prompt is shown to a reader based on reader data (articles read, device, donation status, etc.). Criteria are registered in PHP and evaluated client-side against data from the Reader Data Library (`window.newspackRAS`).

#### Registration API

The primary PHP method is `Newspack_Popups_Criteria::register_criteria( $id, $config )` (in `includes/class-newspack-popups-criteria.php`).

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

#### Default Criteria

Registered in `src/criteria/default/index.php` via `register_criteria()`. The `newspack_popups_default_criteria` filter is applied to the array before registration, allowing modification of built-in criteria.

| ID | Category | Matching Function | Matching Attribute |
|----|----------|-------------------|--------------------|
| `articles_read` | `reader_engagement` | `range` | `articles_read` |
| `articles_read_in_session` | `reader_engagement` | `range` | `articles_read_in_session` |
| `favorite_categories` | `reader_engagement` | `list__in` | `favorite_categories` |
| `devices` | `reader_engagement` | `default` | `devices` (uses `options` with `params` for viewport widths) |
| `user_account` | `reader_activity` | `default` | `user_account` |
| `newsletter` | `newsletter` | `default` | `newsletter` |
| `subscribed_lists` | `newsletter` | `list__in` | `newsletter_subscribed_lists` |
| `not_subscribed_lists` | `newsletter` | `list__not_in` | `newsletter_subscribed_lists` |
| `donation` | `reader_revenue` | `default` | `donation` |
| `active_subscriptions` | `reader_revenue` | `list__in` | `active_subscriptions` |
| `not_active_subscriptions` | `reader_revenue` | `list__not_in` | `active_subscriptions` |
| `active_memberships` | `reader_revenue` | `list__in` | `active_memberships` |
| `not_active_memberships` | `reader_revenue` | `list__not_in` | `active_memberships` |
| `sources_to_match` | `referrer_sources` | `list__in` | `referrer` |
| `sources_to_exclude` | `referrer_sources` | `list__not_in` | `referrer` |

Each default criterion also has a JS module in `src/criteria/default/` (e.g., `articles-read.js`, `devices.js`) that calls `setMatchingAttribute()` to provide the value-fetching logic. These are imported from `src/criteria/default/index.js`.

#### Reader Data Library Integration

Criteria values come from the Reader Data Library (`window.newspackRAS`), provided by the main Newspack plugin:
- **Frontend (all readers)**: `window.newspackRAS.store.get('key')` reads a value; `window.newspackRAS.push(ras => ras.store.set('key', value))` sets a value.
- **PHP (registered users)**: `\Newspack\Reader_Data::update_item( $user_id, 'key', wp_json_encode( $value ) )`.

The `matchingAttribute` config key maps to the Reader Data Library store key. If it's a string, the criteria system calls `ras.store.get( matchingAttribute )` to get the value. If it's a function, the function is called with the `ras` instance and should return the value directly.

#### JS-Side Registration

`registerCriteria( id, config )` in `src/criteria/utils.js` registers criteria on the client side. Each PHP-registered criteria must have a corresponding JS registration (done automatically for default criteria via their JS modules).

Config options:
- `matchingFunction` — A string referencing a built-in function (`'default'`, `'list__in'`, `'list__not_in'`, `'range'`), or a custom function `( segmentConfig, ras, criteria ) => boolean`.
- `matchingAttribute` — A string (Reader Data Library store key) or a function `( ras ) => value`.

Helper functions for lazy configuration (can be called before or after `registerCriteria`):
- `setMatchingAttribute( id, matchingAttribute )` — Sets or overrides the matching attribute.
- `setMatchingFunction( id, matchingFunction )` — Sets or overrides the matching function.

#### PHP Filters

- `newspack_popups_default_criteria` — Applied to the default criteria array in `src/criteria/default/index.php` before `register_criteria()` is called. Allows modifying, adding, or removing built-in criteria.
- `newspack_popups_registered_criteria` — Applied in `get_registered_criteria()` after all criteria are registered. Receives the full flat array of criteria configs. Can add, modify, or remove criteria.

#### Key Source Files

- `includes/class-newspack-popups-criteria.php` — PHP registration, config localization, script enqueuing.
- `src/criteria/default/index.php` — Default criteria definitions (PHP).
- `src/criteria/default/index.js` — Imports all default criteria JS modules.
- `src/criteria/default/*.js` — Individual JS modules that call `setMatchingAttribute()` to provide value-fetching logic.
- `src/criteria/utils.js` — `registerCriteria()`, `setMatchingAttribute()`, `setMatchingFunction()`, `getCriteria()`.
- `src/criteria/matching-functions.js` — Built-in matching function implementations.

### SCSS

- Editor styles: `src/editor/style.scss`
- Frontend prompt styles: `src/view/style.scss`, `src/view/patterns.scss`
- Settings page: `src/settings/style.scss`
- Block editor styles: `src/blocks/*/editor.scss`
- Admin bar styles: `src/view/admin.scss`

### JS Testing

```bash
npm run test             # Run full JS test suite
```

- Jest via `newspack-scripts test`.
- Test files colocated with source using `.test.js` suffix.

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
2. Create a JS module in `src/criteria/default/my-criteria.js` that calls `setMatchingAttribute()` from `../utils` to define how the value is fetched:
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

1. Register in PHP using the `register_criteria()` API:
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
2. Enqueue a JS script that imports from the `criteria` webpack entry (or uses `window.newspackPopupsCriteria`) to call `setMatchingAttribute()` for value fetching.
3. Set reader data values as described above.

### Add a new merge tag

1. In `includes/merge-tags/class-merge-tags.php`, call `self::register_tag()` inside `register_default_tags()`.
2. Provide a `title`, `description`, and `callback` that returns the replacement value.
3. The tag is automatically available in the editor (via localized `newspack_popups_merge_tags`) and processed at render time via the `newspack_popups_popup_content` filter.

### Add a new REST API endpoint

1. Add the route in `Newspack_Popups_API::register_api_endpoints()` under the `newspack-popups/v1` namespace.
2. Use `$this->permission_callback` for authorization.
3. Follow existing patterns: `sanitize_callback` on all args, return `WP_REST_Response` or `WP_Error`.

### Add a new CLI command

1. Create a class in `includes/cli/` under the `Newspack\Campaigns\CLI` namespace.
2. Register it in `Newspack_Popups::register_cli_commands()` via `WP_CLI::add_command()`.
3. Run `composer dump-autoload`.

## Debugging

### PHP

- Set `NEWSPACK_LOG_LEVEL` in `wp-config.php`: `0` = off, `1` = basic, `2` = verbose.
- `Newspack_Popups_Logger::log( $payload )` outputs with header `NEWSPACK-POPUPS`.
- Delegates to `\Newspack\Logger` when available, otherwise `error_log()`.

### JavaScript (Frontend)

- Set `WP_DEBUG` or define `NEWSPACK_POPUPS_DEBUG` in `wp-config.php`.
- Inspect `window.newspack_popups_debug` on the frontend for prompt visibility decisions and segment matching data.
- The `view` script's localized data (`newspack_popups_view`) includes debug flags.

### Admin Bar

When logged in, the admin bar shows a prompt preview toggle that lets you show/hide all prompts on the current page without clearing frequency/dismissal state. Controlled by the `admin` entry point.
