<?php
/** Publisher logo, hierarchical breadcrumb schema, and taxonomy SEO editing. */
if (!defined('ABSPATH')) {
    exit;
}

add_filter('sia_publisher_schema_organization', static function (array $organization, $object, string $url): array {
    $logo_id = (int) get_theme_mod('custom_logo', 0);
    if ($logo_id <= 0 || !wp_attachment_is_image($logo_id)) {
        return $organization;
    }
    $logo_url = wp_get_attachment_image_url($logo_id, 'full');
    if (!is_string($logo_url) || $logo_url === '') {
        return $organization;
    }
    $logo = ['@type' => 'ImageObject', 'url' => esc_url_raw($logo_url)];
    $meta = wp_get_attachment_metadata($logo_id);
    if (is_array($meta) && !empty($meta['width']) && !empty($meta['height'])) {
        $logo['width'] = (int) $meta['width'];
        $logo['height'] = (int) $meta['height'];
    }
    $organization['logo'] = $logo;
    return $organization;
}, 10, 3);

/** One site hierarchy for both taxonomy archives and individual posts. */
function sia_ancf_native_breadcrumb_categories(WP_Term $term): array {
    if ($term->taxonomy !== 'category') {
        return [$term];
    }
    $ancestors = array_reverse(get_ancestors((int) $term->term_id, 'category', 'taxonomy'));
    $chain = [];
    foreach ($ancestors as $id) {
        $parent = get_term((int) $id, 'category');
        if ($parent instanceof WP_Term && !is_wp_error($parent)) {
            $chain[] = $parent;
        }
    }
    $chain[] = $term;
    return $chain;
}

function sia_ancf_native_breadcrumb_schema($object, string $url): ?array {
    $links = [['name' => __('Home', 'sia-ancf-news'), 'url' => home_url('/')]];
    if ($object instanceof WP_Term && in_array($object->taxonomy, ['category', 'post_tag'], true)) {
        $chain = sia_ancf_native_breadcrumb_categories($object);
        foreach ($chain as $term) {
            $term_url = get_term_link($term);
            if (!is_wp_error($term_url)) {
                $links[] = ['name' => $term->name, 'url' => $term_url];
            }
        }
    } elseif ($object instanceof WP_Post && $object->post_type === 'post') {
        $category = function_exists('sia_ancf_news_post_category')
            ? sia_ancf_news_post_category((int) $object->ID) : null;
        if ($category instanceof WP_Term) {
            foreach (sia_ancf_native_breadcrumb_categories($category) as $term) {
                $term_url = get_term_link($term);
                if (!is_wp_error($term_url)) {
                    $links[] = ['name' => $term->name, 'url' => $term_url];
                }
            }
        }
        $links[] = ['name' => get_the_title($object), 'url' => $url];
    } elseif ($object instanceof WP_Post && $object->post_type === 'page') {
        foreach (array_reverse(get_post_ancestors($object)) as $ancestor_id) {
            $links[] = ['name' => get_the_title($ancestor_id), 'url' => get_permalink($ancestor_id)];
        }
        $links[] = ['name' => get_the_title($object), 'url' => $url];
    } else {
        return null;
    }
    if (count($links) < 2) {
        return null;
    }
    $elements = [];
    foreach ($links as $link) {
        if (empty($link['url']) || empty($link['name'])) {
            continue;
        }
        $elements[] = [
            '@type' => 'ListItem',
            'position' => count($elements) + 1,
            'name' => html_entity_decode(wp_strip_all_tags((string) $link['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'item' => esc_url_raw((string) $link['url']),
        ];
    }
    return count($elements) >= 2
        ? ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => $elements]
        : null;
}

add_filter('sia_publisher_schema_graph', static function (array $graph, $object, string $url): array {
    if (is_front_page() || !(bool) get_option('blog_public')) {
        return $graph;
    }
    $breadcrumb = sia_ancf_native_breadcrumb_schema($object, $url);
    if (!$breadcrumb) {
        return $graph;
    }
    foreach ($graph as &$node) {
        if (is_array($node) && ($node['@type'] ?? '') === 'WebPage') {
            $node['breadcrumb'] = ['@id' => $breadcrumb['@id']];
        }
    }
    unset($node);
    $graph[] = $breadcrumb;
    return $graph;
}, 20, 3);

/** Editable metadata for category and topic archives (no SEO plugin). */
foreach (['category', 'post_tag'] as $taxonomy) {
    add_action($taxonomy . '_edit_form_fields', static function (WP_Term $term): void {
        if (!sia_ancf_native_seo_enabled() || !current_user_can('edit_term', $term->term_id)) {
            return;
        }
        wp_nonce_field('sia_ancf_term_seo_' . $term->term_id, 'sia_ancf_term_seo_nonce');
        $title = (string) get_term_meta($term->term_id, '_sia_term_seo_title', true);
        $description = (string) get_term_meta($term->term_id, '_sia_term_seo_description', true);
        echo '<tr class="form-field"><th scope="row"><label for="sia-term-seo-title">SEO title</label></th><td>';
        echo '<input id="sia-term-seo-title" name="sia_term_seo_title" type="text" maxlength="220" value="' . esc_attr($title) . '">';
        echo '<p class="description">Optional. Uses the WordPress category title when blank.</p></td></tr>';
        echo '<tr class="form-field"><th scope="row"><label for="sia-term-seo-description">Meta description</label></th><td>';
        echo '<textarea id="sia-term-seo-description" name="sia_term_seo_description" rows="3" maxlength="320">' . esc_textarea($description) . '</textarea>';
        echo '<p class="description">Optional. Describe this category for readers and search engines.</p></td></tr>';
    });
    add_action('edited_' . $taxonomy, static function (int $term_id): void {
        if (!sia_ancf_native_seo_enabled() || !current_user_can('edit_term', $term_id)
            || !isset($_POST['sia_ancf_term_seo_nonce'])
            || !is_string($_POST['sia_ancf_term_seo_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['sia_ancf_term_seo_nonce'])),
                'sia_ancf_term_seo_' . $term_id
            )) {
            return;
        }
        foreach (['_sia_term_seo_title' => 'sia_term_seo_title', '_sia_term_seo_description' => 'sia_term_seo_description'] as $key => $field) {
            $value = isset($_POST[$field]) && is_string($_POST[$field])
                ? sanitize_text_field(wp_unslash($_POST[$field])) : '';
            $value = wp_html_excerpt($value, $key === '_sia_term_seo_title' ? 220 : 320, '');
            if ($value === '') {
                delete_term_meta($term_id, $key);
            } else {
                update_term_meta($term_id, $key, $value);
            }
        }
    });
}

add_filter('pre_get_document_title', static function ($title) {
    if (!sia_ancf_native_seo_enabled() || !(is_category() || is_tag())) {
        return $title;
    }
    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return $title;
    }
    $custom = (string) get_term_meta($term->term_id, '_sia_term_seo_title', true);
    return $custom !== '' ? $custom : $title;
}, 20);
