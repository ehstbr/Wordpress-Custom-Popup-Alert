# Contributing

Thanks for your interest in improving WordPress Custom Popup Alert.

## Before opening a pull request

- Keep changes focused and easy to review.
- Follow WordPress coding and security practices.
- Use public WordPress/WooCommerce APIs whenever available.
- Keep WooCommerce optional.
- Preserve the separation between rule evaluation and modal rendering.
- Sanitize input and escape output appropriately.
- Keep frontend JavaScript lightweight and dependency-free unless a dependency is clearly justified.
- Update translations and documentation when user-facing behavior changes.

## Basic checks

Run PHP syntax validation:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

If Node.js is available, validate JavaScript syntax:

```bash
node --check assets/admin/admin.js
node --check assets/frontend/popup.js
```

## Bug reports

A useful bug report should include:

- WordPress version;
- PHP version;
- WooCommerce version, when relevant;
- plugin version;
- steps to reproduce;
- expected behavior;
- actual behavior;
- relevant PHP/JavaScript error messages.
