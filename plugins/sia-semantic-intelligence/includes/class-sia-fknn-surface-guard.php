<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Final presentation guard for Fibonacci-kNN recommendation surfaces.
 *
 * The semantic/temporal graph remains the authority for relevance. This layer
 * prevents weak theme fallbacks from bypassing graph evidence and limits archive
 * concentration on compact frontend surfaces. It is read-only and universal.
 */
final class SIA_FKNN_Surface_Guard {
    private const POLICY_VERSION = 'fknn-surface-guard-v1';
    private const FRONTEND_CANDIDATE_LIMIT = 377;

    /** @var array<string,array<string,mixed>> */
    private static array $runtime_graph_cache = [];

    public static function boot(): void {
        // Run after the surface bridge so even cached/fallback output is checked
        // immediately before the theme renders it.
        add_filter('sia_ancf_news_more_stories_ids', [self::class, 'guard_more_stories'], 40, 4);
        add_filter('sia_ancf_news_related_story_ids', [self::class, 'guard_related_stories'], 40, 4);
    }

    /**
     * @param array<int,int> $ids
     * @param array<int,int> $exclude_ids
     * @return array<int,int>
     */
    public static function guard_more_stories(array $ids, int $post_id, int $limit, array $exclude_ids = []): array {
        return self::guard_surface('more', $ids, $post_id, $limit, $exclude_ids);
    }

    /**
     * @param array<int,int> $ids
     * @param array<int,int> $exclude_ids
     * @return array<int,int>
     */
    public static function guard_related_stories(array $ids, int $post_id, int $limit, array $exclude_ids = []): array {
        return self::guard_surface('related', $ids, $post_id, $limit, $exclude_ids);
    }

    /**
     * @param array<int,int> $ids
     * @param array<int,int> $exclude_ids
     * @return array<int,int>
     */
    private static function guard_surface(string $surface, array $ids, int $post_id, int $limit, array $exclude_ids): array {
        $limit = max(1, min(13, $limit));
        $target = get_post($post_id);
        if (!$target instanceof WP_Post || $target->post_status !== 'publish') {
            return self::normalize_ids($ids, $post_id, $limit, $exclude_ids);
        }

        $exclude_ids = self::normalize_exclusions($exclude_ids, $post_id);
        $result = self::graph($target, max(13, $limit));
        $recommendations = isset($result['recommendations']) && is_array($result['recommendations'])
            ? $result['recommendations']
            : [];

        $by_source = [];
        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation) || empty($recommendation['source_id'])) {
                continue;
            }
            $source_id = absint($recommendation['source_id']);
            if ($source_id > 0) {
                $by_source[$source_id] = $recommendation;
            }
        }

        // Existing surface output is evaluated first. Then the graph can fill a
        // rejected slot with the next evidence-backed candidate. A theme fallback
        // that is absent from the graph cannot bypass this guard.
        $candidate_ids = array_values(array_unique(array_merge(
            array_map('absint', $ids),
            array_keys($by_source)
        )));

        $historical_cap = self::historical_cap($surface, $target, $limit);
        $historical_count = 0;
        $kept = [];

        foreach ($candidate_ids as $source_id) {
            $source_id = absint($source_id);
            if ($source_id <= 0 || $source_id === $post_id || in_array($source_id, $exclude_ids, true) || in_array($source_id, $kept, true)) {
                continue;
            }

            $recommendation = $by_source[$source_id] ?? null;
            if (!is_array($recommendation)) {
                // Fallback Quality Gate: no semantic/temporal graph evidence,
                // therefore no permission to occupy a recommendation slot.
                continue;
            }

            $source = get_post($source_id);
            if (!$source instanceof WP_Post || $source->post_status !== 'publish') {
                continue;
            }

            if (!self::passes_quality_gate($recommendation, $surface, $target, $source)) {
                continue;
            }

            $historical = self::is_historical($recommendation);
            if ($historical && $historical_count >= $historical_cap) {
                continue;
            }

            $kept[] = $source_id;
            if ($historical) {
                $historical_count++;
            }

            if (count($kept) >= $limit) {
                break;
            }
        }

        return $kept;
    }

    /** @return array<string,mixed> */
    private static function graph(WP_Post $target, int $limit): array {
        $limit = max(1, min(13, $limit));
        $runtime_key = self::POLICY_VERSION . '|' . $target->ID . '|' . $limit . '|' . (string) get_post_modified_time('U', true, $target);
        if (isset(self::$runtime_graph_cache[$runtime_key])) {
            return self::$runtime_graph_cache[$runtime_key];
        }

        add_filter('sia_fknn_candidate_limit', [self::class, 'frontend_candidate_limit'], 1200, 2);
        $result = apply_filters('sia_fibonacci_knn_recommendations', [], $target, $limit);
        remove_filter('sia_fknn_candidate_limit', [self::class, 'frontend_candidate_limit'], 1200);

        $result = is_array($result) ? $result : [];
        self::$runtime_graph_cache[$runtime_key] = $result;
        return $result;
    }

    public static function frontend_candidate_limit(int $limit, WP_Post $target): int {
        $configured = (int) apply_filters('sia_fknn_surface_guard_candidate_limit', self::FRONTEND_CANDIDATE_LIMIT, $target);
        $configured = max(89, min(1597, $configured));
        return min($limit, $configured);
    }

    /** @param array<string,mixed> $recommendation */
    private static function passes_quality_gate(array $recommendation, string $surface, WP_Post $target, WP_Post $source): bool {
        $base = self::clamp01((float) ($recommendation['score'] ?? 0.0));
        $semantic = self::signal_score($recommendation, 'semantic');
        $context = self::signal_score($recommendation, 'context');
        $intent = self::signal_score($recommendation, 'intent');
        $temporal = self::signal_score($recommendation, 'temporal');
        $historical = self::is_historical($recommendation);

        if ($surface === 'related') {
            $minimum = self::clamp01((float) apply_filters('sia_fknn_related_guard_minimum', 0.40, $target, $source));
            $semantic_floor = self::clamp01((float) apply_filters('sia_fknn_related_guard_semantic_floor', 0.30, $target, $source));
            $context_floor = self::clamp01((float) apply_filters('sia_fknn_related_guard_context_floor', 0.38, $target, $source));
            $intent_floor = self::clamp01((float) apply_filters('sia_fknn_related_guard_intent_floor', 0.24, $target, $source));

            $pass = $base >= $minimum
                && (($semantic !== null && $semantic >= $semantic_floor) || ($context !== null && $context >= $context_floor))
                && ($intent === null || $intent >= $intent_floor);
        } else {
            $minimum = self::clamp01((float) apply_filters('sia_fknn_more_guard_minimum', 0.30, $target, $source));
            $semantic_floor = self::clamp01((float) apply_filters('sia_fknn_more_guard_semantic_floor', 0.26, $target, $source));
            $context_floor = self::clamp01((float) apply_filters('sia_fknn_more_guard_context_floor', 0.34, $target, $source));
            $intent_floor = self::clamp01((float) apply_filters('sia_fknn_more_guard_intent_floor', 0.26, $target, $source));

            $pass = $base >= $minimum
                && (($semantic !== null && $semantic >= $semantic_floor)
                    || ($context !== null && $context >= $context_floor)
                    || ($intent !== null && $intent >= $intent_floor));
        }

        // Historical material must earn its slot through stronger topical or
        // contextual evidence. This does not ban archives; it blocks weak-old
        // category-only fallback from masquerading as a recommendation.
        if ($pass && $historical) {
            $historical_minimum = self::clamp01((float) apply_filters('sia_fknn_historical_guard_minimum', 0.48, $surface, $target, $source));
            $historical_semantic = self::clamp01((float) apply_filters('sia_fknn_historical_guard_semantic_floor', 0.34, $surface, $target, $source));
            $historical_context = self::clamp01((float) apply_filters('sia_fknn_historical_guard_context_floor', 0.44, $surface, $target, $source));

            $pass = $base >= $historical_minimum
                && (($semantic !== null && $semantic >= $historical_semantic)
                    || ($context !== null && $context >= $historical_context));
        }

        $filtered = apply_filters(
            'sia_fknn_surface_guard_pass',
            $pass,
            $surface,
            $recommendation,
            $target,
            $source,
            [
                'base' => $base,
                'semantic' => $semantic,
                'context' => $context,
                'intent' => $intent,
                'temporal' => $temporal,
                'historical' => $historical,
            ]
        );

        return (bool) $filtered;
    }

    private static function historical_cap(string $surface, WP_Post $target, int $limit): int {
        $default = 1;
        $hook = $surface === 'related'
            ? 'sia_fknn_related_historical_cap'
            : 'sia_fknn_more_historical_cap';
        $cap = (int) apply_filters($hook, $default, $target, $limit);
        return max(0, min($limit, $cap));
    }

    /** @param array<string,mixed> $recommendation */
    private static function is_historical(array $recommendation): bool {
        $profile = isset($recommendation['temporal_source']) && is_array($recommendation['temporal_source'])
            ? $recommendation['temporal_source']
            : [];
        if (($profile['class'] ?? '') === 'historical') {
            return true;
        }

        // Compatibility fallback for providers that expose only the temporal
        // signal but not the coarse class.
        $temporal = self::signal_score($recommendation, 'temporal');
        return $temporal !== null && $temporal < 0.30;
    }

    /** @param array<string,mixed> $recommendation */
    private static function signal_score(array $recommendation, string $name): ?float {
        $signals = isset($recommendation['signals']) && is_array($recommendation['signals'])
            ? $recommendation['signals']
            : [];
        $signal = isset($signals[$name]) && is_array($signals[$name]) ? $signals[$name] : [];

        if (empty($signal['available']) || !isset($signal['score']) || !is_numeric($signal['score'])) {
            return null;
        }

        return self::clamp01((float) $signal['score']);
    }

    /** @param array<int,mixed> $ids @return array<int,int> */
    private static function normalize_exclusions(array $ids, int $target_id): array {
        $result = [];
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id <= 0 || $id === $target_id || in_array($id, $result, true)) {
                continue;
            }
            $result[] = $id;
        }
        return $result;
    }

    /**
     * @param array<int,mixed> $ids
     * @param array<int,int> $exclude_ids
     * @return array<int,int>
     */
    private static function normalize_ids(array $ids, int $target_id, int $limit, array $exclude_ids): array {
        $exclude_ids = self::normalize_exclusions($exclude_ids, $target_id);
        $result = [];
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id <= 0 || $id === $target_id || in_array($id, $exclude_ids, true) || in_array($id, $result, true)) {
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

    private static function clamp01(float $value): float {
        return max(0.0, min(1.0, $value));
    }
}
