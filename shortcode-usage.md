# HK Funeral Suite Shortcodes Usage

This document explains how to use the shortcodes provided by the HK Funeral Suite plugin.

## hk_formatted_price

The `[hk_formatted_price]` shortcode provides a standardized way to display price values with proper formatting throughout your site, including within Beaver Builder templates.

**Note:** If the price meta field contains a non-numeric value (for example, "P.O.A"), the shortcode will simply output that value without formatting, but it will add a **text suffix** if specified.

### Shortcode Attributes

- **key** (required): The meta key from which the price is retrieved.  
  Example: `_hk_fs_urn_price`, `_hk_fs_casket_price`, or `_hk_fs_package_price`
- **symbol** (optional): The currency symbol. Default is `$`.
- **prefix** (optional): A string that will be displayed before the formatted price.
- **post_id** (optional): The post ID to query for the pricing field. If used on another page.
- **suffix** (optional): A string that will be displayed after the formatted price for numeric fields.
- **text_suffix** (optional): A suffix to append when the meta value is a non-numeric string.
- **decimals** (optional): The number of decimal places to display. Default is `0`. Set to `2` if you want to show cents.

### Basic Usage

Display a formatted price using the default settings:
```html
[hk_formatted_price key="_hk_fs_package_price"]
```
This outputs, for example, `$2,000` if the meta value is numeric.

### Customising the Currency Symbol

Use a different currency symbol by passing the `symbol` attribute:
```html
[hk_formatted_price key="_hk_fs_package_price" symbol="€"]
```
This might output: `€2,000`

### Adding a Prefix or Suffix

You can add text before or after the price (for numeric values):
```html
[hk_formatted_price key="_hk_fs_package_price" prefix="From" suffix="inc gst"]
```
This might output: `From <span class="hk-item-price">$2,000</span> inc gst`  
*(Note: Only the price is wrapped in a span for styling.)*

### Adding Decimal Places

By default, prices show no decimal places. To display cents, use the `decimals` attribute:
```html
[hk_formatted_price key="_hk_fs_package_price" decimals="2"]
```
This might output: `<span class="hk-item-price">$2,000.00</span>`

### Example for Non-Numeric Value with Text Suffix

When the meta field contains a non-numeric value, you can add a suffix using the `text_suffix` attribute:
```html
[hk_formatted_price key="_hk_fs_package_price" text_suffix="(price on application)"]
```
If the stored value is "P.O.A", the output will be:
```html
<span class="hk-item-price-container">
    <span class="hk-item-price">P.O.A</span> (price on application)
</span>
```

### Fetch Price from a Specific Post

```html
[hk_formatted_price key="_hk_fs_package_price" post_id="123"]
```

This retrieves _hk_fs_package_price from post ID 123, no matter where it's used.

### Combined Example

A fully customised example with decimals:
```html
[hk_formatted_price key="_hk_fs_package_price" symbol="£" prefix="Starting at" suffix="plus VAT" decimals="2"]
```
This might output:
```html
<span class="hk-item-price-container">
    Starting at <span class="hk-item-price">£2,000.00</span> plus VAT
</span>
```

## Output HTML Structure of `[hk_formatted_price]` Shortcode

When the shortcode outputs a numeric price value, it generates HTML similar to the following:
```html
<span class="hk-item-price-container">
    From <span class="hk-item-price">$2,000</span> inc gst
</span>
```

### Explanation

- **.hk-item-price-container**  
  The outer container that wraps the entire pricing output. Use this class for styling the overall block (e.g., margins, background, etc.).

- **.hk-item-price**  
  This inner span wraps only the formatted price (which combines the currency symbol and the numeric value). This allows you to specifically style the price display.

*Note:* Both the prefix and suffix (or text suffix for non-numeric values) are output directly (without additional span wrappers) to simplify the HTML structure.

## hk_custom_field

The `[hk_custom_field]` shortcode provides a flexible way to display any custom field value with optional formatting, wrappers, and fallbacks. It's particularly useful with Beaver Builder for consistent display of meta fields.

### Shortcode Attributes

- **key** (required): The meta key to retrieve.  
  Example: `_hk_fs_staff_position`, `_hk_fs_casket_product_code`, etc.
- **post_id** (optional): Specific post ID to fetch the meta value from (defaults to current post).
- **format** (optional): Date format string for date values.
- **before** (optional): Content to display before the value (only if value exists).
- **after** (optional): Content to display after the value (only if value exists).
- **fallback** (optional): Content to display if the custom field is empty.
- **raw** (optional): Set to "true" to return raw value without wrapper spans (default: false).
- **strip_tags** (optional): Set to "true" to strip HTML tags (default: false).

### Basic Usage

Display a custom field value using the default settings:
```html
[hk_custom_field key="_hk_fs_staff_position"]
```
This outputs the value of the staff position meta field for the current post.

### Adding Content Before and After the Value

```html
[hk_custom_field key="_hk_fs_staff_position" before="Position: " after=" (Staff Member)"]
```
This might output: 
```html
<span class="hk-custom-field-container">
    <span class="hk-custom-field-before">Position: </span>
    <span class="hk-custom-field-value">Funeral Director</span>
    <span class="hk-custom-field-after"> (Staff Member)</span>
</span>
```

### Providing a Fallback for Empty Fields

```html
[hk_custom_field key="_hk_fs_staff_qualification" fallback="No qualifications listed"]
```
If the qualification field is empty, this will display "No qualifications listed" instead.

### Date Formatting

Format date values stored in custom fields:
```html
[hk_custom_field key="_hk_fs_event_date" format="F j, Y"]
```
If the field contains "2024-04-15", this would output "April 15, 2024".

### Displaying Raw Values Without Wrapper Spans

For times when you need just the value without any HTML structure:
```html
[hk_custom_field key="_hk_fs_staff_email" raw="true"]
```
This outputs just the email address without any wrapping spans.

### Stripping HTML Tags

For security or display purposes, strip any HTML tags from the value:
```html
[hk_custom_field key="_hk_fs_casket_description" strip_tags="true"]
```

### Fetch Value from a Specific Post

```html
[hk_custom_field key="_hk_fs_package_intro" post_id="123"]
```
This retrieves the package intro from post ID 123, regardless of where the shortcode is used.

### Combined Example

A comprehensive example showing multiple attributes:
```html
[hk_custom_field key="_hk_fs_staff_phone" before="Call: " fallback="No phone number available" raw="true"]
```

## Output HTML Structure of `[hk_custom_field]` Shortcode

When the shortcode outputs a custom field value with default settings, it generates HTML similar to:

```html
<span class="hk-custom-field-container">
    <span class="hk-custom-field-before">Position: </span>
    <span class="hk-custom-field-value">Funeral Director</span>
    <span class="hk-custom-field-after"> (Staff Member)</span>
</span>
```

### Explanation

- **.hk-custom-field-container**  
  The outer container that wraps the entire output.

- **.hk-custom-field-before**  
  Wraps the "before" content if specified.

- **.hk-custom-field-value**  
  Wraps the actual custom field value.

- **.hk-custom-field-after**  
  Wraps the "after" content if specified.

*Note:* When `raw="true"` is used, the value is returned without any HTML wrappers.

## Using with Beaver Builder

Both shortcodes can be used directly within Beaver Builder modules or Beaver Themer templates.

### Basic Usage in Beaver Builder HTML Module

```html
<div class="staff-details">
    <h3>[wpbb post:title]</h3>
    <div class="position">
        [hk_custom_field key="_hk_fs_staff_position"]
    </div>
    <div class="contact">
        Email: <a href="mailto:[hk_custom_field key="_hk_fs_staff_email" raw="true"]">
            [hk_custom_field key="_hk_fs_staff_email" raw="true"]
        </a>
    </div>
</div>
```

### Using with Beaver Themer Field Connections

Combine with Beaver Themer's conditional tags:

```html
[wpbb-if exists="post:custom_field" key="_hk_fs_staff_qualification"]
    <div class="qualifications">
        <strong>Qualifications:</strong> 
        [hk_custom_field key="_hk_fs_staff_qualification"]
    </div>
[/wpbb-if]
```

### Product Display Example

```html
<div class="product-card">
    <h3>[wpbb post:title]</h3>
    <div class="product-intro">
        [hk_custom_field key="_hk_fs_package_intro" fallback="No description available"]
    </div>
    <div class="product-price">
        [hk_formatted_price key="_hk_fs_package_price" prefix="From" suffix="inc GST"]
    </div>
</div>
```

For more information on integrating with Beaver Builder, see the [beaver-themer-guide.md](beaver-themer-guide.md) documentation.
