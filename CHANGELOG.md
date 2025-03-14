# Changelog

All notable changes to the HumanKind Funeral Suite plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.1] - 2025-03-14
- **Added** Theme & Plugin compatibility settings to remove unnecessary meta boxes from funeral content type entry pages
- **Added** Support for GeneratePress, Page Builder Framework, and Perfmatters (themes/plugins used by Weave Digital Studio)
- **Renamed** Admin classes for better consistency in the codebase

## [1.2.0] - 2025-03-13
- **Added** block protection to prevent accidental deletion of required data fields in all custom post types
- **Fixed** synchronization between block editor and meta fields for intro paragraphs
- **Removed** redundant intro meta box from editor screen
- **Added** SEO column management to hide SEO columns when CPTs are set to non-public
- Centralised featured image column handling across all custom post types
- Renamed "Title" column to "Name" in admin lists for better clarity

## [1.1.7] - 2025-03-12
- **Performance:** Added autosave and revision checks to prevent unnecessary processing for cpt updates

## [1.1.6] - 2025-03-12
- minor bug fixes

## [1.1.4] - 2025-03-09
### Added
- **New shortcode** `[hk_formatted_price]` for outputting formatted prices with:
  - Custom currency symbols
  - Prefix and suffix support
  - Adjustable decimal precision
  - Graceful fallback for non-numeric values (e.g., "P.O.A.")
  - Compatible with Page Builders / Beaver Builder
  - More info see [Shortcode Usage Examples](shortcode-usage.md)
- **Admin UI Enhancements**:
  - Updated CPT visibility settings to clarify purpose and improve control
- **Code Structure Improvements**:
  - Moved shortcodes into a separate class (`class-shortcodes.php`) for better modularity
  - Consolidated CPT admin modifications into `class-post-mods.php`
  - Updated documentation and usage examples

### Fixed
- Ensured settings changes properly flush rewrite rules

## [1.1.0] - 2025-03-06
### Added
- Google Sheets integration for pricing management
  - Added settings UI for enabling Google Sheets integration for packages, caskets, and urns
  - Implemented visual indicators for Google Sheets managed pricing in the admin
  - Added cloud icon indicators in admin columns for externally managed prices
  - Added admin notices to clearly show when prices are managed externally
- Enhanced UI for settings page
- Allowed additional core blocks in funeral content types
  - Users can now add headings and lists to all custom post types
  - Maintained structured content approach while adding flexibility

### Changed
- Modified block templates to allow specific additional block types
- Updated CPT meta boxes to handle externally managed prices
- Improved REST API support for external content updates
- Enhanced caching system to ensure pricing updates appear immediately
- Updated admin interfaces for better clarity and usability

### Fixed
- Fixed potential cache conflicts when updating content

## [1.0.2] - 2025-03-04
### Added
- New visibility settings for each CPT to control whether they have public-facing pages
- Admin setting page with checkboxes to enable/disable public visibility for each content type
- Filter hooks to allow themes to override CPT visibility settings

### Fixed
- Real-time updates for meta fields in Casket and Urn block editors
- Fixed syntax error in packages.php

## [1.0.1] - 2025-03-01
- **Added:** Default Blocks Importer functionality to automatically add necessary blocks to imported posts via WP All Import Pro
- **Added:** Default blocks are now properly added to posts created via REST API
- **Added:** GitHub Updates integration for seamless plugin updates directly through WordPress admin
- **Fixed:** Template locking issues to ensure proper block rendering while still allowing block additions


## [1.0.0] - 2025-01-15

- Four custom post types: Staff, Caskets, Urns, and Pricing Packages
- Custom taxonomies for categorisation
- Specialised Gutenberg blocks for each post type
- Settings page for enabling/disabling features
- Custom capabilities for admin control
