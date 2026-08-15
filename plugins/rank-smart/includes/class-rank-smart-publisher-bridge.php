<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Rank_Smart_Publisher_Bridge {
    public static function boot(): void {
        add_action('sia_publisher_intelligence_after_sections', [self::class, 'render_details'], 20, 2);
    }

    public static function render_details(WP_Post $post, array $context): void {
        $audit = isset($context['rank_smart']) && is_array($context['rank_smart'])
            ? $context['rank_smart']
            : Rank_Smart::audit_post($post);

        $risk = $audit['change_risk'] ?? ['level' => 'UNKNOWN', 'score' => 0, 'meaning' => ''];

        echo '<div style="margin-top:12px;border:1px solid #dcdcde;background:#f6f7f7;padding:12px;border-radius:6px">';
        echo '<strong>Rank Smart read-only evidence</strong>';
        echo '<span style="margin-left:12px">Change risk: <strong>' . esc_html((string) ($risk['level'] ?? 'UNKNOWN')) . ' (' . esc_html((string) ($risk['score'] ?? 0)) . '/100)</strong></span>';
        echo '<span style="margin-left:12px">SEO authority: <strong>' . esc_html((string) ($audit['seo_authority'] ?? 'Unknown')) . '</strong></span>';
        echo '<span style="margin-left:12px">URL observations: <strong>' . esc_html((string) ($audit['url_history']['count'] ?? 0)) . '</strong></span>';
        echo '<p style="margin:6px 0 0;color:#646970">This score estimates the evidence-based risk of changing or removing the URL. It is not a ranking score and does not authorize an SEO change.</p>';
        echo '</div>';
    }
}
