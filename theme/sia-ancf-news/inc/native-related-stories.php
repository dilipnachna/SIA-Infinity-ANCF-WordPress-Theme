<?php
/**
 * Local Related Stories ranking: semantic content, context, reader intent and freshness.
 * No Fibonacci neighbor selection, external AI calls, or paid service.
 */
if (!defined('ABSPATH')) {
    exit;
}

final class SIA_Native_Related_Stories {
    private const POLICY_VERSION = 'related-four-signals-v1';
    private const MAX_CANDIDATES = 420;
    private const CACHE_TTL = HOUR_IN_SECONDS;

    public static function boot(): void {
        add_filter('sia_ancf_news_related_story_ids', [self::class, 'recommend'], 20, 4);
    }

    /** @param array<int,int> $fallback @param array<int,int> $exclude @return array<int,int> */
    public static function recommend(array $fallback, int $post_id, int $limit = 3, array $exclude = []): array {
        $limit = max(1, min(3, $limit));
        $target = get_post($post_id);
        if (!$target instanceof WP_Post || $target->post_type !== 'post' || $target->post_status !== 'publish') {
            return [];
        }

        $cache_key = 'sia_related_' . md5(
            self::POLICY_VERSION . '|' . $post_id . '|' . $target->post_modified_gmt . '|' . get_lastpostmodified('GMT')
        );
        $cached = get_transient($cache_key);
        if (!is_array($cached)) {
            $cached = self::rank($target, $fallback);
            set_transient($cache_key, $cached, self::CACHE_TTL);
        }

        $blocked = array_fill_keys(array_map('absint', $exclude), true);
        $blocked[$post_id] = true;
        $ids = [];
        foreach ($cached as $id) {
            $id = absint($id);
            if ($id === 0 || isset($blocked[$id]) || get_post_status($id) !== 'publish'
                || get_post_type($id) !== 'post' || get_post_field('post_password', $id) !== '') {
                continue;
            }
            $ids[] = $id;
            $blocked[$id] = true;
            if (count($ids) === $limit) {
                break;
            }
        }
        return $ids;
    }

    /** @param array<int,int> $fallback @return array<int,int> */
    private static function rank(WP_Post $target, array $fallback): array {
        $target_profile = self::profile($target);
        if (!$target_profile['title'] || !$target_profile['document']) {
            return [];
        }

        $ranked = [];
        foreach (self::candidate_ids($target, $fallback) as $id) {
            $source = get_post($id);
            if (!$source instanceof WP_Post || $source->post_type !== 'post'
                || $source->post_status !== 'publish' || $source->post_password !== '') {
                continue;
            }
            $source_profile = self::profile($source);

            $shared_title = count(array_intersect_key($target_profile['title'], $source_profile['title']));
            $shared_tags = count(array_intersect($target_profile['tags'], $source_profile['tags']));
            // Taxonomy alone is NOT sufficient to call two stories related.
            if ($shared_title === 0 && $shared_tags === 0) {
                continue;
            }

            $semantic = self::cosine($target_profile['document'], $source_profile['document']);
            $intent = self::cosine($target_profile['title'], $source_profile['title']);
            $title_context = $shared_title / max(1, min(count($target_profile['title']), count($source_profile['title'])));
            $tag_context = $shared_tags > 0 ? $shared_tags / max(1, min(count($target_profile['tags']), count($source_profile['tags']))) : 0.0;
            $category_context = count(array_intersect($target_profile['categories'], $source_profile['categories'])) > 0 ? 1.0 : 0.0;
            $context = min(1.0, 0.70 * $title_context + 0.22 * $tag_context + 0.08 * $category_context);

            // Reject generic-word or merely recent matches even when they are in the same category.
            if ($semantic < 0.09 || $context < 0.18 || ($intent < 0.10 && $shared_tags === 0)) {
                continue;
            }

            $published = strtotime($source->post_date_gmt . ' UTC');
            $age_days = $published ? max(0.0, (time() - $published) / DAY_IN_SECONDS) : 3650.0;
            $freshness = exp(-$age_days / 180.0);
            $score = 0.45 * $semantic + 0.25 * $context + 0.20 * $intent + 0.10 * $freshness;
            $ranked[] = ['id' => $id, 'score' => $score, 'published' => $published ?: 0];
        }

        usort($ranked, static function (array $a, array $b): int {
            return ($b['score'] <=> $a['score'])
                ?: ($b['published'] <=> $a['published'])
                ?: ($b['id'] <=> $a['id']);
        });
        return array_slice(array_column($ranked, 'id'), 0, 18);
    }

    /** @param array<int,int> $fallback @return array<int,int> */
    private static function candidate_ids(WP_Post $target, array $fallback): array {
        $categories = wp_get_post_categories($target->ID, ['fields' => 'ids']);
        $tags = wp_get_post_tags($target->ID, ['fields' => 'ids']);
        $categories = is_array($categories) ? array_map('absint', $categories) : [];
        $tags = is_array($tags) ? array_map('absint', $tags) : [];
        $queries = [];
        if ($tags) {
            $queries[] = ['tag__in' => $tags, 'posts_per_page' => 120];
        }
        if ($categories) {
            $queries[] = ['category__in' => $categories, 'posts_per_page' => 160];
        }
        $queries[] = ['posts_per_page' => 240];
        $ids = array_values(array_unique(array_map('absint', $fallback)));
        $seen = array_fill_keys($ids, true);
        foreach ($queries as $extra) {
            $query = new WP_Query(array_merge([
                'post_type' => 'post',
                'post_status' => 'publish',
                'has_password' => false,
                'post__not_in' => [$target->ID],
                'ignore_sticky_posts' => true,
                'no_found_rows' => true,
                'fields' => 'ids',
                'orderby' => 'date',
                'order' => 'DESC',
            ], $extra));
            foreach ($query->posts as $id) {
                $id = absint($id);
                if ($id > 0 && !isset($seen[$id]) && $id !== (int) $target->ID) {
                    $ids[] = $id;
                    $seen[$id] = true;
                }
                if (count($ids) >= self::MAX_CANDIDATES) {
                    break 2;
                }
            }
        }
        $ids = array_slice($ids, 0, self::MAX_CANDIDATES);
        if ($ids && function_exists('_prime_post_caches')) {
            _prime_post_caches($ids, true, true);
        }
        return $ids;
    }

    /** @return array{title:array<string,float>,document:array<string,float>,tags:array<int,int>,categories:array<int,int>} */
    private static function profile(WP_Post $post): array {
        $title = get_the_title($post);
        $excerpt = trim((string) $post->post_excerpt);
        if ($excerpt === '') {
            $excerpt = wp_trim_words(wp_strip_all_tags(strip_shortcodes((string) $post->post_content)), 50, '');
        }
        $plain_content = wp_strip_all_tags(strip_shortcodes((string) $post->post_content));
        $body = function_exists('mb_substr') ? mb_substr($plain_content, 0, 2500, 'UTF-8') : substr($plain_content, 0, 2500);
        $title_vector = self::vector($title, 1.0);
        $document = self::vector($title, 4.0);
        foreach ([self::vector($excerpt, 2.0), self::vector($body, 1.0)] as $part) {
            foreach ($part as $word => $weight) {
                $document[$word] = ($document[$word] ?? 0.0) + $weight;
            }
        }
        $tags = wp_get_post_tags($post->ID, ['fields' => 'ids']);
        $categories = wp_get_post_categories($post->ID, ['fields' => 'ids']);
        return [
            'title' => $title_vector,
            'document' => $document,
            'tags' => is_array($tags) ? array_map('absint', $tags) : [],
            'categories' => is_array($categories) ? array_map('absint', $categories) : [],
        ];
    }

    /** @return array<string,float> */
    private static function vector(string $text, float $weight): array {
        $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $tokens = preg_split('/[^\p{L}\p{M}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stop = array_fill_keys([
            'the', 'and', 'for', 'with', 'from', 'this', 'that', 'new', 'news', 'latest',
            'update', 'today', 'says', 'after', 'about', 'into', 'will', 'are', 'was',
            'का', 'की', 'के', 'को', 'से', 'ने', 'में', 'पर', 'एक', 'है', 'थे', 'था',
            'और', 'यह', 'इस', 'लिए', 'तक', 'भी', 'अब', 'हुआ', 'हुई', 'खबर', 'समाचार',
        ], true);
        $vector = [];
        foreach (is_array($tokens) ? $tokens : [] as $word) {
            $length = function_exists('mb_strlen') ? mb_strlen($word, 'UTF-8') : strlen($word);
            if ($length < 2 || isset($stop[$word])) {
                continue;
            }
            $vector[$word] = ($vector[$word] ?? 0.0) + $weight;
        }
        return $vector;
    }

    /** @param array<string,float> $a @param array<string,float> $b */
    private static function cosine(array $a, array $b): float {
        if (!$a || !$b) {
            return 0.0;
        }
        $dot = 0.0;
        foreach ($a as $token => $weight) {
            $dot += $weight * ($b[$token] ?? 0.0);
        }
        $a_norm = 0.0;
        $b_norm = 0.0;
        foreach ($a as $weight) { $a_norm += $weight * $weight; }
        foreach ($b as $weight) { $b_norm += $weight * $weight; }
        return $a_norm > 0.0 && $b_norm > 0.0 ? min(1.0, $dot / sqrt($a_norm * $b_norm)) : 0.0;
    }
}

SIA_Native_Related_Stories::boot();
