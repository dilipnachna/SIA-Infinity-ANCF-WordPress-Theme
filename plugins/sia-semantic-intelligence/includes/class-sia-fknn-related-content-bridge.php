<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bridges the universal Fibonacci-kNN semantic graph into presentation surfaces.
 *
 * The theme supplies a generic related-content filter. This bridge ranks the
 * same source documents used by the inlink recommender and returns semantic
 * neighbors first, then preserves the theme's fallback order when the semantic
 * neighborhood is too small. No post content, URLs or SEO output are mutated.
 */
final class SIA_FKNN_Related_Content_Bridge {
    private const CACHE_TTL = HOUR_IN_SECONDS;
    private const FRONTEND_CANDIDATE_LIMIT = 377;

    public static function boot(): void {
        add_filter('sia_ancf_news_related_ids', [self::class, 'rank_related'], 20, 3);
    }

    /**
     * @param array<int,int> $fallback_ids
     * @return array<int,int>
     */
    public static function rank_related(array $fallback_ids, int $post_id, int $limit): array {
        $limit = max(1, min(13, $limit));
        $target = get_post($post_id);
        if (!$target instanceof WP_Post || $target->post_status !== 'publish') {
            return self::normalize_ids($fallback_ids, $post_id, $limit);
        }

        $cache_key = self::cache_key($target, $limit);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return self::normalize_ids($cached, $post_id, $limit);
        }

        add_filter('sia_fknn_candidate_limit', [self::class, 'frontend_candidate_limit'], 1000, 2);
        $result = apply_filters('sia_fibonacci_knn_recommendations', [], $target, $limit);
        remove_filter('sia_fknn_candidate_limit', [self::class, 'frontend_candidate_limit'], 1000);

        $semantic_ids = [];
        if (is_array($result) && !empty($result['recommendations']) && is_array($result['recommendations'])) {
            foreach ($result['recommendations'] as $recommendation) {
                if (!is_array($recommendation) || empty($recommendation['source_id'])) {
                    continue;
                }
                $candidate_id = absint($recommendation['source_id']);
                if ($candidate_id <= 0 || $candidate_id === $post_id) {
                    continue;
                }
                $candidate = get_post($candidate_id);
                if (!$candidate instanceof WP_Post || $candidate->post_status !== 'publish') {
                    continue;
                }
                $semantic_ids[] = $candidate_id;
            }
        }

        $ranked = self::normalize_ids(array_merge($semantic_ids, $fallback_ids), $post_id, $limit);
        set_transient($cache_key, $ranked, (int) apply_filters('sia_fknn_related_cache_ttl', self::CACHE_TTL, $target));

        return $ranked;
    }

    public static function frontend_candidate_limit(int $limit, WP_Post $target): int {
        $configured = (int) apply_filters('sia_fknn_related_candidate_limit', self::FRONTEND_CANDIDATE_LIMIT, $target);
        $configured = max(89, min(1597, $configured));
        return min($limit, $configured);
    }

    private static function cache_key(WP_Post $target, int $limit): string {
        $modified = (string) get_post_modified_time('U', true, $target);
        $corpus = (string) get_lastpostmodified('GMT');
        return 'sia_fknn_rel_' . md5($target->ID . '|' . $modified . '|' . $corpus . '|' . $limit);
    }

    /**
     * @param array<int,mixed> $ids
     * @return array<int,int>
     */
    private static function normalize_ids(array $ids, int $target_id, int $limit): array {
        $result = [];
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id <= 0 || $id === $target_id || in_array($id, $result, true)) {
                continue;
            }
            $post = get_post($id);
            if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
                continue;
            }
            $result[] = $id;
            if (count($result) >= $limit) {
                break;
            }
        }
        return $result;
    }
}
