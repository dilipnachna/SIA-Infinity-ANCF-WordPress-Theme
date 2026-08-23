<?php
/**
 * Plugin Name: SIA Semantic Intelligence
 * Description: Universal semantic graph foundations with read-only Fibonacci kNN inlink recommendations.
 * Version: 0.4.2-alpha.1
 * Requires PHP: 8.1
 * Author: SIA Infinity
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-sia-semantic-intelligence.php';
require_once __DIR__ . '/includes/class-sia-unicode-vector-provider.php';
require_once __DIR__ . '/includes/class-sia-fibonacci-knn-inlinks.php';
require_once __DIR__ . '/includes/class-sia-fknn-temporal-intent.php';
require_once __DIR__ . '/includes/class-sia-fknn-related-content-bridge.php';
require_once __DIR__ . '/includes/class-sia-fknn-surface-guard.php';
require_once __DIR__ . '/includes/class-sia-fknn-freshness-age-guard.php';
require_once __DIR__ . '/includes/class-sia-fknn-related-age-diversity-guard.php';

SIA_Semantic_Intelligence::boot();
SIA_Unicode_Vector_Provider::boot();
SIA_Fibonacci_KNN_Inlinks::boot();
SIA_FKNN_Temporal_Intent::boot();
SIA_FKNN_Related_Content_Bridge::boot();
SIA_FKNN_Surface_Guard::boot();
SIA_FKNN_Freshness_Age_Guard::boot();
SIA_FKNN_Related_Age_Diversity_Guard::boot();
