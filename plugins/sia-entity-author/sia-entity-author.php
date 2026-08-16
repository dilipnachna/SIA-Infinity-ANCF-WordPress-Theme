<?php
/**
 * Plugin Name: SIA Entity & Author
 * Description: Structured author/newsroom entity metadata for ANCF. Public schema authority remains outside this module in the current release.
 * Version: 0.4.2-alpha.1
 * Requires PHP: 8.1
 * Author: SIA Infinity
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-sia-entity-author.php';
SIA_Entity_Author::boot();
