<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bridges the universal Fibonacci-kNN graph into presentation surfaces.
 *
 * One semantic/temporal graph serves multiple surfaces, but each surface has a
 * different user-journey objective. More Stories prioritizes freshness and
 * continuity. Related Stories applies a stricter semantic/context quality gate.
 * No post content, URLs or SEO output are mutated.
 */
final class SIA_FKNN_Related_Content_Bridge {
    private const CACHE_TTL = HOUR_IN_SECONDS;
    private const FRONTEND_CANDIDATE_LIMIT = 377;
    private const GRAPH_CACHE_VERSION = 'fknn-temporal-v1';
    private const SURFACE_POLICY_VERSION = 'fknn-surface-v1';

    /** @var array<string,array<string,mixed>> */
    private static array $runtime_graph_cache = [];

    public static function boot(): void {
        // Backward-compatible broad related-content contract.
        add_filter('sia_ancf_news_related_ids', [self::class, 'rank_related'], 20, 3);

        // Surface-specific contracts. The theme still supplies safe fallbacks.
        add_filter('sia_ancf_news_more_stories_ids', [self::class, 'rank_more_stories'], 20, 4);
        add_filter('sia_ancf_news_related_story_ids', [self::class, 'rank_related_stories'], 20, 4);
    }

    /**
     * Broad compatibility layer retained for themes/integrations that only know
     * the original related-content contract.
     *
     * @param array<int,int> $fallback_ids
     * @return array<int,int>
     */
    public static function rank_related(array $fallback_ids, int $post_id, int $limit): array {
        $limit = max(1, min(13, $limit));
        $target = get_post($post_id);
        if (!$target instanceof WP_Post || $target->post_status !== 'publish') {
            return self::normalize_ids($fallback_ids, $post_id, $limit);
        }

        $cache_key = self::cache_key($target, $limit, 'broad', []);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return self::normalize_ids($cached, $post_id, $limit);
        }

        $result = self::graph($target, $limit);
        $semantic_ids = self::recommendation_ids($result, $post_id, []);
        $ranked = self::normalize_ids(array_merge($semantic_ids, $fallback_ids), $post_id, $limit);

        set_transient(
            $cache_key,
            $ranked,
            (int) apply_filters('sia_fknn_related_cache_ttl', self::CACHE_TTL, $target)
        );

        return $ranked;
    }

    /**
     * More Stories favors fresh/current continuity while still allowing a very
     * strong historical neighbor to survive when evidence warrants it.
     *
     * @param array<int,int> $fallback_ids
     * @param array<int,int> $exclude_ids
     * @return array<int,int>
     */
    public static function rank_more_stories(array $fallback_ids, int $post_id, int $limit, array $exclude_ids = []): array {
        return self::rank_surface('more', $fallback_ids, $post_id, $limit, $exclude_ids);
    }

    /**
     * Related Stories is quality-first. It does not fill empty slots with weak
     * category-only fallback cards unless a site explicitly opts in.
     *
     * @param array<int,int> $fallback_ids
     * @param array<int,int> $exclude_ids
     * @return array<int,int>
     */
    public static function rank_related_stories(array $fallback_ids, int $post_id, int $limit, array $exclude_ids = []): array {
        return self::rank_surface('related', $fallback_ids, $post_id, $limit, $exclude_ids);
    }

    /**
     * @param array<int,int> $fallback_ids
     * @param array<int,int> $exclude_ids
     * @return array<int,int>
     */
    private static function rank_surface(string $surface, array $fallback_ids, int $post_id, int $limit, array $exclude_ids): array {
        $limit = max(1, min(13, $limit));
        $exclude_ids = self::normalize_exclusions($exclude_ids, $post_id);
        $target = get_post($post_id);

        if (!$target instanceof WP_Post || $target->post_status !== 'publish') {
            return self::normalize_ids_excluding($fallback_ids, $post_id, $limit, $exclude_ids);
        }

        $cache_key = self::cache_key($target, $limit, $surface, $exclude_ids);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return self::normalize_ids_excluding($cached, $post_id, $limit, $exclude_ids);
        }

        // Ask the graph for a wider neighborhood than either UI surface needs.
        $graph_limit = max(13, $limit);
        $result = self::graph($target, $graph_limit);
        $recommendations = isset($result['recommendations']) && is_array($result['recommendations'])
            ? $result['recommendations']
            : [];

        $ranked = [];
        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation) || empty($recommendation['source_id'])) {
                continue;
            }

            $source_id = absint($recommendation['source_id']);
            if ($source_id <= 0 || $source_id === $post_id || in_array($source_id, $exclude_ids, true)) {
                continue;
            }

            $source = get_post($source_id);
            if (!$source instanceof WP_Post || $source->post_status !== 'publish') {
                continue;
            }

            if (!self::passes_surface_gate($recommendation, $surface, $target, $source)) {
                continue;
            }

            $ranked[] = [
                'id' => $source_id,
                'surface_score' => self::surface_score($recommendation, $surface, $target, $source),
            ];
        }

        usort($ranked, static function (array $a, array $b): int {
            return ((float) $b['surface_score']) <=> ((float) $a['surface_score']);
        });

        $surface_ids = array_map(static fn(array $row): int => (int) $row['id'], $ranked);
        $surface_ids = self::normalize_ids_excluding($surface_ids, $post_id, $limit, $exclude_ids);

        if ($surface === 'more') {
            // Sidebar continuity may use safe theme fallback after semantic picks.
            $surface_ids = self::normalize_ids_excluding(
                array_merge($surface_ids, $fallback_ids),
                $post_id,
                $limit,
                $exclude_ids
            );
        } else {
            // Related Stories defaults to quality over quantity.
            $allow_fallback = (bool) apply_filters('sia_fknn_related_surface_allow_fallback', false, $target);
            if ($allow_fallback) {
                $surface_ids = self::normalize_ids_excluding(
                    array_merge($surface_ids, $fallback_ids),
                    $post_id,
                    $limit,
                    $exclude_ids
                );
            }
        }

        set_transient(
            $cache_key,
            $surface_ids,
            (int) apply_filters('sia_fknn_related_cache_ttl', self::CACHE_TTL, $target)
        );

        return $surface_ids;
    }

    /** @return array<string,mixed> */
    private static function graph(WP_Post $target, int $limit): array {
        $limit = max(1, min(13, $limit));
        $runtime_key = $target->ID . '|' . $limit . '|' . (string) get_post_modified_time('U', true, $target);
        if (isset(self::$runtime_graph_cache[$runtime_key])) {
            return self::$runtime_graph_cache[$runtime_key];
        }

        add_filter('sia_fknn_candidate_limit', [self::class, 'frontend_candidate_limit'], 1000, 2);
        $result = apply_filters('sia_fibonacci_knn_recommendations', [], $target, $limit);
        remove_filter('sia_fknn_candidate_limit', [self::class, 'frontend_candidate_limit'], 1000);

        $result = is_array($result) ? $result : [];
        self::$runtime_graph_cache[$runtime_key] = $result;
        return $result;
    }

    /**
     * @param array<string,mixed> $recommendation
     */
    private static function passes_surface_gate(array $recommendation, string $surface, WP_Post $target, WP_Post $source): bool {
        $base = self::clamp01((float) ($recommendation['score'] ?? 0.0));
        $semantic = self::signal_score($recommendation, 'semantic');
        $context = self::signal_score($recommendation, 'context');
        $intent = self::signal_score($recommendation, 'intent');

        if ($surface === 'related') {
            $minimum = self::clamp01((float) apply_filters('sia_fknn_related_surface_minimum', 0.40, $target, $source));
            if ($base < $minimum) {
                return false;
            }

            // Prevent category/entity-only matches from filling Related Stories.
            $semantic_floor = self::clamp01((float) apply_filters('sia_fknn_related_semantic_floor', 0.30, $target, $source));
            $context_floor = self::clamp01((float) apply_filters('sia_fknn_related_context_floor', 0.38, $target, $source));
            $intent_floor = self::clamp01((float) apply_filters('sia_fknn_related_intent_floor', 0.24, $target, $source));

            $has_semantic_evidence = $semantic !== null && $semantic >= $semantic_floor;
            $has_context_evidence = $context !== null && $context >= $context_floor;
            $has_intent_evidence = $intent === null || $intent >= $intent_floor;

            return ($has_semantic_evidence || $has_context_evidence) && $has_intent_evidence;
        }

        $minimum = self::clamp01((float) apply_filters('sia_fknn_more_surface_minimum', 0.28, $target, $source));
        return $base >= $minimum;
    }

    /**
     * Fibonacci-weighted surface score. Missing signals are omitted rather than
     * treated as zero.
     *
     * More Stories weights: base 8, temporal 5, semantic 3, intent 2, context 1.
     * Related Stories: base 8, semantic 5, context 3, temporal 2, intent 1.
     *
     * @param array<string,mixed> $recommendation
     */
    private static function surface_score(array $recommendation, string $surface, WP_Post $target, WP_Post $source): float {
        $weights = $surface === 'related'
            ? ['base' => 8, 'semantic' => 5, 'context' => 3, 'temporal' => 2, 'intent' => 1]
            : ['base' => 8, 'temporal' => 5, 'semantic' => 3, 'intent' => 2, 'context' => 1];

        $values = [
            'base' => self::clamp01((float) ($recommendation['score'] ?? 0.0)),
            'semantic' => self::signal_score($recommendation, 'semantic'),
            'context' => self::signal_score($recommendation, 'context'),
            'temporal' => self::signal_score($recommendation, 'temporal'),
            'intent' => self::signal_score($recommendation, 'intent'),
        ];

        $weighted = 0.0;
        $weight_sum = 0;
        foreach ($weights as $name => $weight) {
            if (!array_key_exists($name, $values) || $values[$name] === null) {
                continue;
            }
            $weighted += $weight * self::clamp01((float) $values[$name]);
            $weight_sum += $weight;
        }

        $score = $weight_sum > 0 ? self::clamp01($weighted / $weight_sum) : 0.0;

        // Fresh/current surfaces should not be dominated by explicit archive
        // material, but a genuinely strong archive relation can still survive.
        $temporal = $values['temporal'];
        if ($temporal !== null && $temporal < 0.40) {
            $multiplier = $surface === 'related' ? 0.75 : 0.82;
            $multiplier = (float) apply_filters('sia_fknn_surface_historical_multiplier', $multiplier, $surface, $target, $source);
            $score *= max(0.0, min(1.0, $multiplier));
        }

        return self::clamp01((float) apply_filters('sia_fknn_surface_score', $score, $surface, $recommendation, $target, $source));
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

    /** @param array<string,mixed> $result @param array<int,int> $exclude_ids @return array<int,int> */
    private static function recommendation_ids(array $result, int $post_id, array $exclude_ids): array {
        $ids = [];
        $recommendations = isset($result['recommendations']) && is_array($result['recommendations'])
            ? $result['recommendations']
            : [];

        foreach ($recommendations as $recommendation) {
            if (!is_array($recommendation) || empty($recommendation['source_id'])) {
                continue;
            }
            $id = absint($recommendation['source_id']);
            if ($id <= 0 || $id === $post_id || in_array($id, $exclude_ids, true)) {
                continue;
            }
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    public static function frontend_candidate_limit(int $limit, WP_Post $target): int {
        $configured = (int) apply_filters('sia_fknn_related_candidate_limit', self::FRONTEND_CANDIDATE_LIMIT, $target);
        $configured = max(89, min(1597, $configured));
        return min($limit, $configured);
    }

    /** @param array<int,int> $exclude_ids */
    private static function cache_key(WP_Post $target, int $limit, string $surface, array $exclude_ids): string {
        $modified = (string) get_post_modified_time('U', true, $target);
        $corpus = (string) get_lastpostmodified('GMT');
        $graph_version = (string) apply_filters('sia_fknn_related_graph_version', self::GRAPH_CACHE_VERSION, $target);
        $exclude_hash = $exclude_ids ? md5(implode(',', $exclude_ids)) : 'none';

        return 'sia_fknn_rel_' . md5(
            $target->ID . '|' . $modified . '|' . $corpus . '|' . $limit . '|' . $surface . '|' . $exclude_hash . '|' . $graph_version . '|' . self::SURFACE_POLICY_VERSION
        );
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
        sort($result, SORT_NUMERIC);
        return $result;
    }

    /**
     * @param array<int,mixed> $ids
     * @return array<int,int>
     */
    private static function normalize_ids(array $ids, int $target_id, int $limit): array {
        return self::normalize_ids_excluding($ids, $target_id, $limit, []);
    }

    /**
     * @param array<int,mixed> $ids
     * @param array<int,int> $exclude_ids
     * @return array<int,int>
     */
    private static function normalize_ids_excluding(array $ids, int $target_id, int $limit, array $exclude_ids): array {
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
