<?php
/**
 * Plugin Name: SIA Entity & Author
 * Description: Structured author/newsroom entity metadata for ANCF. No public schema output in v0.1.
 * Version: 0.1.0-alpha.1
 * Requires PHP: 8.1
 * Author: SIA Infinity
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-sia-entity-author.php';
SIA_Entity_Author::boot();
