<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Built-in universal lexical vector provider.
 *
 * External embedding/vector providers can run at an earlier filter priority and
 * return a vector first; this provider preserves any vector already supplied.
 */
final class SIA_Unicode_Vector_Provider {
    public static function boot(): void {
        add_filter('sia_fknn_vector', [self::class, 'provide'], 100, 4);
    }

    /** @param mixed $existing @return array<string,float>|mixed */
    public static function provide($existing, int $object_id, string $role, string $text) {
        if (is_array($existing) && $existing) {
            return $existing;
        }

        static $request_cache = [];
        $cache_key = $object_id . '|' . $role . '|' . md5($text);
        if (isset($request_cache[$cache_key])) {
            return $request_cache[$cache_key];
        }

        $normalized = self::normalize($text);
        if ($normalized === '') {
            return [];
        }

        // \p{M} keeps combining marks attached to their script characters.
        $tokens = preg_split('/[^\p{L}\p{M}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = is_array($tokens) ? $tokens : [];
        $counts = [];

        foreach ($tokens as $token) {
            if (self::length($token) < 2) {
                continue;
            }
            $key = 'w:' . $token;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        // Scripts with weak/no whitespace segmentation receive character trigrams.
        if (count($counts) < 6) {
            $compact = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '', $normalized);
            $compact = is_string($compact) ? self::substring($compact, 0, 2000) : '';
            $length = self::length($compact);
            for ($i = 0; $i <= $length - 3; $i++) {
                $gram = self::substring($compact, $i, 3);
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

        $request_cache[$cache_key] = $vector;
        return $vector;
    }

    private static function normalize(string $text): string {
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

    private static function length(string $text): int {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    private static function substring(string $text, int $start, int $length): string {
        return function_exists('mb_substr') ? (string) mb_substr($text, $start, $length, 'UTF-8') : substr($text, $start, $length);
    }
}
