<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Universal, read-only semantic inlink recommendation engine.
 *
 * It discovers source documents that are semantically close to a target URL and
 * ranks source -> target internal-link opportunities with Fibonacci-weighted
 * evidence. It never writes links or mutates post content.
 */
final class SIA_Fibonacci_KNN_Inlinks {
    private const ENGINE_VERSION = '0.5.0-alpha.1';
    private const DEFAULT_CANDIDATE_LIMIT = 1000;
    private const DEFAULT_DISPLAY_LIMIT = 5;
    private const DEFAULT_MAX_K = 21;

    /** @var array<string,int> */
    private const SIGNAL_WEIGHTS = [
        'semantic' => 13,
        'context'  => 8,
        'intent'   => 5,
        'entity'   => 3,
        'value'    => 2,
    ];

    public static function boot(): void {
        add_action('add_meta_boxes', [self::class, 'register_meta_boxes']);
        add_filter('sia_fibonacci_knn_recommendations', [self::class, 'filter_recommendations'], 10, 3);
    }

    public static function register_meta_boxes(string $post_type): void {
        if (!in_array($post_type, ['post', 'page'], true)) {
            return;
        }

        add_meta_box(
            'sia-fibonacci-knn-inlinks',
            'SIA Fibonacci kNN — Inlink Recommendations',
            [self::class, 'render_meta_box'],
            $post_type,
            'normal',
            'default'
        );
    }

    public static function render_meta_box(WP_Post $post): void {
        if ($post->post_status !== 'publish') {
            echo '<p><em>Publish this document before calculating inlink recommendations.</em></p>';
            return;
        }

        $result = self::recommend_inlinks($post, self::DEFAULT_DISPLAY_LIMIT);

        echo '<p><strong>Mode:</strong> read-only · <strong>Formula:</strong> Fibonacci-weighted kNN · <strong>k:</strong> ' . esc_html((string) $result['k']) . ' / ' . esc_html((string) $result['candidate_count']) . ' candidates</p>';
        echo '<p style="color:#646970">Score = available evidence only: 13×semantic + 8×best context + 5×intent + 3×entity/taxonomy + 2×source value. Missing provider evidence is omitted from the denominator, never treated as zero.</p>';

        if (!$result['recommendations']) {
            echo '<p><em>No sufficiently relevant unlinked source document was found in the current candidate neighborhood.</em></p>';
            return;
        }

        echo '<div style="display:grid;gap:10px">';
        foreach ($result['recommendations'] as $rank => $recommendation) {
            $source_id = (int) $recommendation['source_id'];
            $edit_link = get_edit_post_link($source_id);
            $permalink = get_permalink($source_id);
            $score = number_format_i18n(((float) $recommendation['score']) * 100, 1);

            echo '<section style="border:1px solid #dcdcde;border-radius:6px;padding:12px;background:#fff">';
            echo '<div style="display:flex;justify-content:space-between;gap:16px;align-items:start">';
            echo '<div><strong>#' . esc_html((string) ($rank + 1)) . ' ' . esc_html(get_the_title($source_id)) . '</strong>';
            echo '<div style="color:#646970;margin-top:3px">Source → current target · ' . esc_html((string) get_post_type($source_id)) . '</div></div>';
            echo '<strong style="font-size:1.05em">' . esc_html($score) . '%</strong>';
            echo '</div>';

            echo '<p style="margin-bottom:6px"><strong>Evidence:</strong> semantic ' . esc_html(self::percent($recommendation['signals']['semantic']['score'])) . ' · context ' . esc_html(self::signal_percent($recommendation['signals']['context'])) . ' · intent ' . esc_html(self::percent($recommendation['signals']['intent']['score'])) . ' · entity ' . esc_html(self::signal_percent($recommendation['signals']['entity'])) . ' · value ' . esc_html(self::signal_percent($recommendation['signals']['value'])) . '</p>';

            if (!empty($recommendation['context_excerpt'])) {
                echo '<p style="margin:6px 0;padding:8px 10px;background:#f6f7f7;border-left:3px solid #2271b1"><strong>Best insertion context:</strong> ' . esc_html($recommendation['context_excerpt']) . '</p>';
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

        echo '<p style="margin-top:12px"><strong>Neighborhood confidence:</strong> ' . esc_html(self::percent($result['neighborhood_confidence'])) . '. <em>No link is inserted automatically. An editor or future governed execution layer must approve the source, paragraph and natural anchor text.</em></p>';
    }

    public static function filter_recommendations(array $existing, WP_Post $target, int $limit = self::DEFAULT_DISPLAY_LIMIT): array {
        return self::recommend_inlinks($target, $limit);
    }

    /**
     * @return array{k:int,candidate_count:int,neighborhood_confidence:float,recommendations:array<int,array<string,mixed>>,engine_version:string}
     */
    public static function recommend_inlinks(WP_Post $target, int $display_limit = self::DEFAULT_DISPLAY_LIMIT): array {
        $display_limit = max(1, min(13, $display_limit));
        $candidate_ids = self::candidate_ids($target);
        $candidate_count = count($candidate_ids);

        if ($candidate_count === 0) {
            return self::empty_result();
        }

        $target_document = self::document_text($target);
        $target_seed = self::intent_text($target);
        $target_document_vector = self::feature_vector($target_document, $target->ID, 'document');
        $target_seed_vector = self::feature_vector($target_seed, $target->ID, 'intent');

        $semantic_neighbors = [];
        foreach ($candidate_ids as $source_id) {
            $source = get_post($source_id);
            if (!$source instanceof WP_Post || $source->post_status !== 'publish') {
                continue;
            }
            if (self::already_links_to_target($source, $target)) {
                continue;
            }

            $source_vector = self::feature_vector(self::document_text($source), $source->ID, 'document');
            $semantic = self::cosine_similarity($target_document_vector, $source_vector);
            if ($semantic <= 0.0) {
                continue;
            }

            $semantic_neighbors[] = [
                'source' => $source,
                'semantic' => $semantic,
            ];
        }

        usort($semantic_neighbors, static function (array $a, array $b): int {
            return $b['semantic'] <=> $a['semantic'];
        });

        $eligible_count = count($semantic_neighbors);
        if ($eligible_count === 0) {
            return self::empty_result($candidate_count);
        }

        $k = self::fibonacci_k($eligible_count);
        $neighbors = array_slice($semantic_neighbors, 0, $k);
        $target_entities = self::entity_set($target);
        $recommendations = [];

        foreach ($neighbors as $neighbor) {
            /** @var WP_Post $source */
            $source = $neighbor['source'];
            $semantic = self::clamp01((float) $neighbor['semantic']);

            $source_intent_vector = self::feature_vector(self::intent_text($source), $source->ID, 'intent');
            $intent = self::clamp01(self::cosine_similarity($target_seed_vector, $source_intent_vector));

            $context = self::best_context($source, $target_seed_vector);
            $entity = self::entity_similarity($target_entities, self::entity_set($source));
            $value = self::source_value($source, $target);

            $signals = [
                'semantic' => ['available' => true, 'score' => $semantic],
                'context'  => $context['signal'],
                'intent'   => ['available' => true, 'score' => $intent],
                'entity'   => $entity,
                'value'    => $value,
            ];

            $score = self::weighted_score($signals);
            $minimum_score = self::clamp01((float) apply_filters('sia_fknn_minimum_score', 0.18, $target, $source));
            if ($score < $minimum_score) {
                continue;
            }

            $recommendations[] = [
                'source_id' => $source->ID,
                'target_id' => $target->ID,
                'score' => $score,
                'distance' => 1.0 - $semantic,
                'signals' => $signals,
                'context_excerpt' => $context['excerpt'],
                'existing_link' => false,
            ];
        }

        usort($recommendations, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        $recommendations = array_slice($recommendations, 0, $display_limit);

        return [
            'k' => $k,
            'candidate_count' => $candidate_count,
            'neighborhood_confidence' => self::neighborhood_confidence($recommendations),
            'recommendations' => $recommendations,
            'engine_version' => self::ENGINE_VERSION,
        ];
    }

    /** @return array<int,int> */
    private static function candidate_ids(WP_Post $target): array {
        $provided = apply_filters('sia_fknn_candidate_ids', null, $target);
        if (is_array($provided)) {
            $ids = array_values(array_unique(array_filter(array_map('absint', $provided))));
            return array_values(array_filter($ids, static fn(int $id): bool => $id !== (int) $target->ID));
        }

        $limit = max(13, min(5000, (int) apply_filters('sia_fknn_candidate_limit', self::DEFAULT_CANDIDATE_LIMIT, $target)));
        $query = new WP_Query([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__not_in' => [$target->ID],
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
            'fields' => 'ids',
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        return array_map('intval', $query->posts);
    }

    private static function fibonacci_k(int $candidate_count): int {
        if ($candidate_count <= 0) {
            return 0;
        }
        if ($candidate_count <= 3) {
            return $candidate_count;
        }

        $max_k = max(3, min(89, (int) apply_filters('sia_fknn_max_k', self::DEFAULT_MAX_K, $candidate_count)));
        $target = sqrt((float) $candidate_count);
        $sequence = [1, 2];
        while (end($sequence) < max($max_k, $target)) {
            $count = count($sequence);
            $next = $sequence[$count - 1] + $sequence[$count - 2];
            $sequence[] = $next;
            if ($next > 144) {
                break;
            }
        }

        $k = 3;
        foreach ($sequence as $fib) {
            if ($fib > $target || $fib > $max_k) {
                break;
            }
            if ($fib >= 3) {
                $k = $fib;
            }
        }

        return min($candidate_count, $k);
    }

    private static function document_text(WP_Post $post): string {
        $title = get_the_title($post);
        $excerpt = trim((string) $post->post_excerpt);
        if ($excerpt === '') {
            $excerpt = wp_trim_words(wp_strip_all_tags(strip_shortcodes((string) $post->post_content)), 60, '');
        }
        $content = wp_strip_all_tags(strip_shortcodes((string) $post->post_content));
        $content = self::text_substr($content, 0, 12000);

        return trim($title . ' ' . $title . ' ' . $excerpt . ' ' . $content);
    }

    private static function intent_text(WP_Post $post): string {
        $excerpt = trim((string) $post->post_excerpt);
        if ($excerpt === '') {
            $excerpt = wp_trim_words(wp_strip_all_tags(strip_shortcodes((string) $post->post_content)), 40, '');
        }
        return trim(get_the_title($post) . ' ' . $excerpt);
    }

    /** @return array<string,float> */
    private static function feature_vector(string $text, int $object_id, string $role): array {
        $provider_vector = apply_filters('sia_fknn_vector', null, $object_id, $role, $text);
        if (is_array($provider_vector) && $provider_vector) {
            $normalized = [];
            foreach ($provider_vector as $key => $value) {
                if (is_numeric($value)) {
                    $normalized[(string) $key] = (float) $value;
                }
            }
            if ($normalized) {
                return $normalized;
            }
        }

        $normalized_text = self::normalize_text($text);
        if ($normalized_text === '') {
            return [];
        }

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $normalized_text, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = is_array($tokens) ? $tokens : [];
        $counts = [];
        foreach ($tokens as $token) {
            if (self::text_length($token) < 2) {
                continue;
            }
            $key = 'w:' . $token;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        // Languages without whitespace segmentation fall back to character n-grams.
        if (count($counts) < 6) {
            $compact = preg_replace('/\s+/u', '', $normalized_text);
            $compact = is_string($compact) ? self::text_substr($compact, 0, 2000) : '';
            $length = self::text_length($compact);
            for ($i = 0; $i <= $length - 3; $i++) {
                $gram = self::text_substr($compact, $i, 3);
                if ($gram === '') {
                    continue;
                }
                $key = 'g:' . $gram;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        arsort($counts);
        $counts = array_slice($counts, 0, 700, true);
        $vector = [];
        foreach ($counts as $key => $count) {
            $vector[$key] = 1.0 + log((float) $count);
        }

        return $vector;
    }

    /** @param array<string,float> $a @param array<string,float> $b */
    private static function cosine_similarity(array $a, array $b): float {
        if (!$a || !$b) {
            return 0.0;
        }

        $dot = 0.0;
        $norm_a = 0.0;
        $norm_b = 0.0;
        foreach ($a as $key => $value) {
            $norm_a += $value * $value;
            if (isset($b[$key])) {
                $dot += $value * $b[$key];
            }
        }
        foreach ($b as $value) {
            $norm_b += $value * $value;
        }

        if ($norm_a <= 0.0 || $norm_b <= 0.0) {
            return 0.0;
        }

        return self::clamp01($dot / (sqrt($norm_a) * sqrt($norm_b)));
    }

    /** @param array<string,float> $target_seed_vector @return array{signal:array{available:bool,score:?float},excerpt:string} */
    private static function best_context(WP_Post $source, array $target_seed_vector): array {
        $paragraphs = self::paragraphs((string) $source->post_content);
        $best_score = 0.0;
        $best_excerpt = '';

        foreach ($paragraphs as $paragraph) {
            $vector = self::feature_vector($paragraph, $source->ID, 'context');
            $score = self::cosine_similarity($target_seed_vector, $vector);
            if ($score > $best_score) {
                $best_score = $score;
                $best_excerpt = self::text_substr($paragraph, 0, 280);
            }
        }

        if ($best_excerpt === '') {
            return [
                'signal' => ['available' => false, 'score' => null],
                'excerpt' => '',
            ];
        }

        return [
            'signal' => ['available' => true, 'score' => self::clamp01($best_score)],
            'excerpt' => $best_excerpt,
        ];
    }

    /** @return array<int,string> */
    private static function paragraphs(string $html): array {
        $html = strip_shortcodes($html);
        $html = preg_replace('/<\/(p|div|h[1-6]|li|blockquote)>/i', "\n", $html);
        $text = wp_strip_all_tags(is_string($html) ? $html : '');
        $parts = preg_split('/[\r\n]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $parts = is_array($parts) ? $parts : [];
        $paragraphs = [];

        foreach ($parts as $part) {
            $part = trim(preg_replace('/\s+/u', ' ', $part) ?? '');
            $length = self::text_length($part);
            if ($length < 40) {
                continue;
            }
            $paragraphs[] = self::text_substr($part, 0, 900);
            if (count($paragraphs) >= 80) {
                break;
            }
        }

        return $paragraphs;
    }

    /** @return array<string,bool> */
    private static function entity_set(WP_Post $post): array {
        $set = [];
        $taxonomies = get_object_taxonomies($post->post_type, 'names');
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($terms) || !is_array($terms)) {
                continue;
            }
            foreach ($terms as $term_id) {
                $set[$taxonomy . ':' . (int) $term_id] = true;
            }
        }

        $primary_silo = (int) get_post_meta($post->ID, '_sia_primary_silo_term_id', true);
        if ($primary_silo > 0) {
            $set['category:' . $primary_silo] = true;
        }

        return $set;
    }

    /** @param array<string,bool> $a @param array<string,bool> $b @return array{available:bool,score:?float} */
    private static function entity_similarity(array $a, array $b): array {
        if (!$a || !$b) {
            return ['available' => false, 'score' => null];
        }
        $intersection = count(array_intersect_key($a, $b));
        $union = count($a + $b);
        return [
            'available' => true,
            'score' => $union > 0 ? self::clamp01($intersection / $union) : 0.0,
        ];
    }

    /** @return array{available:bool,score:?float,evidence?:mixed} */
    private static function source_value(WP_Post $source, WP_Post $target): array {
        $value = apply_filters('sia_fknn_source_value', [
            'available' => false,
            'score' => null,
            'evidence' => [],
        ], $source, $target);

        if (!is_array($value) || empty($value['available']) || !isset($value['score']) || !is_numeric($value['score'])) {
            return ['available' => false, 'score' => null, 'evidence' => []];
        }

        return [
            'available' => true,
            'score' => self::clamp01((float) $value['score']),
            'evidence' => $value['evidence'] ?? [],
        ];
    }

    /** @param array<string,array<string,mixed>> $signals */
    private static function weighted_score(array $signals): float {
        $weighted = 0.0;
        $weight_sum = 0;

        foreach (self::SIGNAL_WEIGHTS as $name => $weight) {
            if (!isset($signals[$name]) || empty($signals[$name]['available']) || !isset($signals[$name]['score']) || !is_numeric($signals[$name]['score'])) {
                continue;
            }
            $weighted += $weight * self::clamp01((float) $signals[$name]['score']);
            $weight_sum += $weight;
        }

        return $weight_sum > 0 ? self::clamp01($weighted / $weight_sum) : 0.0;
    }

    /** @param array<int,array<string,mixed>> $recommendations */
    private static function neighborhood_confidence(array $recommendations): float {
        if (!$recommendations) {
            return 0.0;
        }

        $scores = array_map(static fn(array $row): float => self::clamp01((float) $row['score']), array_slice($recommendations, 0, 5));
        $m = count($scores);
        $fib = [1, 2, 3, 5, 8];
        $weights = array_reverse(array_slice($fib, 0, $m));
        $weighted = 0.0;
        $sum = 0;
        foreach ($scores as $index => $score) {
            $weight = $weights[$index] ?? 1;
            $weighted += $score * $weight;
            $sum += $weight;
        }
        return $sum > 0 ? self::clamp01($weighted / $sum) : 0.0;
    }

    private static function already_links_to_target(WP_Post $source, WP_Post $target): bool {
        $target_url = get_permalink($target);
        if (!is_string($target_url) || $target_url === '') {
            return false;
        }
        $content = (string) $source->post_content;
        if (stripos($content, $target_url) !== false) {
            return true;
        }

        $path = (string) parse_url($target_url, PHP_URL_PATH);
        if ($path === '') {
            return false;
        }
        $pattern = '/href\s*=\s*["\'][^"\']*' . preg_quote($path, '/') . '[^"\']*["\']/i';
        return preg_match($pattern, $content) === 1;
    }

    private static function normalize_text(string $text): string {
        $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (class_exists('Normalizer')) {
            $normalized = Normalizer::normalize($text, Normalizer::FORM_KC);
            if (is_string($normalized)) {
                $text = $normalized;
            }
        }
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim(is_string($text) ? $text : '');
    }

    private static function clamp01(float $value): float {
        return max(0.0, min(1.0, $value));
    }

    private static function percent(float $value): string {
        return number_format_i18n(self::clamp01($value) * 100, 1) . '%';
    }

    /** @param array<string,mixed> $signal */
    private static function signal_percent(array $signal): string {
        if (empty($signal['available']) || !isset($signal['score']) || !is_numeric($signal['score'])) {
            return 'n/a';
        }
        return self::percent((float) $signal['score']);
    }

    private static function text_length(string $text): int {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    private static function text_substr(string $text, int $start, int $length): string {
        return function_exists('mb_substr') ? (string) mb_substr($text, $start, $length, 'UTF-8') : substr($text, $start, $length);
    }

    /** @return array{k:int,candidate_count:int,neighborhood_confidence:float,recommendations:array<int,array<string,mixed>>,engine_version:string} */
    private static function empty_result(int $candidate_count = 0): array {
        return [
            'k' => 0,
            'candidate_count' => $candidate_count,
            'neighborhood_confidence' => 0.0,
            'recommendations' => [],
            'engine_version' => self::ENGINE_VERSION,
        ];
    }
}
