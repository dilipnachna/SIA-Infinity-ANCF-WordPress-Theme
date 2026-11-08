<?php
/** Theme-owned read-only Rank Smart module. */
if (!defined('ABSPATH')) { exit; }
if (!class_exists('Rank_Smart')) {
    define('SIA_NATIVE_RANK_SMART_VERSION', '0.4.1-alpha.1');
    define('SIA_NATIVE_RANK_SMART_MODE', 'read-only');
    require_once __DIR__ . '/rank-smart/class-rank-smart.php';
    require_once __DIR__ . '/rank-smart/class-rank-smart-publisher-bridge.php';
    SIA_Native_Rank_Smart::boot();
    SIA_Native_Rank_Smart_Publisher_Bridge::boot();
}
