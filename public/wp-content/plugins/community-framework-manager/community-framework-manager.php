<?php

/**
 * Plugin Name: Community Framework Manager
 * Description: Reusable framework/axis/term manager for community profile taxonomies and recommendations.
 * Version: 0.1.0
 * Author: Teachers.Net
 */

if (!defined('ABSPATH')) {
  exit;
}

define('CFM_VERSION', '0.1.0');
define('CFM_PLUGIN_FILE', __FILE__);
define('CFM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CFM_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CFM_PLUGIN_DIR . 'includes/class-cfm-schema.php';
require_once CFM_PLUGIN_DIR . 'includes/class-cfm-framework-repository.php';
require_once CFM_PLUGIN_DIR . 'includes/class-cfm-activator.php';
require_once CFM_PLUGIN_DIR . 'admin/class-cfm-admin.php';

register_activation_hook(__FILE__, ['CFM_Activator', 'activate']);
if (is_admin()) {
  CFM_Admin::init();
}
