![HumanKind Funeral Suite](https://weave-hk-github.b-cdn.net/humankind/plugin-header.png)

#  ⚰️ HumanKind Funeral Suite 🎩💐

A powerful WordPress plugin to streamline funeral home websites adding custom post types, taxonomies and fields for Staff, Caskets, Urns, and Pricing Packages, along with specialised Gutenberg blocks for easy content management. Basic meta fields are included, no need for ACF.


## 📝 Description  

HumanKind Funeral Suite transforms WordPress sites for funeral homes, allowing them to manage essential services with ease. This plugin introduces specialised custom post types for:  

- 👥 **Team Members & Staff** – Manage funeral home staff for the website, their roles, contact and multiple locations  
- ⚰️ **Caskets** – Showcase available caskets and pricing in an organised catalog  
- 🏺 **Urns** – Display urn options and pricing for families to choose from  
- 💰 **Pricing Packages** – Present funeral service packages with clear pricing  

Each content type comes with tailor-made fields and taxonomies to fit the needs of the funeral industry.  

## ✨ Features  

**Team Management** – Add and manage staff profiles with location & roles  
**Product Catalogs** – Create searchable listings for caskets and urns  
**Pricing Packages** – Display service packages with clear pricing  
**Feature Flexibility** – Enable or disable specific features as needed  

---
  
- **Gutenberg Blocks for Post Entry**:
  - Team Member block with metadata fields for qualifications, position, contact info, and taxonomies
  - Casket & Urn blocks with price and category fields
  - Pricing Package block with price and order fields
  
- **Admin Features**:
  - Customisable settings page to enable/disable individual CPTs
  - Custom capabilities for role-based access control
  - Automatic updates from GitHub repository
  
- **Developer Features**:
  - Import compatibility with All Import Pro
  - REST API integration for programmatic content creation
  - Block template locking for consistent data entry
  - Shortcode for pricing meta field

## 📈 Google Sheet Sync for Pricing

The HumanKind Funeral Suite now includes a one way sync with Google Sheets for urn, casket and pricing package pricing management. 
This was requested by a client who wanted their Manager/Finance team to be able to quickly adjust pricing without logging into the website.
Also be able to see from a spreadsheet what all the price plans were.

Once setup via a Google Apps Script this allows you to:

- Manage pricing for packages, caskets, and urns from a central Google Sheet
- Automatically sync updates to your WordPress site
- Prevent accidental price edits in the WordPress admin that would be overwritten

#### Features

- **Admin Integration**: Clear visual indicators show when pricing is managed externally
- **Content Protection**: Price fields are automatically disabled when Google Sheets integration is active
- **Flexible Control**: Enable/disable integration independently for each product type
- **Cache Management**: Automatic cache clearing ensures pricing updates appear immediately

#### Configuration

1. Navigate to Settings → HK Funeral Suite
2. In the "Internal Use / Developer Options" section, find the Google Sheets Data Sync options
3. Enable Google Sheets integration for your desired product types
4. Contact Weave Digital Studio for help setting up the Google Sheet connection

#### Developer Notes

When Google Sheets integration is enabled, the plugin adds hooks to:
- Prevent pricing fields from being edited in WordPress
- Display visual indicators in the admin interface
- Support the REST API for external updates
- Clear caches automatically when prices are updatet. Tested for Internal use and used with the Weave Cache Purge Helper.
  
## 🍰 Shortcodes

### `[hk_formatted_price]`
Outputs a formatted price from a custom field.
More info see [Shortcode Usage Examples](shortcode-usage.md)

---
## 📥 Plugin Installation  

### 📌 From WordPress.org (Coming Soon)  
1️⃣ Navigate to **Plugins > Add New** in your WordPress admin panel.  
2️⃣ Search for **"HumanKind Funeral Suite"**.  
3️⃣ Click **Install Now**, then **Activate**.  

### 📌 Manual Installation  
1️⃣ Download the latest `.zip` file from the [Releases Page](https://github.com/HumanKind-nz/hk-funeral-suite/releases).  
2️⃣ Go to **Plugins > Add New > Upload Plugin**.  
3️⃣ Upload the zip file, install, and activate!  

### 📌 From GitHub (For Developers)  
```sh
git clone https://github.com/HumanKind-nz/hk-funeral-suite.git
cd hk-funeral-suite
composer install  # (if Composer is used)
```
💡 Then zip the directory contents and upload it via WordPress admin or copy it to the `wp-content/plugins/` directory manually.  

### 🔄 Auto-Updater via GitHub  
This plugin supports automatic updates directly from GitHub using a custom updater. To ensure updates work:  
1️⃣ Keep the plugin installed in `wp-content/plugins/hk-funeral-suite`.  
2️⃣ When a new release is available, the WordPress updater will notify you.  
3️⃣ Click **Update Now** in the Plugins page to install the latest version.

---

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- A funeral website

> [!IMPORTANT]
> **Block Editor Requirement:**
> This plugin relies on the WordPress block editor (Gutenberg) to manage block meta entries for the custom post types (CPTs). If the block editor is disabled (for example, if the Disable Gutenberg plugin is active), the CPTs may not function as intended. Please ensure that Gutenberg is enabled for full plugin functionality.


## 🚀 Usage 

### 🎯 Getting Started  
1️⃣ After activation, visit the **"HK Funeral Suite Settings"** menu in WordPress Admin.  
2️⃣ Use the **Settings** page to enable or disable specific features.  
3️⃣ Start adding your content under the newly created post types.  

### Adding Staff Members

1. Navigate to "Staff" in the admin menu
2. Click "Add New"
3. Enter the staff member's name as the title
4. Add a featured image for the staff photo
5. Use the Team Member block to enter position, qualifications, contact details, etc.
6. Assign staff to locations and roles using the provided taxonomies
7. Publish when ready

### Managing Caskets or Urns

1. Navigate to "Caskets" or "Urns" in the admin menu
2. Click "Add New"
3. Enter the product name as the title
4. Add a featured image
5. Use the corresponding block to enter pricing and select a category
6. Add description and other content as needed
7. Publish when ready

### Creating Pricing Packages

1. Navigate to "Packages" in the admin menu
2. Click "Add New"
3. Enter the package name as the title
4. Use the Pricing Package block to enter an intro, price and display order
5. Add description and details of what's included in the package
6. Publish when ready


## 🚀  🦫 When using with Beaver Themer

- [ 🦫 Meta Fields & Beaver Builder Integration](beaver-themer-guide.md) - Guide for leveraging custom meta fields in Beaver Themer layouts

## ⬆️ Importing Post Content

The plugin is compatible with WP All Import Pro for bulk importing content. 
When importing:

1. Set up your import as usual, mapping fields to the appropriate columns
2. The plugin should automatically add the required blocks to imported posts
3. After import, review and update any missing metadata as needed

## Developers - UI Optimisation for Popular Themes/Plugins

Streamline the editing interface by removing unnecessary meta boxes
 - Support for cleaning up UI elements individually in the "Developer Options" section of settings page
 - GeneratePress - removes layout options and sections meta boxes
 - Page Builder Framework - removes theme settings meta boxes
 - Happy Files - Removed Featured image column as not required
 **Note:** This is an opinionated list of themes and plugins we use at Weave Digital Studio and HumanKind Funeral websites, but can be extended for other tools

---

## 📂 Plugin Structure  

🗂️ This plugin is designed for modularity and ease of maintenance:  

```
hk-funeral-suite/
├── includes/
│   ├── admin/
│   │   ├── class-settings-page.php     # Plugin settings management
│   │   ├── class-capabilities.php      # Custom capabilities for CPTs
│   │   ├── class-github-updater.php    # GitHub automatic updates
│   │   ├── class-block-editor.php      # Custom block defaults for CPTs
│   │   └── class-post-mods.php         # Post modifications/customizations
│   │   └── class-hk-funeral-cpt-admin-columns.php  # Adjust admin columns, remove SEO columns.
│   │   └── class-hk-funeral-cpt-compatibility.php  # Add theme/plugin compatibility optimsations.
│   ├── blocks/
│   │   ├── assets/                     # Shared block assets
│   │   │   └── block-editor-styles.css # Block editor specific styles
│   │   ├── team-member-block/
│   │   │   ├── init.php                # Team member block registration
│   │   │   └── index.js                # Team member block script
│   │   ├── casket-block/
│   │   │   ├── init.php                # Casket block registration
│   │   │   └── index.js                # Casket block script
│   │   ├── urn-block/
│   │   │   ├── init.php                # Urn block registration
│   │   │   └── index.js                # Urn block script
│   │   ├── pricing-package-block/
│   │   │   ├── init.php                # Pricing package block registration
│   │   │   └── index.js                # Pricing package block script
│   │   └── block-styles.php            # Shared block styling
│   ├── cpt/
│   │   ├── staff.php                   # Staff CPT registration
│   │   ├── caskets.php                 # Caskets CPT registration
│   │   ├── urns.php                    # Urns CPT registration
│   │   └── packages.php                # Packages CPT registration
│   ├── import/
│   │   └── class-default-blocks-importer.php  # Import integration
│   └── class-shortcodes.php            # Shortcode registration and handling
├── assets/
│   └── images/                        # Main plugin images
│       └── hk-funeral-suite-banner.png                     
│       └── icon-256x256.png                    
├── README.md                          # Main documentation
├── CHANGELOG.md                       # Version history
├── beaver-themer-guide.md            # Beaver Themer integration guide
├── LICENSE                           # License file
└── hk-funeral-suite.php              # Main plugin file
```

## 🔄 Changelog
[ Full Changelog Here](CHANGELOG.md)

### [1.2.3] - 2025-03-14
- **Added** Theme & Plugin UI optimisation settings to remove unnecessary meta boxes from funeral content types
- **Added** Support for cleaning up UI elements from GeneratePress, Page Builder Framework and Happy Files
- **Renamed** Admin classes for better consistency in the codebase

### [1.2.0] - 2025-03-13
- **Added** block protection to prevent accidental deletion of required data fields in all custom post types
- **Fixed** synchronization between block editor and meta fields for intro paragraphs
- **Removed** redundant intro meta box from editor screen
- **Added** SEO column management to hide SEO columns when CPTs are set to non-public
- Centralised featured image column handling across all custom post types
- Renamed "Title" column to "Name" in admin lists for better clarity

### [1.1.7] - 2025-03-12
- **Performance:** Added autosave and revision checks to prevent unnecessary processing for cpt updates

### [1.1.4] - 2025-03-09
- **Added** New shortcode [hk_formatted_price] for outputting formatted prices.
- Consolidated CPT admin modifications into class-post-mods.php
- Updated Github auto plugin updater.
- Ensured settings changes properly flush rewrite rules
- Updated documentation

### [1.1.0] - 2025-03-06
- **Added:** Google Sheets integration for pricing management
- **Added:** Enhanced UI for settings page
- **Added:** Allowed additional core blocks in funeral content types

### [1.0.2] - 2025-03-04
- **Added:** New visibility settings for each CPT to control whether they have public-facing pages
- **Added:** Admin setting page with checkboxes to enable/disable public visibility for each content type
- **Added:** Filter hooks to allow themes to override CPT visibility settings

### 1.0.1 - 2025-03-02
- **Added:** Default Blocks Importer functionality to automatically add necessary blocks to imported posts via WP All Import Pro
- **Added:** Default blocks are now properly added to posts created via REST API
- **Added:** GitHub Updates integration for seamless plugin updates directly through WordPress admin
- **Fixed:** Template locking issues to ensure proper block rendering while still allowing block additions

### 1.0.0 (Initial Release)
- Four custom post types: Staff, Caskets, Urns, and Pricing Packages
- Custom taxonomies for categorisation
- Specialised Gutenberg blocks for each post type
- Settings page for enabling/disabling features
- Custom capabilities for admin control


## 🎖️ Credits  

👨‍💻 Developed with ❤️ by [HumanKind](https://weave.co.nz), Weave Digital Studio, and Gareth Bissland.  


## Support

For support, feature requests or bug reports, please use the [GitHub issue tracker](https://github.com/HumanKind-nz/hk-funeral-suite/issues).

## 📜 License  

🔓 This plugin is licensed under **GPL v2 or later** – feel free to modify and improve!  
