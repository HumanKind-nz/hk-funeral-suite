# HK Funeral Suite: Beaver Builder Integration

This document provides information on how to integrate HK Funeral Suite custom post types and meta fields with Beaver Themer for your layouts.

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

### Staff Beaver Themer Shortcodes
For Beaver Themer layouts, you can use these shortcodes to display team member fields:
```
[wpbb post:custom_field key='_hk_fs_staff_position']
[wpbb post:custom_field key='_hk_fs_staff_qualification']
[wpbb post:custom_field key='_hk_fs_staff_phone'] 
[wpbb post:custom_field key='_hk_fs_staff_email']
```

You can also add formatting around these fields:
```
<div class="staff-position">
    <strong>Position:</strong> [wpbb post:custom_field key='_hk_fs_staff_position']
</div>
<div class="staff-qualifications">
    <strong>Qualifications:</strong> [wpbb post:custom_field key='_hk_fs_staff_qualification']
</div>
<div class="staff-contact">
    <strong>Email:</strong> <a href="mailto:[wpbb post:custom_field key='_hk_fs_staff_email']">[wpbb post:custom_field key='_hk_fs_staff_email']</a>
    <strong>Phone:</strong> <a href="tel:[wpbb post:custom_field key='_hk_fs_staff_phone']">[wpbb post:custom_field key='_hk_fs_staff_phone']</a>
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

### Urn Beaver Themer Shortcodes
For Beaver Themer layouts, you can use these shortcodes to display urn fields:
```
[wpbb post:custom_field key='_hk_fs_urn_price']
```

You can also add formatting around these fields:
```
<div class="urn-price">
    <strong>Price:</strong> $[wpbb post:custom_field key='_hk_fs_urn_price']
</div>
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

### Packages Beaver Themer Shortcodes
For Beaver Themer layouts, you can use these shortcodes to display package fields:
```
[wpbb post:custom_field key='_hk_fs_package_intro']
[wpbb post:custom_field key='_hk_fs_package_price']
[wpbb post:custom_field key='_hk_fs_package_order']
```

You can also add formatting around these fields:
```
<div class="package-intro">
    [wpbb post:custom_field key='_hk_fs_package_intro']
</div>
<div class="package-pricing">
    <strong>Package Price:</strong> $[wpbb post:custom_field key='_hk_fs_package_price']
</div>
```

### Conditional Display Example for Intro
Only show the intro section if the field has content:

```
[wpbb if:post:custom_field key='_hk_fs_package_intro']
    <div class="package-intro">
        [wpbb post:custom_field key='_hk_fs_package_intro']
    </div>
[/wpbb]
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

### Casket Beaver Themer Shortcodes
For Beaver Themer layouts, you can use these shortcodes to display casket fields:
```
[wpbb post:custom_field key='_hk_fs_casket_price']
```

You can also add formatting around these fields:
```
<div class="casket-price">
    <strong>Price:</strong> $[wpbb post:custom_field key='_hk_fs_casket_price']
</div>
```

### Casket Taxonomies
You can also use the casket category taxonomy in Beaver Themer:
```
[wpbb term:hk_fs_casket_category]
```

## Advanced Beaver Themer Casket Examples

### Casket Price Formatting Example
Format prices with commas for thousands:
```
<div class="price-display">
    $[wpbb post:custom_field key='_hk_fs_casket_price' format='number' thousands_sep=',' decimals='2']
</div>
```

### Conditional Display Example
Only show elements if a field has content:
```
[wpbb if:post:custom_field key='_hk_fs_staff_qualification']
    <div class="qualification">
        <strong>Qualifications:</strong> [wpbb post:custom_field key='_hk_fs_staff_qualification']
    </div>
[/wpbb]
```

### Looping Through Staff by Location
To display all staff members from a specific location:
```
[wpbb-if exists="archive-term:hk_fs_location"]
    <h2>Staff at [wpbb post:terms_list taxonomy='hk_fs_location' separator=', ']</h2>
    [wpbb post:loop]
        <div class="staff-member">
            <h3>[wpbb post:title]</h3>
            <p>[wpbb post:custom_field key='_hk_fs_staff_position']</p>
        </div>
    [/wpbb post:loop]
[/wpbb-if]
```
