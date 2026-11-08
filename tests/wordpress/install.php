<?php
define('WP_INSTALLING', true);
$_SERVER['HTTP_HOST']='127.0.0.1:18087';
$_SERVER['SERVER_NAME']='127.0.0.1';
$_SERVER['REQUEST_URI']='/';
$_SERVER['SERVER_PORT']='18087';
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if (is_blog_installed()) {echo "ALREADY_INSTALLED\n"; exit(0);}
$result = wp_install('SIA ANCF Universal QA', 'qa_admin', 'qa@example.invalid', true, '', wp_generate_password(32, true, true));
echo 'INSTALLED=' . (!empty($result['user_id']) ? 'YES' : 'NO') . PHP_EOL;
echo 'WORDPRESS_VERSION=' . get_bloginfo('version') . PHP_EOL;
