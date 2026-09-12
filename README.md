# HDWebmobile Size Charts

Add size charts to product categories, with a per-product override. No image upload — charts are always a plain text table.

- **WordPress.org:** https://wordpress.org/plugins/hdwebmobile-size-charts/
- **Requires:** WordPress 6.9+, WooCommerce, PHP 7.4+
- **License:** GPLv2 or later

## Description

Build size-guide tables and assign each to one or more product categories. A "Size chart" disclosure appears automatically on matching product pages; any product can override its category's chart.

## Why this plugin exists

"Product Size Charts for WooCommerce" (≤ 2.4.5) shipped CVE-2025-23991 (CWE-862 Missing Authorization) — no capability check on the chart-config write path.

Closed by construction:

* **One write path, properly gated** — `HDSC_Repository::save_charts()` is called only from the settings handler, behind `manage_woocommerce` + a verified nonce.
* **No image upload anywhere** — a chart is only admin-typed text.
* **Every value validated regardless of caller** — real category terms only, sanitised cells, incomplete charts silently skipped.

## Features

* Any number of charts, your own columns and rows
* Assign to categories, override per product
* No image upload, no JS, no modal — a plain accessible `<details>` disclosure
* Classic and block product templates

## Limitations

* Plain text tables only, no image-based charts
* One chart per product (product override, then category)
* No per-variation charts

## Installation

1. Upload to `/wp-content/plugins/hdwebmobile-size-charts`, or install through the WordPress plugins screen.
2. Activate. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Size Charts**.

## License

GPLv2 or later — https://www.gnu.org/licenses/gpl-2.0.html
