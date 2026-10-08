# Block Theme Guide

How to show caskets, urns, monuments, keepsakes and team members on a block theme. Everything here is opt-in. Sites built with Beaver Builder and the existing shortcodes behave exactly as before.

## Pick the right tool

| You want | Use |
|----------|-----|
| A product range, grouped by category, in catalogue order, with prices | The **Product Grid** block |
| The same grid in Beaver Builder or a classic theme | The `[hk_product_grid]` shortcode |
| Cards built from core blocks that the theme styles itself | A **Query Loop variation**, such as "Caskets (HK)" |
| A price or field inside any core block in a Query Loop | The **block bindings sources** |

The grid block is the quickest route and needs no theme work. Reach for the Query Loop when the design calls for a card layout the grid cannot give you.

## Why core blocks alone do not work

HK post types are deliberately not public: products have no single pages or archives. Two core rules follow from that.

1. **The Query Loop drops the post type.** Core keeps a Query block's post type only when it is publicly viewable. Otherwise it quietly queries blog posts. The editor's post type picker hides HK types for the same reason.
2. **`core/post-meta` shows visitors nothing.** It refuses protected keys (every HK key starts with `_hk_fs_`), and it returns nothing for a post that is not publicly viewable.

The plugin works around both without making anything public. Do not switch on "public" for a product type to get a Query Loop working. It gives every product a URL you then have to manage.

## Product Grid block

Find it in the inserter under **HK Funeral Suite**, or search "Product Grid". The editor preview is the server's own output, so what you see is what visitors get.

### Settings

| Block setting | Shortcode attribute | Default | Notes |
|---------------|---------------------|---------|-------|
| Product type | `type` | `casket` | `casket`, `urn`, `monument` or `keepsake`. Only enabled types are offered. |
| Categories | `categories` | all | Category slugs. Comma-separated in the shortcode. |
| Group by category | `group_by_category` | on | One heading and list per category. |
| Order | `order_by` | `catalogue` | `catalogue`, `price` or `title`. See below. |
| Columns | `columns` | `3` | The most columns on wide screens (1 to 6). Fewer show on small screens. |
| Show image | `show_image` | on | The featured image. |
| Image size | `image_size` | `medium_large` | Any registered size. |
| Show price | `show_price` | on | |
| Text before the price | `price_prefix` | none | For example "From". Numeric prices only. |
| Text after the price | `price_suffix` | `incl. GST` | Numeric prices only. |
| POA label | `poa_label` | "Price on application" | Shown for a `POA` price. Leave blank for the default. |
| Description | `show_description` | `full` | `full`, `excerpt` or `none`. |
| Product name heading level | `heading_level` | `3` | Category headings sit one level above. |

Shortcode booleans accept `true`/`false`, `yes`/`no` or `1`/`0`.

```
[hk_product_grid type="urn" categories="ceramic,timber" order_by="price" price_prefix="From" show_description="none"]
```

### Order

- **Catalogue** (default): `menu_order`, then name. The master catalogue writes `menu_order` as a rank by category, then price, with POA products last, so this is the catalogue's display order. On sites not using the catalogue every product is 0, and the grid falls back to name order.
- **Price**: numeric prices lowest first, then other text (such as a legacy "P.O.A."), then POA, then products with no price.
- **Title**: name, A to Z.

Category groups appear in the order their first product appears in catalogue order. When that is a tie (every product at 0), groups are ordered by category name. Products without a category go last under "Other".

### Prices

| Stored value | Shown as |
|--------------|----------|
| `3595` | `$3,595 incl. GST` (with the prefix and suffix) |
| `POA` | The POA label, without prefix or suffix |
| Any other text, such as `P.O.A.` | The text, unchanged |
| Empty | No price line |

### Markup

```html
<section class="wp-block-hk-funeral-suite-product-grid hk-product-grid hk-product-grid--casket">
  <h2 class="hk-product-grid__category">Solid Timber</h2>
  <ul class="hk-product-grid__items" role="list" style="--hk-columns:3">
    <li class="hk-product-card" id="hk-product-123">
      <figure class="hk-product-card__media"><img class="hk-product-card__image" alt="…" …></figure>
      <h3 class="hk-product-card__title">Kauri Classic</h3>
      <p class="hk-product-card__price hk-product-card__price--numeric">
        <span class="hk-item-price-container"><span class="hk-item-price">$3,595</span> incl. GST</span>
      </p>
      <div class="hk-product-card__description">…</div>
    </li>
  </ul>
</section>
```

- The price uses the same `hk-item-price` classes as `[hk_formatted_price]`, so existing price CSS carries over.
- The price paragraph also carries its state: `--numeric`, `--poa` or `--text`.
- Each card has a stable `hk-product-{ID}` anchor, given once per page.
- Images come from `wp_get_attachment_image()` with `srcset`. The alt text falls back to the product name. The first row loads straight away and the rest lazy-load.
- The description is the product's content with the HK details block removed, the same as `[hk_content]`.

### Styling

The block ships a small stylesheet that loads only on pages that show a grid. It uses your theme.json presets where they exist. Adjust it with custom properties rather than overriding the grid:

| Property | Default | Sets |
|----------|---------|------|
| `--hk-columns` | Columns setting | Most columns |
| `--hk-product-grid-gap` | Block spacing, or `1.5rem` | Gap between cards |
| `--hk-product-grid-min` | `14rem` | Narrowest card before the grid drops a column |

The block also supports wide and full alignment, margin, padding, block spacing, text and background colour, and font size.

## Query Loop variations

Search the inserter for "Caskets (HK)" or "Urns (HK)". There is one per enabled product type. Each one inserts a core Query Loop that:

- queries the product post type in catalogue order, up to 100 products;
- shows the featured image and title, neither linked (products have no page to link to);
- shows the price in a paragraph bound to `hk-funeral-suite/price`.

The post type and order controls are hidden in the sidebar so the loop cannot drift back to blog posts. The category filter and search stay available.

Restyle the cards however the design needs. Add or remove core blocks inside the Post Template freely.

### How it works

A `query_loop_block_query_vars` filter puts the HK post type back into any Query Loop whose `postType` is an enabled HK type. A loop ordered by `menu_order` gets the catalogue order (`menu_order`, then title). The editor preview uses the same tie-break, so it matches what visitors see.

Hand-written markup works the same way, including for team members:

```html
<!-- wp:query {"query":{"perPage":20,"postType":"hk_fs_staff","order":"asc","orderBy":"menu_order","inherit":false}} -->
```

## Block bindings sources

Bind a paragraph or heading (or any bindable core attribute) inside a Query Loop over an HK post type.

### `hk-funeral-suite/price`

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"hk-funeral-suite/price","args":{"prefix":"From","suffix":"incl. GST"}}}}} -->
<p></p>
<!-- /wp:paragraph -->
```

| Arg | Default | Notes |
|-----|---------|-------|
| `prefix` | none | Numeric prices only |
| `suffix` | none | Numeric prices only |
| `poa` | "Price on application" | Label for a `POA` price |
| `decimals` | `0` | Decimal places for numeric prices |

Works for caskets, urns, monuments, keepsakes and packages.

### `hk-funeral-suite/field`

```html
<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"hk-funeral-suite/field","args":{"key":"_hk_fs_staff_position"}}}}} -->
<p></p>
<!-- /wp:paragraph -->
```

`key` must be one of the fields the plugin registers for that post type:

| Post type | Keys |
|-----------|------|
| Team members | `_hk_fs_staff_position`, `_hk_fs_staff_qualification`, `_hk_fs_staff_phone`, `_hk_fs_staff_email` |
| Packages | `_hk_fs_package_price`, `_hk_fs_package_intro`, `_hk_fs_package_order` |
| Caskets, urns, monuments | `_hk_fs_{type}_price` (the raw value; use the price source for display) |
| Keepsakes | `_hk_fs_keepsake_price`, `_hk_fs_keepsake_product_code`, `_hk_fs_keepsake_metal`, `_hk_fs_keepsake_stones` |

Any other key returns nothing. The catalogue's internal keys (master IDs, image version hashes, the second image ID) and anything that looks like a secret are refused even if someone adds them to the list.

### Behaviour

- **Empty values remove the block.** A paragraph or heading bound to either source is dropped when there is no value, so a card without a price shows no empty line or placeholder.
- **Visitors only see published posts.** Drafts (for example a product the catalogue has deactivated) and password-protected posts return nothing.
- **The editor previews real values.** Bound blocks are read-only in the editor. Edit prices and fields on the post itself, or in the catalogue.

## Patterns

The **HK Funeral Suite** pattern category has two patterns per enabled product type:

- **Casket grid**: a heading and the Product Grid block, with category headings at H3 and product names at H4.
- **Casket cards (Query Loop)**: the same cards as the "Caskets (HK)" variation.

Copy them into the theme's own patterns and restyle from there.

## Things that will not work

- **`[hk_formatted_price]` in a Shortcode block inside a Query Loop.** Shortcodes run outside the loop, so the price comes from the page, not the product. It renders empty. Use the price binding instead.
- **`core/post-meta` with an HK key.** Core refuses protected keys. Use `hk-funeral-suite/field`.
- **Linking product titles or images.** Products have no public URL.

## Beaver Builder and classic themes

`[hk_product_grid]` renders the same grid as the block, with the same attributes. If you build grids with Beaver Builder Loop modules instead, sort by menu order **ascending** to get the catalogue order. Descending puts the dearest and POA products first.

## Filters

| Filter | Default | Use |
|--------|---------|-----|
| `hk_fs_poa_label` | "Price on application" | Site-wide POA label |
| `hk_fs_product_grid_uncategorised_label` | "Other" | Heading for products with no category |
| `hk_fs_product_grid_excerpt_length` | `25` | Words in the excerpt description |
| `hk_fs_enable_block_bindings` | `true` | Turn the bindings sources off |
| `hk_fs_enable_query_loop_support` | `true` | Turn the Query Loop filter and variations off |

## WordPress versions

The plugin needs WordPress 6.6 or later. Block bindings need 6.5, and their editor preview uses the 6.7 bindings API. All of this has been tested on WordPress 7.1.3 with Twenty Twenty-Five and the Weave blocks starter theme. Older versions have not been tested.
