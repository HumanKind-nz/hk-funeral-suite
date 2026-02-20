![HumanKind Funeral Suite](https://weave-hk-github.b-cdn.net/humankind/plugin-header.png)

# HumanKind Funeral Suite

A WordPress plugin for funeral home websites. Custom post types, taxonomies, meta fields, and Gutenberg blocks for managing staff, caskets, urns, monuments, keepsakes, and pricing packages. No ACF required.

## Requirements

- WordPress 6.6+
- PHP 8.1+
- Block editor (Gutenberg) enabled

> **Note:** This plugin relies on the block editor for meta field entry. If Gutenberg is disabled, the CPTs will not function as intended.

## Installation

### From a release

1. Download the latest `.zip` from the [Releases page](https://github.com/HumanKind-nz/hk-funeral-suite/releases)
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the zip, install, and activate

### From source

```bash
git clone https://github.com/HumanKind-nz/hk-funeral-suite.git
cd hk-funeral-suite
npm install && npm run build
```

Then copy to `wp-content/plugins/` or symlink for local development.

### Auto-updates

The plugin includes a GitHub updater. When a new release is published, WordPress will show an update notification on the Plugins screen — click **Update Now** to install.

## What's Included

### Custom Post Types

- **Pricing Packages** — service packages with pricing, display order, and intro text
- **Staff** — team member profiles with position, qualifications, contact details, location and role taxonomies
- **Caskets** — product catalog with pricing and category taxonomy
- **Urns** — product catalog with pricing and category taxonomy
- **Monuments** — product catalog with pricing and category taxonomy
- **Keepsakes** — product catalog with pricing, product code, metal type, stones, and category taxonomy

Each CPT has a dedicated Gutenberg block for structured data entry using `useEntityProp`.

### Settings Page

React-powered settings at **Settings > HK Funeral Suite** with four tabs:

- **General** — enable/disable CPTs and public visibility
- **Integration** — Google Sheets price sync per product type
- **Compatibility** — theme/plugin meta box cleanup (GeneratePress, Page Builder Framework, HappyFiles, SEOPress)
- **About** — plugin info and links

### Google Sheets Price Sync

One-way sync from Google Sheets to WordPress for managing pricing across multiple sites. When enabled:

- Price fields are locked in the block editor
- Visual indicators show externally managed pricing
- REST API accepts updates from the Google Apps Script
- Caches clear automatically on price changes

Enable per product type in **Settings > HK Funeral Suite > Integration**.

### Shortcodes

**`[hk_formatted_price]`** — formatted price output with currency symbol, decimal precision, and fallback for non-numeric values (e.g. "P.O.A.").

**`[hk_custom_field]`** — display any custom field with date formatting, before/after wrappers, and fallback content.

See [Shortcode Usage Examples](shortcode-usage.md) for full documentation and [Beaver Themer Integration](beaver-themer-guide.md) for using these with Beaver Builder layouts.

### Compatibility

The plugin can remove unnecessary meta boxes from the editing interface:

- **GeneratePress** — layout options and sections meta boxes
- **Page Builder Framework** — theme settings meta boxes
- **HappyFiles** — featured image column (not needed)
- **SEOPress** — metaboxes and content analysis on non-public CPTs

Configure in **Settings > HK Funeral Suite > Compatibility**.

## Usage

### Getting started

1. Activate the plugin
2. Visit **Settings > HK Funeral Suite** to enable the CPTs you need
3. Start adding content under the new admin menu items

### Beaver Themer

See [Meta Fields & Beaver Builder Integration](beaver-themer-guide.md) for using custom fields in Beaver Themer layouts.

### Importing content

Compatible with WP All Import Pro. The plugin automatically adds required blocks to imported posts. After import, review and update any missing metadata as needed.

## Development

```bash
npm run start        # Watch all source files
npm run build        # Production build
npm run lint         # Lint JS and CSS
```

## Plugin Structure

```
hk-funeral-suite/
├── inc/                               # PHP modules (namespaced functions)
│   ├── post-types.php                 # CPT and taxonomy registration
│   ├── blocks.php                     # Block registration via block.json
│   ├── block-editor.php               # Block editor customisation
│   ├── admin-columns.php              # Admin list table columns
│   ├── hooks.php                      # Activation, compatibility, admin tweaks
│   ├── settings-page.php              # React settings page (HK_Funeral_Settings)
│   ├── google-sheets.php              # Google Sheets price sync integration
│   ├── shortcodes.php                 # Shortcode registration
│   ├── import.php                     # All Import Pro compatibility
│   └── github-updater.php             # GitHub automatic updates
├── src/
│   ├── blocks/                        # JSX block source (useEntityProp)
│   │   ├── staff-meta/                # Team member block
│   │   ├── casket-meta/               # Casket block
│   │   ├── urn-meta/                  # Urn block
│   │   ├── package-meta/              # Pricing package block
│   │   ├── monument-meta/             # Monument block
│   │   └── keepsake-meta/             # Keepsake block
│   └── js/settings/                   # React settings app source
├── build/                             # Compiled assets (npm run build)
├── assets/images/                     # Plugin icons and banner
├── uninstall.php                      # Clean uninstall handler
└── hk-funeral-suite.php               # Main plugin file
```

## Changelog

[Full changelog](CHANGELOG.md)

### [2.0.0] - 2026-02-21
- Rewritten from class-based to namespaced function architecture
- `@wordpress/scripts` dual build system, JSX blocks with `useEntityProp`, React settings page
- Native block locking, Settings link on Plugins screen, improved admin menu ordering
- Requires WordPress 6.6+ and PHP 8.1+

### [1.4.17] - 2025-12-05
- New `[hk_team_member_content]` shortcode for Beaver Builder and classic themes

### [1.4.0] - 2025-04-07
- Keepsakes custom post type

### [1.3.0] - 2025-03-23
- Monuments custom post type, shared CPT registration

### [1.1.0] - 2025-03-06
- Google Sheets integration for pricing management

### [1.0.0] - 2025-01-15
- Initial release with Staff, Caskets, Urns, and Pricing Packages

## Credits

Developed by [HumanKind](https://humankindwebsites.com), [Weave Digital Studio](https://weave.co.nz), and Gareth Bissland.

## Support

For support, feature requests, or bug reports, use the [GitHub issue tracker](https://github.com/HumanKind-nz/hk-funeral-suite/issues).

## License

GPL v2 or later.
