<?php

/**
 * Plugin Name: Search And Filters - Active Filters
 * Description: Shortcode that shows currently active filters in a breadcrumbs style with links to remove each filter. Use shortcode [active_filters_breadcrumbs].
 * Version: 0.1
 * Author: Automated Assistant
 */

/**
 * Usage
 *
 * Shortcode: use `[active_filters_breadcrumbs]` in templates or content.
 * See `README.md` in the plugin folder for detailed examples, available
 * shortcode attributes and filter usage (label mapping, taxonomy aliases,
 * unified `afb_key_map`, and `afb_remove_url_for_item`).
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Register plugin assets (styles and scripts). Registered on init so they can be enqueued by the shortcode.
 */
function saf_register_assets()
{
    $base = plugin_dir_url(__FILE__);
    wp_register_style('saf-style', $base . 'css/afb-style.css', array(), '0.1');
    wp_register_script('saf-script', $base . 'js/afb-script.js', array(), '0.1', true);
    
    // Register AJAX update script (enqueued only when shortcode is used)
    wp_register_script('saf-ajax-update', $base . 'js/afb-ajax-update.js', array('jquery'), '0.1', true);
    wp_localize_script('saf-ajax-update', 'safAjax', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('saf_refresh_nonce'),
    ));
}

add_action('wp_enqueue_scripts', 'saf_register_assets');
function saf_get_current_url()
{
    return esc_url_raw(home_url(add_query_arg(null, null)));
}

/**
 * Build a URL that removes one or more query params from the current URL.
 *
 * Plugins/themes can override the computed removal URL by hooking
 * `afb_remove_url_for_item` and returning a custom URL. The filter receives
 * the computed URL, the original $param (string|array) and an optional
 * $context array with metadata like 'key' and 'label'.
 *
 * @param string|array $param   Query var key or array of keys to remove.
 * @param array        $context Optional metadata about the item (key, label, is_taxonomy).
 * @return string
 */
function saf_build_remove_link($param, $context = array())
{
    $current = home_url(add_query_arg(null, null));
    $url = remove_query_arg($param, $current);

    /**
     * Filter the removal URL for an active filter item.
     *
     * @param string        $url     The computed removal URL.
     * @param string|array  $param   The query var(s) to remove.
     * @param array         $context Optional metadata about the item.
     */
    $url = apply_filters('saf_remove_url_for_item', $url, $param, $context);

    return esc_url($url);
}

function saf_get_active_filters_items()
{
    global $wp_query;

    $items = array();

    // Preload public taxonomies map for later detection (query_var -> taxonomy obj, and name -> obj)
    $taxes = get_taxonomies(array('public' => true), 'objects');
    $tax_map = array();
    if (! empty($taxes) && is_array($taxes)) {
        foreach ($taxes as $tax) {
            $qv = isset($tax->query_var) && $tax->query_var !== false ? $tax->query_var : $tax->name;
            $tax_map[$qv] = $tax;
            $tax_map[$tax->name] = $tax;
        }
    }

    // Allow developers to register aliases for taxonomy query keys. This is useful when
    // front-end filters use different GET parameter names (for example ACF-generated
    // inputs that include leading underscores or custom param names).
    // See README.md for examples and usage of `afb_taxonomy_aliases`.
    $taxonomy_aliases = apply_filters('saf_taxonomy_aliases', array());
    if (! empty($taxonomy_aliases) && is_array($taxonomy_aliases)) {
        foreach ($taxonomy_aliases as $alias => $tax_name) {
            if (! isset($tax_map[$alias]) && isset($tax_map[$tax_name])) {
                $tax_map[$alias] = $tax_map[$tax_name];
            }
        }
    }

    // Unified key map: developers can map raw GET keys to either a friendly label,
    // to a taxonomy name, or both. This centralizes label mapping and aliasing.
    // See README.md for examples and usage of `afb_key_map`.
    $key_map = apply_filters('saf_key_map', array());

    // Search
    if (is_search()) {
        $s = get_search_query();
        if ($s !== '') {
            $items[] = array(
                'label' => sprintf('Search: "%s"', $s),
                'remove' => saf_build_remove_link('s', array('key' => 's', 'label' => $s)),
                'key' => 's',
            );
        }
    }
    $cat_id = get_query_var('cat');
    if ($cat_id) {
        $cat = get_category(intval($cat_id));
        if ($cat && ! is_wp_error($cat)) {
            $items[] = array(
                'label' => sprintf('Category: %s', $cat->name),
                'remove' => saf_build_remove_link('cat', array('key' => 'cat', 'label' => $cat->name, 'is_taxonomy' => true)),
                'key' => 'cat',
                'is_taxonomy' => true,
            );
        }
    } else {
        $cat_slug = get_query_var('category_name');
        if ($cat_slug) {
            $term = get_term_by('slug', $cat_slug, 'category');
            if ($term) {
                $items[] = array(
                    'label' => sprintf('Category: %s', $term->name),
                    'remove' => saf_build_remove_link('category_name', array('key' => 'category_name', 'label' => $term->name, 'is_taxonomy' => true)),
                    'key' => 'category_name',
                    'is_taxonomy' => true,
                );
            }
        }
    }

    // Tag
    $tag_slug = get_query_var('tag');
    if ($tag_slug) {
        $tag = get_term_by('slug', $tag_slug, 'post_tag');
        if ($tag) {
            $items[] = array(
                'label' => sprintf('Tag: %s', $tag->name),
                'remove' => saf_build_remove_link('tag', array('key' => 'tag', 'label' => $tag->name, 'is_taxonomy' => true)),
                'key' => 'tag',
                'is_taxonomy' => true,
            );
        }
    }

    // Author
    $author_id = get_query_var('author');
    if ($author_id) {
        $display = get_the_author_meta('display_name', $author_id);
        if ($display) {
            $items[] = array(
                'label' => sprintf('Author: %s', $display),
                'remove' => saf_build_remove_link('author', array('key' => 'author', 'label' => $display)),
                'key' => 'author',
            );
        }
    } else {
        $author_name = get_query_var('author_name');
        if ($author_name) {
            $user = get_user_by('slug', $author_name);
            if ($user) {
                $items[] = array(
                    'label' => sprintf('Author: %s', $user->display_name),
                    'remove' => saf_build_remove_link('author_name', array('key' => 'author_name', 'label' => $user->display_name)),
                    'key' => 'author_name',
                );
            }
        }
    }

    // Taxonomy / term pages
    if (is_tax() || is_category() || is_tag()) {
        $term = get_queried_object();
        if (isset($term->taxonomy) && isset($term->name)) {
            $tax_obj = get_taxonomy($term->taxonomy);
            $label = isset($tax_obj->labels->singular_name) ? $tax_obj->labels->singular_name : $term->taxonomy;
            $items[] = array(
                'label' => sprintf('%s: %s', $label, $term->name),
                'remove' => saf_build_remove_link($term->taxonomy, array('key' => $term->taxonomy, 'label' => $term->name, 'is_taxonomy' => true)),
                'key' => $term->taxonomy,
                'is_taxonomy' => true,
            );
        }
    }

    // Post type archive
    if (is_post_type_archive()) {
        $pt = get_post_type();
        $obj = get_post_type_object($pt);
        if ($obj) {
            $items[] = array(
                'label' => sprintf('Type: %s', $obj->labels->singular_name),
                'remove' => saf_build_remove_link('post_type', array('key' => 'post_type', 'label' => $obj->labels->singular_name)),
                'key' => 'post_type',
            );
        }
    }

    // Date-based queries
    $year = get_query_var('year');
    $month = get_query_var('monthnum');
    $day = get_query_var('day');
    if ($year) {
        $label = 'Year: ' . intval($year);
        if ($month) {
            $label .= ' / ' . sprintf('%02d', intval($month));
        }
        if ($day) {
            $label .= ' / ' . sprintf('%02d', intval($day));
        }
        $items[] = array(
            'label' => $label,
            'remove' => saf_build_remove_link(array('year', 'monthnum', 'day')),
        );
    }

    // Any additional GET params (common pattern for filters like price_min, color, etc.)
    $skip = array('paged', 'pagename', 's', 'cat', 'category_name', 'tag', 'author', 'author_name', 'post_type', 'year', 'monthnum', 'day', 'preview_id', 'preview_nounce', 'preview');

    /**
     * Filter the list of query parameter keys to skip/ignore when building active filter items.
     * This is useful for excluding framework-specific or preview params (e.g. Elementor's preview_id).
     *
     * @param array $skip Array of query var keys to skip.
     */
    $skip = apply_filters('saf_skip_params', $skip);

    foreach ($_GET as $k => $v) {
        if (in_array($k, $skip, true)) {
            continue;
        }
        if (! is_scalar($v) || $v === '') {
            continue;
        }
        // Preserve original key for removal, and a normalized lookup key (without leading underscores)
        $orig_k = $k;
        $lookup_k = ltrim($k, '_');

        // If this GET key (or its underscored variant) matches a registered taxonomy's query_var or taxonomy name,
        // treat it as a taxonomy item so hide_taxonomy and taxonomy formatting apply.
        if (! empty($tax_map) && (isset($tax_map[$k]) || isset($tax_map[$lookup_k]))) {
            $tax = isset($tax_map[$lookup_k]) ? $tax_map[$lookup_k] : $tax_map[$k];
            // attempt to resolve a term for nicer label when possible
            $term = get_term_by('slug', $v, $tax->name);
            if (! $term) {
                $term = get_term_by('id', intval($v), $tax->name);
            }
            $display_name = $term && ! is_wp_error($term) ? $term->name : sanitize_text_field($v);
            $tax_label = isset($tax->labels->singular_name) ? $tax->labels->singular_name : $tax->label;

            $items[] = array(
                'label' => sprintf('%s: %s', $tax_label, $display_name),
                'remove' => saf_build_remove_link($k, array('key' => $k, 'label' => $display_name, 'is_taxonomy' => true)),
                'key' => $k,
                'is_taxonomy' => true,
            );
        } else {
            // Use the normalized key (no leading underscores) for label/key so rendering
            // options like strip_underscored and hide_taxonomy behave consistently.
            $norm_k = ltrim($k, '_');
            $label_key = sanitize_text_field($norm_k);
            $items[] = array(
                'label' => sprintf('%s: %s', $label_key, sanitize_text_field($v)),
                // removal should still target the original GET param (may include underscores)
                'remove' => saf_build_remove_link($orig_k, array('key' => $norm_k, 'label' => sanitize_text_field($v))),
                'key' => $norm_k,
            );
        }
    }

    // Detect registered public taxonomies (including those added via ACF) and add items
    if (! empty($taxes) && is_array($taxes)) {
        // Collect existing keys to avoid duplicates
        $existing_keys = array();
        foreach ($items as $it) {
            if (! empty($it['key'])) {
                $existing_keys[] = $it['key'];
            }
        }

        foreach ($taxes as $tax) {
            $qv = isset($tax->query_var) && $tax->query_var !== false ? $tax->query_var : $tax->name;
            if (in_array($qv, $existing_keys, true)) {
                continue;
            }

            // check for a value in query vars or GET
            $val = get_query_var($qv);
            if (! $val && isset($_GET[$qv])) {
                $val = sanitize_text_field($_GET[$qv]);
            }
            if ($val) {
                // attempt to resolve a term for nicer label when possible
                $term = get_term_by('slug', $val, $tax->name);
                if (! $term) {
                    $term = get_term_by('id', intval($val), $tax->name);
                }

                $display_name = $term && ! is_wp_error($term) ? $term->name : $val;
                $tax_label = isset($tax->labels->singular_name) ? $tax->labels->singular_name : $tax->label;

                $items[] = array(
                    'label' => sprintf('%s: %s', $tax_label, $display_name),
                    'remove' => saf_build_remove_link($qv, array('key' => $qv, 'label' => $display_name, 'is_taxonomy' => true)),
                    'key' => $qv,
                    'is_taxonomy' => true,
                );
            }
        }
    }

    return $items;
}

function saf_shortcode_render($atts)
{
    $atts = shortcode_atts(array(
        // show_keys: true/false - when true, always show `key: value` on pills
        'show_keys' => false,
        // hide_underscored: hide keys that start with underscore (e.g. _category)
        'hide_underscored' => true,
        // strip_underscored: remove leading underscores from labels (default true)
        'strip_underscored' => true,
        // hide_taxonomy: hide taxonomy key portion and show only term
        'hide_taxonomy' => true,
        // hide_common_keys: hide common keys like 'category', 'tag', 'author'
        'hide_common_keys' => true,
    ), $atts, 'active_filters_breadcrumbs');

    // Normalize boolean-ish shortcode attributes
    $show_keys = filter_var($atts['show_keys'], FILTER_VALIDATE_BOOLEAN);
    $hide_underscored = filter_var($atts['hide_underscored'], FILTER_VALIDATE_BOOLEAN);
    $strip_underscored = filter_var($atts['strip_underscored'], FILTER_VALIDATE_BOOLEAN);
    $hide_taxonomy = filter_var($atts['hide_taxonomy'], FILTER_VALIDATE_BOOLEAN);
    $hide_common_keys = filter_var($atts['hide_common_keys'], FILTER_VALIDATE_BOOLEAN);

    // Allow mapping raw GET keys to friendly labels via filter: saf_label_map
    // Example: add_filter('saf_label_map', fn($m) => array_merge($m, ['price_min' => 'Min price']));
    $label_map = apply_filters('saf_label_map', array(
        // default friendly mappings (can be extended)
        'price_min' => 'Min price',
        'price_max' => 'Max price',
        'color' => 'Color',
        'size' => 'Size',
    ));


    $items = saf_get_active_filters_items();
    /**
     * Filter the active items array so themes (child themes) can modify, merge or
     * remove items prior to rendering. Example use: combine `price_min`/`price_max`
     * into a single range label.
     *
     * @param array $items Array of item arrays with keys: label, remove, key, is_taxonomy.
     */
    $items = apply_filters('saf_items', $items);
    if (empty($items)) {
        return ''; // nothing active
    }

    // Enqueue assets only when shortcode is present on the page
    wp_enqueue_style('saf-style');
    wp_enqueue_script('saf-script');
    wp_enqueue_script('saf-ajax-update');

    $out = '';
    $out .= '<nav class="afb-breadcrumbs" data-saf-breadcrumb="1" aria-label="Active filters">';
    $out .= '<span class="afb-prefix">Filters:</span> ';

    $parts = array();
    foreach ($items as $item) {
        $full_label = isset($item['label']) ? $item['label'] : '';

        // Compute display label: by default show full label
        $display_label = $full_label;

        // If label contains a key/value format "key: value" split it
        $kv = preg_split('/\s*:\s*/', $full_label, 2);
        $has_kv = count($kv) === 2;

        // Optionally strip leading underscores from the key portion (user-friendly)
        if ($has_kv && $strip_underscored) {
            $kv[0] = preg_replace('/^_+/', '', $kv[0]);
        }

        // Keys that we prefer to hide (show only the value):
        $hide_key = false;
        $k = ! empty($item['key']) ? $item['key'] : '';

        if ($show_keys) {
            // explicit override: show keys always
            $hide_key = false;
        } else {
            if ($hide_taxonomy && ! empty($item['is_taxonomy'])) {
                $hide_key = true;
            }
            if (! $hide_key && $hide_underscored && $k !== '' && strpos($k, '_') === 0) {
                $hide_key = true;
            }
            if (! $hide_key && $hide_common_keys && $k !== '') {
                $taxonomy_like = array('s', '_s', 'category', '_category', 'category_name', 'tag', 'author', 'author_name');
                if (in_array($k, $taxonomy_like, true)) {
                    $hide_key = true;
                }
            }
        }

        // If mapping exists and we don't hide the key, use mapping for key portion
        if ($has_kv && ! empty($item['key']) && isset($label_map[$item['key']]) && ! $hide_key) {
            $display_label = $label_map[$item['key']] . ': ' . $kv[1];
        } elseif ($has_kv && ! $hide_key) {
            // No mapping, show key: value but prettify the key (Title Case, hyphens -> spaces)
            $pretty_key = ucwords(str_replace(array('-', '_'), ' ', $kv[0]));
            $display_label = $pretty_key . ': ' . $kv[1];
        } elseif ($has_kv && $hide_key) {
            // Show only the value portion
            $display_label = $kv[1];
        } else {
            $display_label = $full_label;
            // If we don't have a separable key/value pair, allow stripping a leading underscore
            if (! $has_kv && $strip_underscored) {
                if ($k !== '' && strpos($k, '_') === 0) {
                    // Replace the key occurrence in the label with a de-underscored version
                    $display_label = preg_replace('/^' . preg_quote($k, '/') . '/', ltrim($k, '_'), $display_label);
                } else {
                    $display_label = preg_replace('/^_+/', '', $display_label);
                }
            }
        }

        // Remove a trailing parenthetical like " (taxonomy)" if present
        $display_label = preg_replace('/\s*\([^)]*\)\s*$/', '', $display_label);

        $aria_label = esc_attr($full_label);
        $disp = esc_html($display_label);

        if (! empty($item['remove'])) {
            $parts[] = sprintf(
                '<span class="afb-filter"><span class="afb-label">%1$s</span><a href="%2$s" class="afb-remove-btn" aria-label="Remove filter %3$s">&times;</a></span>',
                $disp,
                esc_url($item['remove']),
                $aria_label
            );
        } else {
            $parts[] = sprintf('<span class="afb-filter afb-filter-static"><span class="afb-label">%s</span></span>', $disp);
        }
    }

    // Render as a list of pill buttons (no separators required)
    $out .= implode(' ', $parts);

    // Clear all link
    $query_keys = array_keys($_GET);
    if (! empty($query_keys)) {
        $clear_url = remove_query_arg($query_keys, home_url(add_query_arg(null, null)));
        $out .= ' <span class="afb-sep">|</span> <a class="afb-clear" href="' . esc_url($clear_url) . '">Clear all</a>';
    }

    $out .= '</nav>';

    // Styles are enqueued from css/afb-style.css

    return $out;
}

add_shortcode('active_filters_breadcrumbs', 'saf_shortcode_render');

/**
 * AJAX endpoint to re-render the active filters breadcrumb.
 * Called when Elementor query loop updates via AJAX.
 */
function saf_ajax_refresh_breadcrumb() {
    // Verify nonce for security
    check_ajax_referer('saf_refresh_nonce', 'nonce');

    // Re-render the shortcode with default attributes
    $output = saf_shortcode_render(array());

    wp_send_json_success(array(
        'html' => $output,
    ));
}
add_action('wp_ajax_saf_refresh_breadcrumb', 'saf_ajax_refresh_breadcrumb');
add_action('wp_ajax_nopriv_saf_refresh_breadcrumb', 'saf_ajax_refresh_breadcrumb');
