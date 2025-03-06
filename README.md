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

---

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- A funeral website


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

---

## 📂 Plugin Structure  

🗂️ This plugin is designed for modularity and ease of maintenance:  

```
hk-funeral-suite/
├── includes/
│   ├── admin/
│   │   ├── class-settings-page.php     # Plugin settings management
│   │   ├── class-capabilities.php      # Custom capabilities for CPTs
│   │   └── class-github-updater.php    # GitHub automatic updates
│   │   └── class-block-editor.php      # Custom block defaults for CPTs
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
│   └── import/
│       └── class-default-blocks-importer.php  # Import integration
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
