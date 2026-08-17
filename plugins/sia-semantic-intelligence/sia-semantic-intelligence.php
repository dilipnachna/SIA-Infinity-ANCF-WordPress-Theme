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
require_once __DIR__ . '/includes/class-sia-fibonacci-knn-inlinks.php';

SIA_Semantic_Intelligence::boot();
SIA_Fibonacci_KNN_Inlinks::boot();
