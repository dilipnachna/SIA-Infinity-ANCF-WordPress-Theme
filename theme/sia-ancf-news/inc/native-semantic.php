<?php
/** Native primary-silo metadata; Related Stories uses its own four-signal ranker. */
if (!defined('ABSPATH')) { exit; }
if (!class_exists('SIA_Semantic_Intelligence')) {
    require_once __DIR__ . '/semantic/class-sia-semantic-intelligence.php';
    SIA_Native_Semantic_Intelligence::boot();
}
