<?php
/** Theme-owned copy of the local SIA semantic module. */
if (!defined('ABSPATH')) { exit; }
if (!class_exists('SIA_Semantic_Intelligence')) {
    require_once __DIR__ . '/semantic/class-sia-semantic-intelligence.php';
    require_once __DIR__ . '/semantic/class-sia-unicode-vector-provider.php';
    require_once __DIR__ . '/semantic/class-sia-fibonacci-knn-inlinks.php';
    require_once __DIR__ . '/semantic/class-sia-fknn-temporal-intent.php';
    require_once __DIR__ . '/semantic/class-sia-fknn-related-content-bridge.php';
    require_once __DIR__ . '/semantic/class-sia-fknn-surface-guard.php';
    require_once __DIR__ . '/semantic/class-sia-fknn-freshness-age-guard.php';
    require_once __DIR__ . '/semantic/class-sia-fknn-related-age-diversity-guard.php';
    SIA_Native_Semantic_Intelligence::boot();
    SIA_Native_Unicode_Vector_Provider::boot();
    SIA_Native_Fibonacci_KNN_Inlinks::boot();
    SIA_Native_FKNN_Temporal_Intent::boot();
    SIA_Native_FKNN_Related_Content_Bridge::boot();
    SIA_Native_FKNN_Surface_Guard::boot();
    SIA_Native_FKNN_Freshness_Age_Guard::boot();
    SIA_Native_FKNN_Related_Age_Diversity_Guard::boot();
}
