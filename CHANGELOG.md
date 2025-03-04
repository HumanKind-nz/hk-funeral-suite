# Changelog

All notable changes to the HumanKind Funeral Suite plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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