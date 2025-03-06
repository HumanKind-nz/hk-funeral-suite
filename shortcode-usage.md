# HK Funeral Suite Shortcodes Usage

---

# hk_formatted_price

This document explains how to use the `[hk_formatted_price]` shortcode provided by the HK Funeral Suite plugin.

**Note:** If the price meta field contains a non-numeric value (for example, "P.O.A"), the shortcode will simply output that value without formatting, but it will add a **text suffix** if specified.

## Shortcode Attributes

- **key** (required): The meta key from which the price is retrieved.  
  Example: `_hk_fs_urn_price`, `_hk_fs_casket_price`, or `_hk_fs_package_price`
- **symbol** (optional): The currency symbol. Default is `$`.
- **prefix** (optional): A string that will be displayed before the formatted price.
- **post_id** (optional): The post ID to query for the pricing field. If used on another page.
- **suffix** (optional): A string that will be displayed after the formatted price for numeric fields.
- **text_suffix** (optional): A suffix to append when the meta value is a non-numeric string.
- **decimals** (optional): The number of decimal places to display. Default is `2`. Set to `0` if you don't want any decimals.

## Basic Usage

Display a formatted price using the default settings:
```html
[hk_formatted_price key="_hk_fs_package_price"]
```
This outputs, for example, `$2,000.00` if the meta value is numeric.

## Customising the Currency Symbol

Use a different currency symbol by passing the `symbol` attribute:
```html
[hk_formatted_price key="_hk_fs_package_price" symbol="€"]
```
This might output: `€2,000.00`

## Adding a Prefix or Suffix

You can add text before or after the price (for numeric values):
```html
[hk_formatted_price key="_hk_fs_package_price" prefix="From" suffix="inc gst"]
```
This might output: `From <span class="hk-item-price">$2,000.00</span> inc gst`  
*(Note: Only the price is wrapped in a span for styling.)*

## Changing Decimal Places

Control the number of decimal places with the `decimals` attribute. For example, to show no decimals:
```html
[hk_formatted_price key="_hk_fs_package_price" decimals="0"]
```
This might output: `<span class="hk-item-price">$2,000</span>`

## Example for Non-Numeric Value with Text Suffix

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

## Fetch Price from a Specific Post

```html
[hk_formatted_price key="_hk_fs_package_price" post_id="123"]
```

This retrieves _hk_fs_package_price from post ID 123, no matter where it’s used.


## Combined Example

A fully customised example:
```html
[hk_formatted_price key="_hk_fs_package_price" symbol="£" prefix="Starting at" suffix="plus VAT" decimals="2"]
```
This might output:
```html
<span class="hk-item-price-container">
	Starting at <span class="hk-item-price">£2,000.00</span> plus VAT
</span>
```

---

# Output HTML Structure of `[hk_formatted_price]` Shortcode

When the shortcode outputs a numeric price value, it generates HTML similar to the following:
```html
<span class="hk-item-price-container">
	From <span class="hk-item-price">$2,000.00</span> inc gst
</span>
```

## Explanation

- **.hk-item-price-container**  
  The outer container that wraps the entire pricing output. Use this class for styling the overall block (e.g., margins, background, etc.).

- **.hk-item-price**  
  This inner span wraps only the formatted price (which combines the currency symbol and the numeric value). This allows you to specifically style the price display.

*Note:* Both the prefix and suffix (or text suffix for non-numeric values) are output directly (without additional span wrappers) to simplify the HTML structure.

---

Use these examples as a reference when integrating the `[hk_formatted_price]` shortcode into your Beaver Builder templates or anywhere else on your site.
