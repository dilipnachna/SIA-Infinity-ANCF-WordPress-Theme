<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Age-diversity guard for the Related Stories surface.
 *
 * This is a late, read-only presentation policy. It does not change the base
 * Fibonacci-kNN graph, More Stories, contextual inlink recommendations, post
 * content, URLs or SEO output. Fresh targets prefer fresher related documents,
 * while older material can still survive when semantic AND contextual evidence
 * is strong enough to justify an archive/reference slot.
 */
final class SIA_FKNN_Related_Age_Diversity_Guard {
    private const POLICY_VERSION = 'fknn-related-age-diversity-v1';
    private const FRONTEND_CANDIDATE_LIMIT = 377;

    /** @var array<string,array<string,mixed>> */
    private static array $runtime_graph_cache = [];

    public static function boot(): void {
        // Run after the surface bridge and general surface guard. More Stories
        // has its own freshness authority and is intentionally untouched here.
        add_filter('sia_ancf_news_related_story_ids', [self::class, 'guard_related_stories'], 60, 4);
    }

    /**
     * @param array<int,int> $ids
     * @param array<int,int> $exclude_ids
     * @return array<int,int>
     */
    public static function guard_related_stories(array $ids, int $post_id, int $limit, array $exclude_ids = []): array {
        $limit = max(1, min(13, $limit));
        $target = get_post($post_id);
        if (!$target instanceof WP_Post || $target->post_status !== 'publish') {
            return self::normalize_ids($ids, $post_id, $limit, $exclude_ids);
        }

        $target_age_days = self::modified_age_days($target);
        $fresh_target_days = max(1, min(365, (int) apply_filters('sia_fknn_related_fresh_target_days', 120, $target)));

        // Age pressure is a fresh/current user-journey policy, not a global rule.
        // Archive and evergreen targets retain the existing semantic surface.
        if ($target_age_days > $fresh_target_days) {
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

        // Existing Related Stories are evaluated first, then the next graph-backed
        // neighbor may fill a rejected stale slot. No raw fallback bypass exists.
        $candidate_ids = array_values(array_unique(array_merge(
            array_map('absint', $ids),
            array_keys($by_source)
        )));

        $ranked = [];
        foreach ($candidate_ids as $source_id) {
            $source_id = absint($source_id);
            if ($source_id <= 0 || $source_id === $post_id || in_array($source_id, $exclude_ids, true)) {
                continue;
            }

            $recommendation = $by_source[$source_id] ?? null;
            if (!is_array($recommendation)) {
                continue;
            }

            $source = get_post($source_id);
            if (!$source instanceof WP_Post || $source->post_status !== 'publish') {
                continue;
            }

            $age_days = self::source_age_days($recommendation, $source);
            if (!self::passes_age_gate($recommendation, $target, $source, $age_days)) {
                continue;
            }

            $ranked[] = [
                'id' => $source_id,
                'score' => self::related_age_score($recommendation, $target, $source, $age_days),
                'age_days' => $age_days,
                'stale_or_archive' => self::is_stale_or_archive($recommendation, $age_days, $target),
            ];
        }

        usort($ranked, static function (array $a, array $b): int {
            $score_order = ((float) $b['score']) <=> ((float) $a['score']);
            if ($score_order !== 0) {
                return $score_order;
            }
            return ((int) $a['age_days']) <=> ((int) $b['age_days']);
        });

        $stale_cap = max(0, min($limit, (int) apply_filters('sia_fknn_related_stale_cap', 1, $target, $limit)));
        $stale_count = 0;
        $kept = [];

        foreach ($ranked as $row) {
            if (!empty($row['stale_or_archive'])) {
                if ($stale_count >= $stale_cap) {
                    continue;
                }
                $stale_count++;
            }

            $kept[] = (int) $row['id'];
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

        add_filter('sia_fknn_candidate_limit', [self::class, 'frontend_candidate_limit'], 1400, 2);
        $result = apply_filters('sia_fibonacci_knn_recommendations', [], $target, $limit);
        remove_filter('sia_fknn_candidate_limit', [self::class, 'frontend_candidate_limit'], 1400);

        $result = is_array($result) ? $result : [];
        self::$runtime_graph_cache[$runtime_key] = $result;
        return $result;
    }

    public static function frontend_candidate_limit(int $limit, WP_Post $target): int {
        $configured = (int) apply_filters('sia_fknn_related_age_guard_candidate_limit', self::FRONTEND_CANDIDATE_LIMIT, $target);
        $configured = max(89, min(1597, $configured));
        return min($limit, $configured);
    }

    /** @param array<string,mixed> $recommendation */
    private static function passes_age_gate(array $recommendation, WP_Post $target, WP_Post $source, int $age_days): bool {
        $base = self::clamp01((float) ($recommendation['score'] ?? 0.0));
        $semantic = self::signal_score($recommendation, 'semantic');
        $context = self::signal_score($recommendation, 'context');
        $intent = self::signal_score($recommendation, 'intent');

        $normal_days = max(30, min(730, (int) apply_filters('sia_fknn_related_age_normal_days', 365, $target)));
        $exceptional_days = max($normal_days + 1, min(3650, (int) apply_filters('sia_fknn_related_age_exceptional_days', 730, $target)));

        if ($age_days <= $normal_days) {
            $pass = true;
        } elseif ($age_days <= $exceptional_days) {
            // 366-730 days by default: stronger relevance is required, but an
            // archive/reference document can still occupy the single stale slot.
            $minimum = self::clamp01((float) apply_filters('sia_fknn_related_age_strong_minimum', 0.46, $target, $source));
            $semantic_floor = self::clamp01((float) apply_filters('sia_fknn_related_age_strong_semantic_floor', 0.34, $target, $source));
            $context_floor = self::clamp01((float) apply_filters('sia_fknn_related_age_strong_context_floor', 0.44, $target, $source));
            $intent_floor = self::clamp01((float) apply_filters('sia_fknn_related_age_strong_intent_floor', 0.24, $target, $source));

            $pass = $base >= $minimum
                && (($semantic !== null && $semantic >= $semantic_floor)
                    || ($context !== null && $context >= $context_floor))
                && ($intent === null || $intent >= $intent_floor);
        } else {
            // >730 days by default: exceptional related evidence must be broad,
            // not a one-signal accident. Semantic AND context must both be strong.
            $minimum = self::clamp01((float) apply_filters('sia_fknn_related_age_exceptional_minimum', 0.56, $target, $source));
            $semantic_floor = self::clamp01((float) apply_filters('sia_fknn_related_age_exceptional_semantic_floor', 0.46, $target, $source));
            $context_floor = self::clamp01((float) apply_filters('sia_fknn_related_age_exceptional_context_floor', 0.56, $target, $source));
            $intent_floor = self::clamp01((float) apply_filters('sia_fknn_related_age_exceptional_intent_floor', 0.34, $target, $source));

            $pass = $base >= $minimum
                && $semantic !== null && $semantic >= $semantic_floor
                && $context !== null && $context >= $context_floor
                && ($intent === null || $intent >= $intent_floor);
        }

        return (bool) apply_filters(
            'sia_fknn_related_age_gate_pass',
            $pass,
            $recommendation,
            $target,
            $source,
            $age_days
        );
    }

    /** @param array<string,mixed> $recommendation */
    private static function related_age_score(array $recommendation, WP_Post $target, WP_Post $source, int $age_days): float {
        $weights = ['base' => 8, 'semantic' => 5, 'context' => 3, 'temporal' => 2, 'intent' => 1];
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

        $normal_days = max(30, min(730, (int) apply_filters('sia_fknn_related_age_normal_days', 365, $target)));
        $exceptional_days = max($normal_days + 1, min(3650, (int) apply_filters('sia_fknn_related_age_exceptional_days', 730, $target)));

        if ($age_days <= $normal_days) {
            $decay = 1.00;
        } elseif ($age_days <= $exceptional_days) {
            $decay = 0.82;
        } else {
            $decay = 0.50;
        }

        $decay = (float) apply_filters('sia_fknn_related_age_decay', $decay, $age_days, $recommendation, $target, $source);
        return self::clamp01($score * max(0.0, min(1.0, $decay)));
    }

    /** @param array<string,mixed> $recommendation */
    private static function is_stale_or_archive(array $recommendation, int $age_days, WP_Post $target): bool {
        $stale_days = max(30, min(3650, (int) apply_filters('sia_fknn_related_stale_days', 365, $target)));
        if ($age_days > $stale_days) {
            return true;
        }

        $profile = isset($recommendation['temporal_source']) && is_array($recommendation['temporal_source'])
            ? $recommendation['temporal_source']
            : [];
        return (($profile['class'] ?? '') === 'historical');
    }

    /** @param array<string,mixed> $recommendation */
    private static function source_age_days(array $recommendation, WP_Post $source): int {
        $profile = isset($recommendation['temporal_source']) && is_array($recommendation['temporal_source'])
            ? $recommendation['temporal_source']
            : [];
        if (isset($profile['modified_age_days']) && is_numeric($profile['modified_age_days'])) {
            return max(0, (int) $profile['modified_age_days']);
        }
        return self::modified_age_days($source);
    }

    private static function modified_age_days(WP_Post $post): int {
        $now = (int) current_time('timestamp', true);
        if ($now <= 0) {
            $now = time();
        }
        $modified = (int) get_post_modified_time('U', true, $post);
        if ($modified <= 0) {
            $modified = (int) get_post_time('U', true, $post);
        }
        if ($modified <= 0 || $modified >= $now) {
            return 0;
        }
        return max(0, (int) floor(($now - $modified) / DAY_IN_SECONDS));
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
