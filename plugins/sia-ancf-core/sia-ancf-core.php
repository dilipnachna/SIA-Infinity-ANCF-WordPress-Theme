<?php
/**
 * Plugin Name: SIA ANCF Core
 * Description: Publisher-intelligence foundation for SIA Infinity ANCF.
 * Version: 0.4.1-alpha.1
 * Requires PHP: 8.1
 * Author: SIA Infinity
 * Text Domain: sia-ancf-core
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SIA_ANCF_CORE_VERSION', '0.4.1-alpha.1');
define('SIA_ANCF_RUNTIME_MODE', 'publisher-intelligence');

require_once __DIR__ . '/includes/class-sia-ancf-core.php';

SIA_ANCF_Core::boot();
