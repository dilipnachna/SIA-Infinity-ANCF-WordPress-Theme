<?php
/**
 * Plugin Name: SIA URL Memory
 * Description: Append-only observed URL history for WordPress content. No automatic redirects in the current read-only release.
 * Version: 0.4.2-alpha.1
 * Requires PHP: 8.1
 * Author: SIA Infinity
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SIA_URL_MEMORY_VERSION', '0.4.2-alpha.1');
require_once __DIR__ . '/includes/class-sia-url-memory.php';
register_activation_hook(__FILE__, ['SIA_URL_Memory', 'activate']);
SIA_URL_Memory::boot();
