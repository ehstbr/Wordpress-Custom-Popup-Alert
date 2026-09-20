=== WordPress Custom Popup Alert ===
Contributors: Eduardo Henrique Teixeira
Tags: popup, modal, alerts, woocommerce, conditional content
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.5.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Display contextual popup alerts based on WordPress and WooCommerce rules.

== Description ==

WordPress Custom Popup Alert creates notices, messages, and alerts in a responsive modal. A nested AND/OR rule tree determines where each alert is displayed.

The private `wccpa_alert` post type uses the traditional WordPress Visual/Text editor. All settings appear in clear native panels immediately below the editor, with familiar WordPress controls, buttons, tables, and color pickers.

= Features =

* published alerts are active; drafts are inactive;
* traditional WordPress WYSIWYG with Visual/Text modes, media, links, and formatted content;
* public popup title separate from the internal dashboard name;
* optional schedule enabled explicitly, with start and end limits in the site timezone;
* nested AND/OR groups and negative operators;
* priority from 1 to 100, sequential queue, and exclusive alerts;
* display every time, once per session, every X days, or never again;
* browser-side frequency storage compatible with full-page caching;
* responsive modal with compact dimension controls and real-time visual previews for colors, shadow, border, overlay, and header bar;
* live behavior preview for closing options, timing, animation, all countdown presentations, hover pause, and footer buttons;
* selectable pixel, percentage, and viewport units for popup width and maximum height;
* close button, overlay click, ESC key, automatic closing, optional hover pause, and opening delay;
* five countdown display choices: none, discreet footer text, bottom progress bar, circular close-button progress, or remaining time in close-action buttons;
* focus trapping and restoration, scroll lock, and `prefers-reduced-motion` support;
* dashboard preview;
* AJAX search for content, products, terms, and authors;
* native action to duplicate an alert as a draft;
* frontend assets loaded only when an alert is eligible for the current context;
* translation-ready English source with a Brazilian Portuguese translation included.

= WordPress conditions =

* content type;
* specific content;
* post category;
* post tag;
* author;
* keyword in the title, excerpt, content, or all three.

= WooCommerce conditions =

* WooCommerce page: Cart and/or Checkout;
* specific product, searchable by name, ID, or SKU;
* product category, optionally including subcategories;
* product tag;
* shipping class;
* on-sale status;
* stock status;
* product type;
* SKU with exact, prefix, suffix, or partial matching.

WooCommerce is optional. When it is inactive, the plugin continues to work. Existing WooCommerce rules are preserved but not evaluated.

== Installation ==

1. In WordPress, go to Plugins > Add New Plugin > Upload Plugin.
2. Select the WordPress Custom Popup Alert ZIP file.
3. Install and activate it.
4. Go to Popup Alerts > Add New.
5. Use the WordPress title as the internal name and write the message in the Visual/Text editor.
6. Configure at least one display rule. An empty rule tree never displays the alert.
7. Publish to activate, or save as a draft to keep it inactive.

== Frequently Asked Questions ==

= What happens when a visitor clears browser data? =

The frequency record is removed, so the alert may appear again. The same applies to another device or a private browsing window.

= Does the plugin create a PHP session or a custom database table? =

No. Frequency uses `sessionStorage` and `localStorage`; configuration uses native posts and post meta.

= Does it work with Cloudflare, LiteSpeed Cache, WP Rocket, or server caching? =

Per-visitor frequency is enforced in the browser, so the server does not need to vary HTML for each visitor. Context rules still depend on the cache serving the correct page for each URL, as with other WordPress and WooCommerce output.

= How are variable products evaluated? =

Rules use the parent product of the current product page. Re-evaluating after a variation selection is outside the current version.

= What does “exclusive” mean? =

After browser frequency is applied, an eligible exclusive alert removes lower-priority alerts from the queue. Higher-priority alerts still appear before it.

= Why does a published alert not appear? =

Check that it has at least one condition, the entire AND/OR tree matches the current page, the schedule is valid, and browser frequency permits another display.

== Extensibility ==

The engine accepts condition objects that implement `WCCPA\Conditions\Condition` through the `wccpa_registered_conditions` filter.

Main hooks:

* `wccpa_registered_conditions`;
* `wccpa_condition_result`;
* `wccpa_alert_matches`;
* `wccpa_alert_content`;
* `wccpa_alerts_before_render`;
* `wccpa_alerts_after_render`.

== Privacy ==

The plugin sends no data to external services and does not store visitors in the database. Frequency stores only the alert identifier and display time in the visitor's browser storage.

== Uninstallation ==

Deleting the plugin preserves alerts and settings by default. To remove its data too, define `WCCPA_REMOVE_DATA` as `true` in `wp-config.php` before deletion.

== Changelog ==

= 1.5.1 =

* Fixed a fatal error when opening the new or edit alert screen caused by an unavailable conditional-attribute helper.

= 1.5.0 =

* Added five countdown presentation choices: none, discreet footer text, bottom progress bar, circular close (X) progress, and remaining seconds in close-action buttons.
* Moved the textual countdown onto the same footer line as the buttons and reduced its visual prominence.
* Added an option to pause automatic closing while the pointer is over the popup and resume from the exact remaining time.
* Updated the live behavior preview to animate every countdown presentation and demonstrate the hover pause state.
* Disabled schedule date inputs as well as hiding them when scheduling is turned off.
* Preserved the former countdown checkbox setting as the new textual countdown mode.

= 1.4.0 =

* Added a unified real-time preview to Behavior and Buttons.
* Made the behavior preview react to closing options, delay, auto-close, countdown, animation, and both footer buttons.
* Fixed schedule date fields remaining visible after scheduling was disabled.
* Ensured all conditional admin fields remain hidden when their controlling option is disabled.

= 1.3.0 =

* Kept dimension values and their compact unit selectors on the same line.
* Added real-time visual previews for popup colors and shadow, border, page overlay, and header bar.
* Made the live header preview react to its enabled state, colors, and selected icon.
* Made the overlay preview react to color, opacity, blur, and enabled state.

= 1.2.0 =

* Added an explicit schedule switch and hid date fields while scheduling is disabled.
* Added a visual popup dimension editor for width, maximum height, content padding, and corner radius.
* Added pixel, percentage, and viewport units for width and maximum height.
* Preserved the behavior of schedules and dimensions saved by previous versions.

= 1.1.1 =

* Fixed disabled footer buttons being visually rendered as empty buttons when the other button was enabled.
* Ensured every hidden popup control remains hidden even when its component uses a flex display rule.

= 1.1.0 =

* Replaced the block editor with the traditional WordPress Visual/Text editor for popup alerts.
* Reorganized all alert settings into clear native panels below the editor.
* Added a direct WooCommerce Cart/Checkout page condition.
* Converted all source strings to English and included a Brazilian Portuguese translation.

= 1.0.0 =

* Initial MVP release.
