# Changelog

All notable changes to **WordPress Custom Popup Alert** are documented here.

## 1.5.1

- Fixed a fatal error when opening the new or edit alert screen caused by an unavailable conditional-attribute helper.

## 1.5.0

- Added five countdown presentation choices: none, discreet footer text, bottom progress bar, circular close (X) progress, and remaining seconds in close-action buttons.
- Moved the textual countdown onto the same footer line as the buttons and reduced its visual prominence.
- Added an option to pause automatic closing while the pointer is over the popup and resume from the exact remaining time.
- Updated the live behavior preview to animate every countdown presentation and demonstrate the hover pause state.
- Disabled schedule date inputs as well as hiding them when scheduling is turned off.
- Preserved the former countdown checkbox setting as the new textual countdown mode.

## 1.4.0

- Added a unified real-time preview to Behavior and Buttons.
- Made the behavior preview react to closing options, delay, auto-close, countdown, animation, and both footer buttons.
- Fixed schedule date fields remaining visible after scheduling was disabled.
- Ensured all conditional admin fields remain hidden when their controlling option is disabled.

## 1.3.0

- Kept dimension values and their compact unit selectors on the same line.
- Added real-time visual previews for popup colors and shadow, border, page overlay, and header bar.
- Made the live header preview react to its enabled state, colors, and selected icon.
- Made the overlay preview react to color, opacity, blur, and enabled state.

## 1.2.0

- Added an explicit schedule switch and hid date fields while scheduling is disabled.
- Added a visual popup dimension editor for width, maximum height, content padding, and corner radius.
- Added pixel, percentage, and viewport units for width and maximum height.
- Preserved the behavior of schedules and dimensions saved by previous versions.

## 1.1.1

- Fixed disabled footer buttons being visually rendered as empty buttons when the other button was enabled.
- Ensured every hidden popup control remains hidden even when its component uses a flex display rule.

## 1.1.0

- Replaced the block editor with the traditional WordPress Visual/Text editor for popup alerts.
- Reorganized all alert settings into clear native panels below the editor.
- Added a direct WooCommerce Cart/Checkout page condition.
- Converted all source strings to English and included a Brazilian Portuguese translation.

## 1.0.0

- Initial MVP release.
