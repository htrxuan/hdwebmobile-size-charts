=== HDWebmobile Size Charts ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: woocommerce, size chart, size guide, product chart, sizing
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add size charts to product categories, with a per-product override. No image upload -- charts are always a plain text table.

== Description ==

HDWebmobile Size Charts lets you build size-guide tables (Size / Bust / Waist / Hips, or whatever columns you choose) and assign each one to one or more product categories. A "Size chart" disclosure appears automatically on matching product pages. Any individual product can override its category's chart from its own Product data panel.

= Why this plugin exists =
"Product Size Charts for WooCommerce" (versions up to and including 2.4.5) shipped CVE-2025-23991 (CWE-862 Missing Authorization): the chart-configuration write path had no capability check, letting it be changed by users who should never have been able to touch store-wide content.

This plugin closes that by construction:

* **One write path, properly gated.** `HDSC_Repository::save_charts()` is called from exactly one place -- the settings-save handler -- which checks `current_user_can('manage_woocommerce')` **and** a verified nonce before a single field is read.
* **No image upload, anywhere.** A chart is only ever a table of admin-typed text (column headers and rows). There is nothing file-shaped in this plugin for a write-path bug to touch.
* **Every value is validated regardless of caller.** Category assignments are checked against real, existing category terms; every cell is sanitised text. A chart with no name, no columns, or no rows is silently skipped rather than guessed at.

= Key Features =
* Build any number of size charts with your own column headers and rows
* Assign a chart to one or more product categories
* Override the category default on any individual product
* No image upload, no JavaScript, no modal library -- a plain, accessible `<details>` disclosure
* Works on classic and block-based product templates

= Limitations (please read before installing) =
* Charts are plain text tables only -- no image-based size charts in this version
* One chart per product (the most specific match: product override, then category)
* No per-variation charts (e.g. a different chart for a specific colour/size variation)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdwebmobile-size-charts` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Size Charts** to build your first chart.

== How to Use ==

= 1. Build a chart =
On the Size Charts tab, name your chart, list your column headers (comma-separated), add rows (one per line, comma-separated cells), and tick the categories it should apply to.

= 2. Override on a single product =
On any product's Product data panel (General tab), pick a specific chart from the "Size chart" dropdown, or leave it on "Use category default".

= 3. Customers see it automatically =
A "Size chart" disclosure appears on the product page for any product with a matching chart.

== Screenshots ==

1. The Size Charts settings tab.
2. The size-chart disclosure on a product page.
3. The per-product chart override on the Product data panel.

== Changelog ==

= 1.0.0 =
* Initial release: category-assigned and per-product size charts, capability- and nonce-gated configuration, no image upload.
