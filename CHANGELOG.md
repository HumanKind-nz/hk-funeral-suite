# Changelog

All notable changes to the HumanKind Funeral Suite plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.4.7 - 2024-04-22

- **Added** new `[hk_custom_field]` shortcode for reliable custom field display in Beaver Builder
- **Updated** Beaver Themer guide with new shortcode examples and best practices to docs

## 1.4.6 - 2024-04-18 - Keepsakes

- **Added** Support for the Keepsakes CPT in the Block Editor
- **Added** Core button blocks (`core/button` and `core/buttons`) to all CPTs
- **Fixed** issue with slow post saving due to inefficient cache purging. Overall NGINX page cache improvements
- **Fixed** duplicate code in pricing package block save handler

## [1.4.5] - 2025-04-09
- **Added** `decimals="0"` parameter to shortcodes in the package admin column

## [1.4.4] - 2025-04-09
- **Added** Shortcode added to the pricing packages admin for easier content embedding
- **Fixed** useEffect hook issues in Gutenberg blocks to properly sync metadata
- **Fixed** Google Sheets integration edge cases in price management system

## [1.4.1] - 2025-04-08
- **Added** Enhanced cache clearing for REST API meta updates
- **Improved** Compatibility with external data sources and page builders

## [1.4.0] - 2025-04-07
- **Added** Keepsakes custom post type for showcasing keepsakes
- **Added** Custom meta fields for keepsakes including product code, metal type, and stones
- **Added** Keepsake block with specialized fields for easy content management
- **Added** Cache clearing for REST API meta updates to improve compatibility with page builders
- **Improved** REST API support for keepsake meta fields

## [1.3.0] - 2025-03-23
- **Added** Monuments custom post type for showcasing monuments and headstones
- **Added** Moved to shared CPT registration code for simplicity and easier extra CPTs
- **Added** Custom user roles for Funeral Staff and Funeral Manager for permissions and management
- **Improved** Centralised product registration with hooks for plugin extensibility

## [1.2.4] - 2025-03-15
- **Added** Support for hiding SEOPress metaboxes on CPTs and UI cleanup

## [1.2.3] - 2025-03-14
- **Added** Theme & Plugin UI optimisation settings to remove unnecessary meta boxes from funeral content types
- **Added** Support for cleaning up UI elements from GeneratePress, Page Builder Framework and Happy Files
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
