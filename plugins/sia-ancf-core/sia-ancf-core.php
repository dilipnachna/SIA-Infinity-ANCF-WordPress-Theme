<?php
/**
 * Plugin Name: SIA ANCF Core
 * Description: Observe-first foundation for SIA Infinity ANCF publishing intelligence.
 * Version: 0.1.0-alpha.1
 * Requires PHP: 8.1
 * Author: SIA Infinity
 * Text Domain: sia-ancf-core
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SIA_ANCF_CORE_VERSION', '0.1.0-alpha.1');
define('SIA_ANCF_RUNTIME_MODE', 'observe');

require_once __DIR__ . '/includes/class-sia-ancf-core.php';

SIA_ANCF_Core::boot();
