# Lightweight API

The lightweight API is a stripped-down alternative to the WordPress REST API. Prompts will often be present on every page of a site, which can lead to performance issues as a result of a large number of uncacheable API requests.

## Configuration

The feature requires a custom config file named `newspack-popups-config.php` at the root of the site. See the main README for more information.

## API Overview

| Endpoint | Frequency | Purpose |
| :------------- | :------------- | :------------- |
| `GET /api/campaigns/index.php` | Page load | Tells the page which prompts should be displayed. This is the [authorization endpoint](https://amp.dev/documentation/components/amp-access/#authorization-endpoint) of the `amp-access` AMP component. |
| `GET /api/segmentation/index.php` | Page load | An analytics [config rewriter](https://amp.dev/documentation/components/amp-analytics/?format=websites#dynamically-rewrite-a-configuration), which inserts segmentation-derived custom dimensions. This feature has to be additionaly configured in Newspack Plugin's Analytics Wizard. |
| `POST /api/campaigns/index.php` | Interaction event | Reporting prompt-related events, such as user seeing or suppressing a prompt. |
| `POST /api/segmentation/index.php` | Page load or event | Reporting client segmentation-related data, such as a donation or newsletter subscription status. |

## Disabling the API calls

In high-traffic periods or for debugging purposes, it's possible to disable any calls to the API with the "non-interactive mode" setting (`newspack_popups_non_interative_mode` option).
