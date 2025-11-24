Search And Filters - Active Filters

This small plugin provides the `[active_filters_breadcrumbs]` shortcode which renders currently active filters as pill-style buttons with a small × remove control.

Shortcode attributes

- `show_keys` (bool) — when `true` always show `key: value` on pills. Default: `false`.
- `hide_underscored` (bool) — hide keys that start with an underscore (e.g. `_category`). Default: `true`.
- `hide_taxonomy` (bool) — hide taxonomy key portion and show only term. Default: `true`.
- `hide_common_keys` (bool) — hide common keys like `category`, `tag`, `author`. Default: `true`.
 - By default the plugin now prettifies raw keys into Title Case. For example `_custom-taxonomy` or `custom-taxonomy` will display as `Custom Taxonomy`.

Examples

- Default:

  [active_filters_breadcrumbs]

- Show keys explicitly:

  [active_filters_breadcrumbs show_keys="true"]

- Keep underscored keys visible:

  [active_filters_breadcrumbs hide_underscored="false"]

Programmatic usage (template)

```php
echo do_shortcode('[active_filters_breadcrumbs]');
```

Label mapping

Map raw GET keys to friendly labels using the `saf_label_map` filter:

```php
add_filter('saf_label_map', function($map){
  $map['price_min'] = 'Min price';
  $map['price_max'] = 'Max price';
  return $map;
});
```

Custom removal URLs

If your theme uses pretty permalinks or custom archive pages, override the removal URL with `saf_remove_url_for_item`:

```php
add_filter('saf_remove_url_for_item', function($url, $param, $context){
  if (! empty($context['is_taxonomy'])) {
    // return canonical listing/archive page for this taxonomy
    return home_url('/shop/');
  }
  return $url;
}, 10, 3);
```

ACF / Custom taxonomies

The plugin detects registered public taxonomies (including those added via ACF) by inspecting `get_taxonomies()` and will show terms when the taxonomy query var or GET param is present. If your ACF filters do not expose query vars, supply removal URLs via `afb_remove_url_for_item`.

Pretty keys example

If you want to customize how keys are prettified, use `saf_items` or `saf_label_map`. By default keys are converted by replacing `-`/`_` with spaces and Title Casing the result.

Example: transform `_custom-taxonomy` to `Custom Taxonomy` and keep term label:

```php
add_filter('saf_items', function($items){
  foreach ($items as &$it) {
    if (! empty($it['key']) && $it['key'] === 'custom-taxonomy') {
      // already prettified by default; adjust label if you want a custom string
      $it['label'] = str_replace('Custom Taxonomy', 'My Custom Tax', $it['label']);
    }
  }
  return $items;
});
```

Map custom GET keys to taxonomies

If your front-end uses a different query param name than the taxonomy's `query_var`
(for example `_custom-taxonomy` or `custtax`), map those keys to the taxonomy name
using `saf_taxonomy_aliases`:

```php
add_filter('saf_taxonomy_aliases', function($map){
  // map an underscored GET key to the registered taxonomy 'custom-taxonomy'
  $map['_custom-taxonomy'] = 'custom-taxonomy';
  // map a short param name to the taxonomy
  $map['custtax'] = 'custom-taxonomy';
  return $map;
});
```

After adding this filter, GET keys listed above will be treated as taxonomy filters
by the plugin, which enables `hide_taxonomy`, prettier labels and proper term resolution.

Unified key mapping (`afb_key_map`)

Instead of having separate filters for labels and taxonomy aliases you can use
`afb_key_map` to map any key to a friendly label and/or to a taxonomy name. The
value can be a string (friendly label) or an array with `label` and/or `taxonomy`.

Examples:

```php
add_filter('saf_key_map', function($map){
  // Friendly label mapping for GET keys
  $map['price_min'] = 'Min price';
  $map['price_max'] = 'Max price';
  $map['color'] = 'Color';

  // Map custom GET param names to a registered taxonomy
  $map['custtax'] = array('taxonomy' => 'custom-taxonomy');
  // Map underscored param to taxonomy and provide a custom label for the taxonomy portion
  $map['_custom-taxonomy'] = array('taxonomy' => 'custom-taxonomy', 'label' => 'Custom Taxonomy');

  return $map;
});
```

The plugin will consult `saf_key_map` first, then fall back to `saf_label_map` and
`saf_taxonomy_aliases` for legacy behavior. This gives a single place to control
how raw GET keys are treated and displayed.

Files

- `active-filters-breadcrumbs.php` — main plugin
- `css/afb-style.css` — styles for pills
- `js/afb-script.js` — small JS to animate pill removal and navigate

License

Use and modify as you like.
