<?php
/** Theme-owned editorial and classification fallback. Ads use the native placement module. */
if (!defined('ABSPATH')) { exit; }
if (!class_exists('SIA_ANCF_Core')) {
    define('SIA_NATIVE_ANCF_CORE_VERSION', '0.4.2-alpha.1');
    define('SIA_NATIVE_ANCF_RUNTIME_MODE', 'publisher-intelligence');
    define('SIA_NATIVE_ANCF_EDITORIAL_VERSION', '0.2.0');
    require_once __DIR__ . '/ancf-core/class-sia-ancf-core.php';
    require_once __DIR__ . '/ancf-core/editorial/record.php';
    require_once __DIR__ . '/ancf-core/editorial/workspace.php';
    SIA_Native_ANCF_Core::boot();
    \SIA\ANCF\ThemeEditorial\Workspace::boot(__FILE__);
    \SIA\ANCF\ThemeEditorial\Workspace::retireStandaloneHooks();
}
