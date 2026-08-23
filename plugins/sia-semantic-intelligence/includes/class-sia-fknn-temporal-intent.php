<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Universal temporal-intent compatibility layer for the Fibonacci-kNN graph.
 *
 * This module does not depend on tenant names, topic keywords or language
 * dictionaries. It uses structural time evidence: explicit years in the
 * title/excerpt plus publish/modified recency. Undated documents are treated
 * as compatible-but-uncertain rather than automatically stale.
 */
final class SIA_FKNN_Temporal_Intent {
    private const MODULE_VERSION = '0.5.0-alpha.2';
    private const DISPLAY_LIMIT = 5;

    /** @var array<string,int> */
    private const SIGNAL_WEIGHTS = [
        'semantic' => 13,
        'context'  => 8,
        'intent'   => 5,
        'entity'   => 3,
        'temporal' => 3,
        'value'    => 2,
    ];

    public static function boot(): void {
        add_filter('sia_fibonacci_knn_recommendations', [self::class, 'rerank'], 20, 3);
        add_action('add_meta_boxes', [self::class, 'replace_meta_box'], 30, 1);
    }

    public static function replace_meta_box(string $post_type): void {
        if (!in_array($post_type, ['post', 'page'], true)) {
            return;
        }

        remove_meta_box('sia-fibonacci-knn-inlinks', $post_type, 'normal');
        add_meta_box(
            'sia-fibonacci-knn-inlinks',
            'SIA Fibonacci kNN — Inlink Recommendations',
            [self::class, 'render_meta_box'],
            $post_type,
            'normal',
            'default'
        );
    }

    /**
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    public static function rerank(array $result, WP_Post $target, int $limit = self::DISPLAY_LIMIT): array {
        if (empty($result['recommendations']) || !is_array($result['recommendations'])) {
            $result['temporal_target'] = self::profile($target);
            $result['temporal_intent_version'] = self::MODULE_VERSION;
            return $result;
        }

        $limit = max(1, min(13, $limit));
        $target_profile = self::profile($target);
        $ranked = [];

        foreach ($result['recommendations'] as $recommendation) {
            if (!is_array($recommendation) || empty($recommendation['source_id'])) {
                continue;
            }

            $source = get_post((int) $recommendation['source_id']);
            if (!$source instanceof WP_Post || $source->post_status !== 'publish') {
                continue;
            }

            $source_profile = self::profile($source);
            $temporal = self::temporal_compatibility($target_profile, $source_profile, $target, $source);

            $signals = isset($recommendation['signals']) && is_array($recommendation['signals'])
                ? $recommendation['signals']
                : [];
            $signals['temporal'] = $temporal;

            $score = self::weighted_score($signals);
            $minimum_score = self::clamp01((float) apply_filters('sia_fknn_minimum_score', 0.18, $target, $source));
            if ($score < $minimum_score) {
                continue;
            }

            $recommendation['signals'] = $signals;
            $recommendation['score'] = $score;
            $recommendation['temporal_source'] = $source_profile;
            $ranked[] = $recommendation;
        }

        usort($ranked, static function (array $a, array $b): int {
            return ((float) ($b['score'] ?? 0.0)) <=> ((float) ($a['score'] ?? 0.0));
        });

        $ranked = array_slice($ranked, 0, $limit);
        $result['recommendations'] = $ranked;
        $result['neighborhood_confidence'] = self::neighborhood_confidence($ranked);
        $result['temporal_target'] = $target_profile;
        $result['temporal_intent_version'] = self::MODULE_VERSION;

        return $result;
    }

    public static function render_meta_box(WP_Post $post): void {
        if ($post->post_status !== 'publish') {
            echo '<p><em>Publish this document before calculating inlink recommendations.</em></p>';
            return;
        }

        $result = apply_filters('sia_fibonacci_knn_recommendations', [], $post, self::DISPLAY_LIMIT);
        $target_profile = isset($result['temporal_target']) && is_array($result['temporal_target'])
            ? $result['temporal_target']
            : self::profile($post);

        echo '<p><strong>Mode:</strong> read-only · <strong>Formula:</strong> Fibonacci-weighted kNN + temporal intent · <strong>k:</strong> ' . esc_html((string) ($result['k'] ?? 0)) . ' / ' . esc_html((string) ($result['candidate_count'] ?? 0)) . ' candidates</p>';
        echo '<p style="color:#646970">Score = available evidence only: 13×semantic + 8×best context + 5×intent + 3×entity/taxonomy + 3×temporal compatibility + 2×source value. Missing evidence is omitted from the denominator, never treated as zero.</p>';
        echo '<p style="color:#646970"><strong>Target temporal profile:</strong> ' . esc_html(self::profile_label($target_profile)) . '</p>';

        $recommendations = isset($result['recommendations']) && is_array($result['recommendations'])
            ? $result['recommendations']
            : [];

        if (!$recommendations) {
            echo '<p><em>No sufficiently relevant unlinked source document was found in the current candidate neighborhood.</em></p>';
            return;
        }

        echo '<div style="display:grid;gap:10px">';
        foreach ($recommendations as $rank => $recommendation) {
            $source_id = (int) ($recommendation['source_id'] ?? 0);
            if ($source_id <= 0) {
                continue;
            }

            $edit_link = get_edit_post_link($source_id);
            $permalink = get_permalink($source_id);
            $score = number_format_i18n(((float) ($recommendation['score'] ?? 0.0)) * 100, 1);
            $signals = isset($recommendation['signals']) && is_array($recommendation['signals'])
                ? $recommendation['signals']
                : [];
            $source_profile = isset($recommendation['temporal_source']) && is_array($recommendation['temporal_source'])
                ? $recommendation['temporal_source']
                : self::profile(get_post($source_id));

            echo '<section style="border:1px solid #dcdcde;border-radius:6px;padding:12px;background:#fff">';
            echo '<div style="display:flex;justify-content:space-between;gap:16px;align-items:start">';
            echo '<div><strong>#' . esc_html((string) ($rank + 1)) . ' ' . esc_html(get_the_title($source_id)) . '</strong>';
            echo '<div style="color:#646970;margin-top:3px">Source → current target · ' . esc_html((string) get_post_type($source_id)) . '</div></div>';
            echo '<strong style="font-size:1.05em">' . esc_html($score) . '%</strong>';
            echo '</div>';

            echo '<p style="margin-bottom:6px"><strong>Evidence:</strong> semantic ' . esc_html(self::signal_percent($signals['semantic'] ?? []))
                . ' · context ' . esc_html(self::signal_percent($signals['context'] ?? []))
                . ' · intent ' . esc_html(self::signal_percent($signals['intent'] ?? []))
                . ' · entity ' . esc_html(self::signal_percent($signals['entity'] ?? []))
                . ' · temporal ' . esc_html(self::signal_percent($signals['temporal'] ?? []))
                . ' · value ' . esc_html(self::signal_percent($signals['value'] ?? [])) . '</p>';

            echo '<p style="margin:5px 0;color:#646970"><strong>Temporal:</strong> target ' . esc_html(self::profile_label($target_profile))
                . ' ↔ source ' . esc_html(self::profile_label($source_profile));
            if (!empty($signals['temporal']['reason'])) {
                echo ' · ' . esc_html((string) $signals['temporal']['reason']);
            }
            echo '</p>';

            if (!empty($recommendation['context_excerpt'])) {
                echo '<p style="margin:6px 0;padding:8px 10px;background:#f6f7f7;border-left:3px solid #2271b1"><strong>Best insertion context:</strong> ' . esc_html((string) $recommendation['context_excerpt']) . '</p>';
            }

            echo '<p style="margin-bottom:0">';
            if ($edit_link) {
                echo '<a href="' . esc_url($edit_link) . '">Edit source</a>';
            }
            if ($permalink) {
                echo ($edit_link ? ' · ' : '') . '<a href="' . esc_url($permalink) . '" target="_blank" rel="noopener">View source</a>';
            }
            echo '</p></section>';
        }
        echo '</div>';

        echo '<p style="margin-top:12px"><strong>Neighborhood confidence:</strong> ' . esc_html(self::percent((float) ($result['neighborhood_confidence'] ?? 0.0))) . '. <em>No link is inserted automatically.</em></p>';
    }

    /**
     * @return array{class:string,explicit_year:?int,modified_age_days:int,published_age_days:int,has_explicit_year:bool}
     */
    private static function profile(WP_Post $post): array {
        $now = (int) current_time('timestamp', true);
        if ($now <= 0) {
            $now = time();
        }

        $current_year = (int) gmdate('Y', $now);
        $modified_ts = (int) get_post_modified_time('U', true, $post);
        $published_ts = (int) get_post_time('U', true, $post);
        if ($modified_ts <= 0) {
            $modified_ts = $published_ts > 0 ? $published_ts : $now;
        }
        if ($published_ts <= 0) {
            $published_ts = $modified_ts;
        }

        $modified_age_days = max(0, (int) floor(($now - $modified_ts) / DAY_IN_SECONDS));
        $published_age_days = max(0, (int) floor(($now - $published_ts) / DAY_IN_SECONDS));

        $excerpt = trim((string) $post->post_excerpt);
        if ($excerpt === '') {
            $excerpt = wp_trim_words(wp_strip_all_tags(strip_shortcodes((string) $post->post_content)), 30, '');
        }
        $years = self::explicit_years(get_the_title($post) . ' ' . $excerpt);
        $explicit_year = $years ? max($years) : null;

        $current_days = max(1, min(90, (int) apply_filters('sia_fknn_temporal_current_days', 21, $post)));
        $recent_days = max($current_days, min(365, (int) apply_filters('sia_fknn_temporal_recent_days', 120, $post)));

        if ($explicit_year !== null && $explicit_year < $current_year) {
            $class = 'historical';
        } elseif ($explicit_year !== null && $explicit_year > $current_year) {
            $class = 'future';
        } elseif ($modified_age_days <= $current_days) {
            $class = 'current';
        } elseif ($modified_age_days <= $recent_days) {
            $class = 'recent';
        } elseif ($explicit_year !== null && $explicit_year === $current_year) {
            $class = 'recent';
        } elseif ($published_age_days > 730 && $modified_age_days > 365) {
            $class = 'evergreen';
        } else {
            $class = 'undated';
        }

        $profile = [
            'class' => $class,
            'explicit_year' => $explicit_year,
            'modified_age_days' => $modified_age_days,
            'published_age_days' => $published_age_days,
            'has_explicit_year' => $explicit_year !== null,
        ];

        $filtered = apply_filters('sia_fknn_temporal_profile', $profile, $post);
        return is_array($filtered) ? array_merge($profile, $filtered) : $profile;
    }

    /** @return array<int,int> */
    private static function explicit_years(string $text): array {
        if (!preg_match_all('/(?<!\d)(?:19|20|21)\d{2}(?!\d)/u', $text, $matches)) {
            return [];
        }

        $years = [];
        foreach ($matches[0] as $year) {
            $year = (int) $year;
            if ($year >= 1900 && $year <= 2199) {
                $years[] = $year;
            }
        }
        return array_values(array_unique($years));
    }

    /**
     * @param array<string,mixed> $target_profile
     * @param array<string,mixed> $source_profile
     * @return array{available:bool,score:?float,reason:string}
     */
    private static function temporal_compatibility(array $target_profile, array $source_profile, WP_Post $target, WP_Post $source): array {
        $target_class = (string) ($target_profile['class'] ?? 'undated');
        $source_class = (string) ($source_profile['class'] ?? 'undated');
        $target_year = isset($target_profile['explicit_year']) && is_numeric($target_profile['explicit_year']) ? (int) $target_profile['explicit_year'] : null;
        $source_year = isset($source_profile['explicit_year']) && is_numeric($source_profile['explicit_year']) ? (int) $source_profile['explicit_year'] : null;

        $available = false;
        $score = null;
        $reason = 'no strong temporal evidence';

        if (in_array($target_class, ['current', 'recent'], true)) {
            $available = true;
            if ($source_class === 'historical') {
                $gap = $source_year !== null ? max(1, (int) gmdate('Y') - $source_year) : 1;
                $score = $gap >= 3 ? 0.10 : ($gap === 2 ? 0.15 : 0.25);
                $reason = 'fresh target vs explicit historical source';
            } elseif ($source_class === 'future') {
                $score = 0.25;
                $reason = 'fresh target vs future-dated source';
            } elseif (in_array($source_class, ['current', 'recent'], true)) {
                $score = $source_class === $target_class ? 1.0 : 0.92;
                $reason = 'freshness classes align';
            } else {
                $score = 0.85;
                $reason = 'no conflicting explicit time marker';
            }
        } elseif ($target_class === 'historical' && $target_year !== null) {
            $available = true;
            if ($source_year !== null) {
                $gap = abs($target_year - $source_year);
                $score = $gap === 0 ? 1.0 : ($gap === 1 ? 0.65 : ($gap === 2 ? 0.35 : 0.15));
                $reason = $gap === 0 ? 'explicit archive year aligns' : 'explicit archive years differ';
            } elseif (in_array($source_class, ['current', 'recent'], true)) {
                $score = 0.45;
                $reason = 'historical target vs fresh source';
            } else {
                $score = 0.65;
                $reason = 'historical target with undated source';
            }
        } elseif ($target_class === 'future' && $target_year !== null) {
            $available = true;
            if ($source_year !== null) {
                $gap = abs($target_year - $source_year);
                $score = $gap === 0 ? 1.0 : ($gap === 1 ? 0.60 : 0.25);
                $reason = $gap === 0 ? 'future year aligns' : 'future years differ';
            } else {
                $score = 0.55;
                $reason = 'future target with undated source';
            }
        } elseif ($target_year !== null && $source_year !== null) {
            $available = true;
            $gap = abs($target_year - $source_year);
            $score = $gap === 0 ? 1.0 : ($gap === 1 ? 0.65 : ($gap === 2 ? 0.35 : 0.15));
            $reason = $gap === 0 ? 'explicit years align' : 'explicit years differ';
        }

        $signal = [
            'available' => $available,
            'score' => $available && $score !== null ? self::clamp01((float) $score) : null,
            'reason' => $reason,
        ];

        $filtered = apply_filters('sia_fknn_temporal_signal', $signal, $target_profile, $source_profile, $target, $source);
        if (!is_array($filtered)) {
            return $signal;
        }

        $filtered['available'] = !empty($filtered['available']);
        $filtered['score'] = $filtered['available'] && isset($filtered['score']) && is_numeric($filtered['score'])
            ? self::clamp01((float) $filtered['score'])
            : null;
        $filtered['reason'] = isset($filtered['reason']) ? (string) $filtered['reason'] : $reason;
        return $filtered;
    }

    /** @param array<string,array<string,mixed>> $signals */
    private static function weighted_score(array $signals): float {
        $weighted = 0.0;
        $weight_sum = 0;

        foreach (self::SIGNAL_WEIGHTS as $name => $weight) {
            $signal = $signals[$name] ?? null;
            if (!is_array($signal) || empty($signal['available']) || !isset($signal['score']) || !is_numeric($signal['score'])) {
                continue;
            }
            $weighted += $weight * self::clamp01((float) $signal['score']);
            $weight_sum += $weight;
        }

        return $weight_sum > 0 ? self::clamp01($weighted / $weight_sum) : 0.0;
    }

    /** @param array<int,array<string,mixed>> $recommendations */
    private static function neighborhood_confidence(array $recommendations): float {
        if (!$recommendations) {
            return 0.0;
        }

        $scores = array_map(
            static fn(array $row): float => self::clamp01((float) ($row['score'] ?? 0.0)),
            array_slice($recommendations, 0, 5)
        );
        $fib = [1, 2, 3, 5, 8];
        $weights = array_reverse(array_slice($fib, 0, count($scores)));
        $weighted = 0.0;
        $sum = 0;
        foreach ($scores as $index => $score) {
            $weight = $weights[$index] ?? 1;
            $weighted += $score * $weight;
            $sum += $weight;
        }
        return $sum > 0 ? self::clamp01($weighted / $sum) : 0.0;
    }

    /** @param array<string,mixed> $profile */
    private static function profile_label(array $profile): string {
        $class = strtoupper((string) ($profile['class'] ?? 'undated'));
        $year = isset($profile['explicit_year']) && is_numeric($profile['explicit_year'])
            ? (int) $profile['explicit_year']
            : null;
        return $year !== null ? $class . ' · ' . $year : $class;
    }

    /** @param array<string,mixed> $signal */
    private static function signal_percent(array $signal): string {
        if (empty($signal['available']) || !isset($signal['score']) || !is_numeric($signal['score'])) {
            return 'n/a';
        }
        return self::percent((float) $signal['score']);
    }

    private static function percent(float $value): string {
        return number_format_i18n(self::clamp01($value) * 100, 1) . '%';
    }

    private static function clamp01(float $value): float {
        return max(0.0, min(1.0, $value));
    }
}
