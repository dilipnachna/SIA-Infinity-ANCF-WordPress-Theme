<?php
/**
 * Plugin Name: SIA Semantic Intelligence
 * Description: Explicit Primary Semantic Silo metadata and read-only semantic foundations.
 * Version: 0.3.0-alpha.1
 * Requires PHP: 8.1
 * Author: SIA Infinity
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-sia-semantic-intelligence.php';
SIA_Semantic_Intelligence::boot();
