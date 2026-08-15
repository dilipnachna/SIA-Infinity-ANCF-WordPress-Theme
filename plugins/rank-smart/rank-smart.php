<?php
/**
 * Plugin Name: Rank Smart
 * Description: Read-only SEO intelligence for SIA Infinity ANCF. Audits URL, content, indexability candidates, URL history and provider evidence without taking over SEO output.
 * Version: 0.4.0-alpha.1
 * Requires PHP: 8.1
 * Author: SIA Infinity
 * Text Domain: rank-smart
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RANK_SMART_VERSION', '0.4.0-alpha.1');
define('RANK_SMART_MODE', 'read-only');
define('RANK_SMART_PATH', plugin_dir_path(__FILE__));

require_once RANK_SMART_PATH . 'includes/class-rank-smart.php';
require_once RANK_SMART_PATH . 'includes/class-rank-smart-publisher-bridge.php';

Rank_Smart::boot();
Rank_Smart_Publisher_Bridge::boot();
