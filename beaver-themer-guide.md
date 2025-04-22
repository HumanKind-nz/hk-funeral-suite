# HK Funeral Suite: Beaver Builder Integration

This document provides information on how to integrate HK Funeral Suite custom post types and meta fields with Beaver Themer for your layouts.

## Recommended Price Formatting

For displaying prices in Beaver Builder, we recommend using the `[hk_formatted_price]` shortcode instead of directly accessing meta fields. This shortcode provides better formatting control and consistent output. See the [shortcode-usage.md](shortcode-usage.md) documentation for complete details on the `[hk_formatted_price]` shortcode.

Example of using the price shortcode within Beaver Builder:

```html
<div class="package-pricing">
    <strong>Package Price:</strong> [hk_formatted_price key="_hk_fs_package_price" prefix="From" suffix="inc GST"]
</div>
```

## Staff Custom Post Type

```
hk_fs_staff
```

### Staff Beaver Builder Integration
When using field connections in Beaver Builder Themer, you'll always use the original meta field names regardless of UI labels:

| Field | Meta Key for Beaver Themer |
|-------|----------------------------|
| Position | `_hk_fs_staff_position` |
| Qualification | `_hk_fs_staff_qualification` |
| Phone | `_hk_fs_staff_phone` |
| Email | `_hk_fs_staff_email` |

### Staff Shortcodes
For Beaver Themer layouts, you can use these shortcodes to display team member fields:
```
[hk_custom_field key="_hk_fs_staff_position"]
[hk_custom_field key="_hk_fs_staff_qualification"]
[hk_custom_field key="_hk_fs_staff_phone"]
[hk_custom_field key="_hk_fs_staff_email"]
```

You can also add formatting around these fields:
```
<div class="staff-position">
    <strong>Position:</strong> [hk_custom_field key="_hk_fs_staff_position"]
</div>
<div class="staff-qualifications">
    <strong>Qualifications:</strong> [hk_custom_field key="_hk_fs_staff_qualification"]
</div>
<div class="staff-contact">
    <strong>Email:</strong> <a href="mailto:[hk_custom_field key="_hk_fs_staff_email"]">[hk_custom_field key="_hk_fs_staff_email"]</a>
    <strong>Phone:</strong> <a href="tel:[hk_custom_field key="_hk_fs_staff_phone"]">[hk_custom_field key="_hk_fs_staff_phone"]</a>
</div>
```

### Staff Taxonomies
You can also use these taxonomies in Beaver Themer:
```
[wpbb term:hk_fs_location]
[wpbb term:hk_fs_role]
```

## Urns Custom Post Type

```
hk_fs_urn
```

### Urn Beaver Builder Integration
When using field connections in Beaver Builder Themer, you'll always use the original meta field names regardless of UI labels:

| Field | Meta Key for Beaver Themer |
|-------|----------------------------|
| Price | `_hk_fs_urn_price` |

### Urn Shortcodes
For Beaver Themer layouts, you can display urn prices using the `[hk_formatted_price]` shortcode (recommended):
```
[hk_formatted_price key="_hk_fs_urn_price" prefix="Price:" suffix="inc GST"]
```

Or using the basic custom field shortcode:
```
[hk_custom_field key="_hk_fs_urn_price"]
```

### Urn Taxonomies
You can also use the urn category taxonomy in Beaver Themer:
```
[wpbb term:hk_fs_urn_category]
```

## Pricing Packages Custom Post Type

```
hk_fs_package
```

### Pricing Packages Beaver Builder Integration
When using field connections in Beaver Builder Themer, you'll always use the original meta field names regardless of UI labels:

| Field | Meta Key for Beaver Themer |
|-------|----------------------------|
| Intro | `_hk_fs_package_intro` |
| Price | `_hk_fs_package_price` |
| Display Order | `_hk_fs_package_order` |

### Packages Shortcodes
For Beaver Themer layouts, you can use these shortcodes to display package fields:
```
[hk_custom_field key="_hk_fs_package_intro"]
[hk_formatted_price key="_hk_fs_package_price"]
[hk_custom_field key="_hk_fs_package_order"]
```

You can also add formatting around these fields:
```
<div class="package-intro">
    [hk_custom_field key="_hk_fs_package_intro"]
</div>
<div class="package-pricing">
    <strong>Package Price:</strong> [hk_formatted_price key="_hk_fs_package_price" suffix="inc GST"]
</div>
```

### Conditional Display Example for Intro
Only show the intro section if the field has content:

```
[wpbb-if exists="post:custom_field" key='_hk_fs_package_intro']
    <div class="package-intro">
        [hk_custom_field key="_hk_fs_package_intro"]
    </div>
[/wpbb-if]
```

## Caskets Custom Post Type

```
hk_fs_casket
```

### Casket Beaver Builder Integration
When using field connections in Beaver Builder Themer, you'll always use the original meta field names regardless of UI labels:

| Field | Meta Key for Beaver Themer |
|-------|----------------------------|
| Price | `_hk_fs_casket_price` |

### Casket Shortcodes
For Beaver Themer layouts, you can display casket prices using the `[hk_formatted_price]` shortcode (recommended):
```
[hk_formatted_price key="_hk_fs_casket_price" prefix="Price:" suffix="inc GST"]
```

Or using the basic custom field shortcode:
```
[hk_custom_field key="_hk_fs_casket_price"]
```

### Casket Taxonomies
You can also use the casket category taxonomy in Beaver Themer:
```
[wpbb term:hk_fs_casket_category]
```

## Advanced Shortcode Examples

### Price Formatting with HK Shortcode (Recommended)
Format prices with proper currency and formatting options:
```
<div class="price-display">
    [hk_formatted_price key="_hk_fs_casket_price" prefix="Price:" suffix="inc GST" decimals="2"]
</div>
```

### Custom Field with Default Value
Display a field with a fallback value if empty:
```
[hk_custom_field key="_hk_fs_package_intro" default="No description available"]
```

### Custom Field with Raw Output
Display a field without HTML formatting:
```
[hk_custom_field key="_hk_fs_package_intro" format="raw"]
```

### Conditional Display Example
Only show elements if a field has content:
```
[wpbb-if exists="post:custom_field" key='_hk_fs_staff_qualification']
    <div class="qualification">
        <strong>Qualifications:</strong> [hk_custom_field key="_hk_fs_staff_qualification"]
    </div>
[/wpbb-if]
```

## Combining HK Shortcodes with Beaver Themer

You can combine the power of both shortcode systems. For example, to display a formatted price while referencing a specific post ID:

```
<div class="product-item">
    <h3>[wpbb post:title]</h3>
    <div class="price">
        [hk_formatted_price key="_hk_fs_casket_price" post_id="[wpbb post:id]" prefix="Price:" suffix="inc GST"]
    </div>
</div>
```

For more details on the `[hk_formatted_price]` shortcode and its options, refer to the [shortcode-usage.md](shortcode-usage.md) documentation.
