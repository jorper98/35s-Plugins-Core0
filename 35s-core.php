<?php
/*
Plugin Name: 35s Core
Plugin URI: https://35sites.com/wordpress-plugins/
Description: Core functionality for all 35s plugins. Provides centralized menu management, shared utilities, and automatic updates from GitHub.
Version: 1.0.1
Author: Jorge Pereira (35sites)
Author URI: https://35sites.com/wordpress-plugins/
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: 35s-core
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('S35_CORE_VERSION', '1.0.1');
define('S35_CORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('S35_CORE_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load the menu manager class
if (!class_exists('S35_Menu_Manager')) {
    require_once S35_CORE_PLUGIN_DIR . 'includes/class-s35-menu-manager.php';
}

/**
 * Main S35 Core Plugin Class
 */
class S35_Core {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    public function init() {
        // Initialize menu manager
        S35_Menu_Manager::getInstance();
        
        // Hook for other 35s plugins to use
        do_action('s35_core_loaded');
        
        // Load text domain
        load_plugin_textdomain('35s-core', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    public function activate() {
        update_option('s35_core_installed', true);
        update_option('s35_core_version', S35_CORE_VERSION);
        update_option('s35_core_auto_generated', true);
        update_option('s35_core_activated', current_time('mysql'));
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Keep options for other plugins that might depend on core
        // Only cleanup when no 35s plugins are active
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public static function is_core_active() {
        return get_option('s35_core_installed', false);
    }
    
    /**
     * Admin notices for various core states
     */
    public function admin_notices() {
        // GitHub update notice
        if (get_option('s35_core_github_update_notice')) {
            $version = get_option('s35_core_github_update_notice');
            echo '<div class="notice notice-success is-dismissible">
                <p><strong>35s Core Updated:</strong> Successfully updated to version ' . esc_html($version) . ' from GitHub.</p>
            </div>';
            delete_option('s35_core_github_update_notice');
        }
        
        // Cleanup notice
        if (get_option('s35_core_cleanup_notice')) {
            echo '<div class="notice notice-info is-dismissible">
                <p><strong>35s Core:</strong> Core plugin has been deactivated as no 35s plugins are currently active.</p>
            </div>';
            delete_option('s35_core_cleanup_notice');
        }
        
        // Auto-generation notice (only on plugins page)
        if (get_option('s35_core_auto_generated') && current_user_can('manage_options')) {
            $screen = get_current_screen();
            if ($screen && $screen->id === 'plugins') {
                $install_method = get_option('s35_core_installed_from_github') ? 'GitHub' : 'locally';
                echo '<div class="notice notice-info is-dismissible">
                    <p><strong>35s Core:</strong> This plugin was automatically installed ' . esc_html($install_method) . ' to support your 35s plugins suite. It manages shared functionality and can be safely left active.</p>
                </div>';
            }
        }
        
        // Installation success notice
        if (get_option('s35_core_install_success_notice')) {
            echo '<div class="notice notice-success is-dismissible">
                <p><strong>35s Core Installed:</strong> Core plugin has been successfully installed and activated. Your 35s plugins are now ready to use!</p>
            </div>';
            delete_option('s35_core_install_success_notice');
        }
        
        // GitHub connection error notice
        if (get_option('s35_core_github_error_notice')) {
            echo '<div class="notice notice-warning is-dismissible">
                <p><strong>35s Core:</strong> Could not connect to GitHub for updates. Using local fallback. Check your internet connection.</p>
            </div>';
            delete_option('s35_core_github_error_notice');
        }
    }
    
    /**
     * Get core plugin information
     */
    public static function get_plugin_info() {
        return array(
            'version' => S35_CORE_VERSION,
            'installed_from_github' => get_option('s35_core_installed_from_github', false),
            'created_locally' => get_option('s35_core_created_locally', false),
            'last_update_check' => get_option('s35_core_last_update_check', 0),
            'activated_date' => get_option('s35_core_activated', ''),
            'auto_generated' => get_option('s35_core_auto_generated', false)
        );
    }
    
    /**
     * Check if specific 35s plugin is active
     */
    public static function is_plugin_active($plugin_slug) {
        $active_plugins = get_option('active_plugins', array());
        
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, $plugin_slug . '/') === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get list of active 35s plugins
     */
    public static function get_active_35s_plugins() {
        $plugins = array();
        
        $plugin_definitions = array(
            '35s-post-gaps' => array(
                'name' => '35s Post Gaps',
                'description' => 'Manages post gaps and spacing for better content layout.',
                'admin_url' => admin_url('admin.php?page=35s-post-gaps')
            ),
            '35s-request-lister' => array(
                'name' => '35s Request Lister',
                'description' => 'Lists and manages various types of requests efficiently.',
                'admin_url' => admin_url('admin.php?page=35s-request-lister')
            ),
            '35s-smart-tag-blocks' => array(
                'name' => '35s Smart Tag Blocks',
                'description' => 'Advanced tag management with intelligent block features.',
                'admin_url' => admin_url('admin.php?page=35s-smart-tag-blocks')
            ),
            '35sSecureFileDownload' => array(
                'name' => '35s Secure File Download',
                'description' => 'Secure file download management with access controls.',
                'admin_url' => admin_url('admin.php?page=35sSecureFileDownload')
            )
        );
        
        foreach ($plugin_definitions as $plugin_slug => $plugin_info) {
            if (self::is_plugin_active($plugin_slug)) {
                $plugins[] = $plugin_info;
            }
        }
        
        return $plugins;
    }
}

// Initialize the core plugin
S35_Core::getInstance();

/**
 * Helper function for other plugins to check core availability
 */
function s35_core_available() {
    return class_exists('S35_Core') && S35_Core::is_core_active();
}

/**
 * Helper function to get core plugin info
 */
function s35_get_core_info() {
    if (class_exists('S35_Core')) {
        return S35_Core::get_plugin_info();
    }
    return false;
}

/**
 * Helper function to check if specific 35s plugin is active
 */
function s35_is_plugin_active($plugin_slug) {
    if (class_exists('S35_Core')) {
        return S35_Core::is_plugin_active($plugin_slug);
    }
    return false;
}

/**
 * Uninstall hook - only runs when plugin is deleted
 */
register_uninstall_hook(__FILE__, 's35_core_uninstall');

function s35_core_uninstall() {
    // Only clean up if no other 35s plugins are active
    $active_plugins = get_option('active_plugins', array());
    $s35_plugins_active = false;
    
    $s35_plugin_patterns = array('35s-post-gaps/', '35s-request-lister/', '35s-smart-tag-blocks/', '35sSecureFileDownload/');
    
    foreach ($active_plugins as $plugin) {
        foreach ($s35_plugin_patterns as $pattern) {
            if (strpos($plugin, $pattern) === 0) {
                $s35_plugins_active = true;
                break 2;
            }
        }
    }
    
    if (!$s35_plugins_active) {
        // Clean up options
        delete_option('s35_core_installed');
        delete_option('s35_core_version');
        delete_option('s35_core_auto_generated');
        delete_option('s35_core_activated');
        delete_option('s35_core_installed_from_github');
        delete_option('s35_core_created_locally');
        delete_option('s35_core_last_update_check');
        delete_option('s35_core_github_install_date');
        
        // Clean up any remaining notices
        delete_option('s35_core_github_update_notice');
        delete_option('s35_core_cleanup_notice');
        delete_option('s35_core_install_success_notice');
        delete_option('s35_core_github_error_notice');
    }
}

/**
 * Add settings link to plugins page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 's35_core_action_links');

function s35_core_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=35s-plugins') . '">' . __('Dashboard', '35s-core') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Add plugin row meta
 */
add_filter('plugin_row_meta', 's35_core_row_meta', 10, 2);

function s35_core_row_meta($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $row_meta = array(
            'support' => '<a href="https://35sites.com/support" target="_blank">' . __('Support', '35s-core') . '</a>',
            'more_plugins' => '<a href="https://35sites.com/wordpress-plugins/" target="_blank">' . __('More Plugins', '35s-core') . '</a>'
        );
        
        if (get_option('s35_core_installed_from_github')) {
            $row_meta['github'] = '<a href="https://github.com/your-username/35s-core-plugin" target="_blank">' . __('View on GitHub', '35s-core') . '</a>';
        }
        
        return array_merge($links, $row_meta);
    }
    return $links;
}

?>
