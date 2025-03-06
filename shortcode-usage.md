# HK Funeral Suite Shortcodes Usage

---

# hk_formatted_price

This document explains how to use the `[hk_formatted_price]` shortcode provided by the HK Funeral Suite plugin.

**Note:** If the price meta field contains a non-numeric value (for example, "P.O.A"), the shortcode will simply output that value without formatting


## Shortcode Attributes

- **key** (required): The meta key from which the price is retrieved.
  `_hk_fs_urn_price` or `_hk_fs_casket_price` or `_hk_fs_package_price`
- **symbol** (optional): The currency symbol. Default is `$`.
- **prefix** (optional): A string that will be displayed before the formatted price.
- **suffix** (optional): A string that will be displayed after the formatted price.
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

You can add text before or after the price:
```html
[hk_formatted_price key="_hk_fs_package_price" prefix="From" suffix="inc gst"]
```
This might output: `From $2,000.00 inc gst`

## Changing Decimal Places

Control the number of decimal places with the `decimals` attribute. For example, to show no decimals:
```html
[hk_formatted_price key="_hk_fs_package_price" decimals="0"]
```
This might output: `$2,000`

## Combined Example

A fully customized example:
```html
[hk_formatted_price key="_hk_fs_package_price" symbol="£" prefix="Starting at" suffix="plus VAT" decimals="2"]
```
This might output: `Starting at £2,000.00 plus VAT`

## Non-numeric Values

If the meta field contains a non-numeric value (for example, "P.O.A"), the shortcode will simply output that value without formatting:
```html
[hk_formatted_price key="_hk_fs_package_price"]
```
If the stored value is "P.O.A", the output will be:
```
P.O.A
```

---

# Output HTML Structure of `[hk_formatted_price]` Shortcode

When the shortcode outputs a numeric price value, it generates a block of HTML with multiple spans to allow for easy styling. The structure is as follows:

```html
<span class="hk-price-container">
	<span class="hk-price-prefix">From</span>
	<span class="hk-price">$2,000.00</span>
	<span class="hk-price-suffix">inc gst</span>
</span>
```

## Explanation

- **.hk-price-container**  
  This outer span wraps the entire pricing output. It serves as the main container, so you can style the whole block (for example, set margins or apply background colours).

- **.hk-price-prefix**  
  This span holds any prefix text you provide via the shortcode (for example, "From"). It is optional and will only appear if a prefix attribute is specified.

- **.hk-price**  
  This is the primary span that displays the formatted price. It combines the currency symbol and the numeric value (formatted with the specified number of decimals).

- **.hk-price-suffix**  
  This span displays any suffix text (such as "inc gst"). Like the prefix, it will only appear if a suffix attribute is provided.

## Example Usage

For the shortcode:
```html
[hk_formatted_price key="_hk_fs_package_price" symbol="$" prefix="From" suffix="inc gst" decimals="2"]
```

Assuming the meta value for `_hk_fs_package_price` is `2000`, the rendered HTML will be:
```html
<span class="hk-price-container">
	<span class="hk-price-prefix">From</span>
	<span class="hk-price">$2,000.00</span>
	<span class="hk-price-suffix">inc gst</span>
</span>
```

This structure gives you fine-grained control over each component of the price display via CSS.

---
Use these examples as a reference when integrating the `[hk_formatted_price]` shortcode into your Beaver Builder templates or anywhere else on your site.
