<?php
/**
 * GitHub Core Installer for 35s Plugins
 * Save this as: includes/class-s35-github-core-installer.php
 */

if (!class_exists('S35_GitHub_Core_Installer')) {
    class S35_GitHub_Core_Installer {
        
        private static $core_plugin_slug = '35s-core';
        private static $core_plugin_file = '35s-core/35s-core.php';
        
        // Configure your GitHub repository here
        private static $github_config = array(
            'repo_owner' => 'jorgep',                    // Replace with your GitHub username
            'repo_name' => '35s-core-plugin',            // Replace with your repo name
            'branch' => 'main',                          // or 'master' if that's your default branch
            'base_url' => 'https://raw.githubusercontent.com/jorgep/35s-core-plugin/main/', // Update with your repo
        );
        
        /**
         * Main method to ensure core plugin exists
         */
        public static function ensure_core_plugin() {
            $core_path = WP_PLUGIN_DIR . '/' . self::$core_plugin_file;
            
            // Check if core plugin already exists
            if (file_exists($core_path)) {
                // Optionally check for updates (once per day)
                if (self::should_check_for_updates()) {
                    self::check_and_update_core();
                }
                
                // Make sure it's activated
                if (!is_plugin_active(self::$core_plugin_file)) {
                    activate_plugin(self::$core_plugin_file);
                }
                return true;
            }
            
            // Core doesn't exist, download and install it
            if (self::download_and_install_core()) {
                activate_plugin(self::$core_plugin_file);
                return true;
            }
            
            // If GitHub download fails, try fallback creation
            return self::create_fallback_core();
        }
        
        /**
         * Download and install core plugin from GitHub
         */
        private static function download_and_install_core() {
            $core_dir = WP_PLUGIN_DIR . '/' . self::$core_plugin_slug;
            $includes_dir = $core_dir . '/includes';
            
            // Create directories
            if (!wp_mkdir_p($core_dir) || !wp_mkdir_p($includes_dir)) {
                return false;
            }
            
            // Files to download from GitHub
            $files_to_download = array(
                '35s-core.php' => $core_dir . '/35s-core.php',
                'includes/class-s35-menu-manager.php' => $includes_dir . '/class-s35-menu-manager.php',
                'README.txt' => $core_dir . '/README.txt'
            );
            
            $success = true;
            foreach ($files_to_download as $github_path => $local_path) {
                $github_url = self::$github_config['base_url'] . $github_path;
                
                if (!self::download_file($github_url, $local_path)) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                // Set proper permissions
                self::set_plugin_permissions($core_dir);
                
                // Log successful installation
                update_option('s35_core_installed_from_github', true);
                update_option('s35_core_github_install_date', current_time('mysql'));
                update_option('s35_core_last_update_check', time());
                
                return true;
            }
            
            // Clean up on failure
            self::remove_directory($core_dir);
            return false;
        }
        
        /**
         * Download file from GitHub
         */
        private static function download_file($url, $local_path) {
            $response = wp_remote_get($url, array(
                'timeout' => 30,
                'user-agent' => 'WordPress/35s-plugins-installer'
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                return false;
            }
            
            // Ensure directory exists
            $dir = dirname($local_path);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
            
            return file_put_contents($local_path, $body) !== false;
        }
        
        /**
         * Check if we should look for updates (once per day)
         */
        private static function should_check_for_updates() {
            $last_check = get_option('s35_core_last_update_check', 0);
            return (time() - $last_check) > DAY_IN_SECONDS;
        }
        
        /**
         * Check GitHub for updates and download if newer version exists
         */
        private static function check_and_update_core() {
            // Get current version
            $current_version = get_option('s35_core_version', '1.0.0');
            
            // Try to get version from GitHub (you can create a version.json file)
            $version_url = self::$github_config['base_url'] . 'version.json';
            $response = wp_remote_get($version_url, array('timeout' => 10));
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $version_data = json_decode(wp_remote_retrieve_body($response), true);
                
                if (isset($version_data['version']) && version_compare($version_data['version'], $current_version, '>')) {
                    // Backup current core
                    self::backup_current_core();
                    
                    // Download and install new version
                    if (self::download_and_install_core()) {
                        update_option('s35_core_updated_from_github', current_time('mysql'));
                        
                        // Show update notice
                        add_option('s35_core_github_update_notice', $version_data['version']);
                    }
                }
            }
            
            update_option('s35_core_last_update_check', time());
        }
        
        /**
         * Create fallback core plugin if GitHub download fails
         */
        private static function create_fallback_core() {
            $core_dir = WP_PLUGIN_DIR . '/' . self::$core_plugin_slug;
            $includes_dir = $core_dir . '/includes';
            
            if (!wp_mkdir_p($core_dir) || !wp_mkdir_p($includes_dir)) {
                return false;
            }
            
            // Create main plugin file
            $main_content = self::get_fallback_main_content();
            if (!file_put_contents($core_dir . '/35s-core.php', $main_content)) {
                return false;
            }
            
            // Create menu manager class
            $menu_content = self::get_fallback_menu_manager_content();
            if (!file_put_contents($includes_dir . '/class-s35-menu-manager.php', $menu_content)) {
                return false;
            }
            
            // Create readme
            $readme_content = self::get_fallback_readme_content();
            file_put_contents($core_dir . '/README.txt', $readme_content);
            
            update_option('s35_core_created_locally', true);
            return true;
        }
        
        /**
         * Backup current core before updating
         */
        private static function backup_current_core() {
            $core_dir = WP_PLUGIN_DIR . '/' . self::$core_plugin_slug;
            $backup_dir = WP_PLUGIN_DIR . '/' . self::$core_plugin_slug . '-backup-' . date('Y-m-d-H-i-s');
            
            if (file_exists($core_dir)) {
                self::copy_directory($core_dir, $backup_dir);
            }
        }
        
        /**
         * Set proper file permissions
         */
        private static function set_plugin_permissions($dir) {
            if (is_dir($dir)) {
                chmod($dir, 0755);
                
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
                foreach ($files as $file) {
                    if ($file->isFile()) {
                        chmod($file->getPathname(), 0644);
                    } elseif ($file->isDir()) {
                        chmod($file->getPathname(), 0755);
                    }
                }
            }
        }
        
        /**
         * Copy directory recursively
         */
        private static function copy_directory($src, $dst) {
            if (!is_dir($src)) return false;
            if (!wp_mkdir_p($dst)) return false;
            
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($files as $file) {
                $dst_path = $dst . '/' . $files->getSubPathName();
                if ($file->isDir()) {
                    wp_mkdir_p($dst_path);
                } else {
                    copy($file, $dst_path);
                }
            }
            return true;
        }
        
        /**
         * Remove directory recursively
         */
        private static function remove_directory($dir) {
            if (!is_dir($dir)) return;
            
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file);
                } else {
                    unlink($file);
                }
            }
            rmdir($dir);
        }
        
        /**
         * Clean up core when no 35s plugins are active
         */
        public static function maybe_cleanup_core() {
            $active_plugins = get_option('active_plugins', array());
            
            // Check if any other 35s plugins are still active
            $s35_plugins_active = false;
            $s35_plugin_patterns = array(
                '35s-post-gaps/',
                '35s-request-lister/', 
                '35s-smart-tag-blocks/',
                '35sSecureFileDownload/'
            );
            
            foreach ($active_plugins as $plugin) {
                foreach ($s35_plugin_patterns as $pattern) {
                    if (strpos($plugin, $pattern) === 0) {
                        $s35_plugins_active = true;
                        break 2;
                    }
                }
            }
            
            // If no 35s plugins are active and core was auto-installed, deactivate it
            if (!$s35_plugins_active && (get_option('s35_core_installed_from_github') || get_option('s35_core_created_locally'))) {
                deactivate_plugins(self::$core_plugin_file);
                
                // Add cleanup notice
                add_option('s35_core_cleanup_notice', true);
            }
        }
        
        /**
         * Fallback content for main plugin file
         */
        private static function get_fallback_main_content() {
            return '<?php
/*
Plugin Name: 35s Core
Plugin URI: https://35sites.com/wordpress-plugins/
Description: Core functionality for all 35s plugins. Auto-installed from GitHub.
Version: 1.0.0
Author: Jorge Pereira (35sites)
Text Domain: 35s-core
*/

if (!defined(\'ABSPATH\')) exit;

define(\'S35_CORE_VERSION\', \'1.0.0\');
define(\'S35_CORE_PLUGIN_DIR\', plugin_dir_path(__FILE__));
define(\'S35_CORE_PLUGIN_URL\', plugin_dir_url(__FILE__));

// Load the menu manager class
if (!class_exists(\'S35_Menu_Manager\')) {
    require_once S35_CORE_PLUGIN_DIR . \'includes/class-s35-menu-manager.php\';
}

class S35_Core {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action(\'plugins_loaded\', array($this, \'init\'));
        register_activation_hook(__FILE__, array($this, \'activate\'));
        register_deactivation_hook(__FILE__, array($this, \'deactivate\'));
    }
    
    public function init() {
        S35_Menu_Manager::getInstance();
        do_action(\'s35_core_loaded\');
    }
    
    public function activate() {
        update_option(\'s35_core_installed\', true);
        update_option(\'s35_core_version\', S35_CORE_VERSION);
        update_option(\'s35_core_auto_generated\', true);
    }
    
    public function deactivate() {
        // Keep options for other plugins
    }
    
    public static function is_core_active() {
        return get_option(\'s35_core_installed\', false);
    }
}

S35_Core::getInstance();

function s35_core_available() {
    return class_exists(\'S35_Core\') && S35_Core::is_core_active();
}

// Admin notices
add_action(\'admin_notices\', function() {
    if (get_option(\'s35_core_github_update_notice\')) {
        $version = get_option(\'s35_core_github_update_notice\');
        echo \'<div class="notice notice-success is-dismissible">
            <p><strong>35s Core Updated:</strong> Updated to version \' . esc_html($version) . \' from GitHub.</p>
        </div>\';
        delete_option(\'s35_core_github_update_notice\');
    }
    
    if (get_option(\'s35_core_cleanup_notice\')) {
        echo \'<div class="notice notice-info is-dismissible">
            <p><strong>35s Core:</strong> Core plugin has been deactivated as no 35s plugins are currently active.</p>
        </div>\';
        delete_option(\'s35_core_cleanup_notice\');
    }
    
    if (get_option(\'s35_core_auto_generated\') && current_user_can(\'manage_options\')) {
        $screen = get_current_screen();
        if ($screen && $screen->id === \'plugins\') {
            echo \'<div class="notice notice-info is-dismissible">
                <p><strong>35s Core:</strong> This plugin was automatically installed from GitHub to support your 35s plugins suite.</p>
            </div>\';
        }
    }
});
';
        }
        
        /**
         * Fallback content for menu manager class
         */
        private static function get_fallback_menu_manager_content() {
            return '<?php
if (!class_exists(\'S35_Menu_Manager\')) {
    class S35_Menu_Manager {
        
        private static $instance = null;
        private static $main_page_created = false;
        
        public static function getInstance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function __construct() {
            add_action(\'admin_menu\', array($this, \'ensure_main_menu\'), 5);
        }
        
        public function ensure_main_menu() {
            if (!self::$main_page_created && !$this->main_menu_exists()) {
                $this->create_main_menu();
                self::$main_page_created = true;
            }
        }
        
        private function main_menu_exists() {
            global $menu;
            if (!is_array($menu)) return false;
            
            foreach ($menu as $menu_item) {
                if (isset($menu_item[2]) && $menu_item[2] === \'35s-plugins\') {
                    return true;
                }
            }
            return false;
        }
        
        private function create_main_menu() {
            add_menu_page(
                __(\'35s Plugins\', \'35s-core\'),
                __(\'35s Plugins\', \'35s-core\'),
                \'manage_options\',
                \'35s-plugins\',
                array($this, \'display_main_page\'),
                \'dashicons-admin-plugins\',
                30
            );
        }
        
        public function add_submenu($page_title, $menu_title, $menu_slug, $callback) {
            $this->ensure_main_menu();
            
            add_submenu_page(
                \'35s-plugins\',
                $page_title,
                $menu_title,
                \'manage_options\',
                $menu_slug,
                $callback
            );
        }
        
        public function display_main_page() {
            $active_plugins = $this->get_active_35s_plugins();
            ?>
            <div class="wrap">
                <h1><?php _e(\'35s Plugins Suite\', \'35s-core\'); ?></h1>
                
                <div class="s35-dashboard">
                    <div class="s35-welcome-panel">
                        <h2><?php _e(\'Welcome to 35s Plugins\', \'35s-core\'); ?></h2>
                        <p><?php _e(\'Manage all your 35s plugins from this central dashboard. The core functionality is automatically synchronized with GitHub for updates.\', \'35s-core\'); ?></p>
                    </div>
                    
                    <?php if (!empty($active_plugins)): ?>
                    <div class="s35-plugins-grid">
                        <h3><?php _e(\'Active Plugins\', \'35s-core\'); ?></h3>
                        <div class="s35-grid">
                            <?php foreach ($active_plugins as $plugin): ?>
                            <div class="s35-plugin-card">
                                <h4><?php echo esc_html($plugin[\'name\']); ?></h4>
                                <p><?php echo esc_html($plugin[\'description\']); ?></p>
                                <?php if (!empty($plugin[\'admin_url\'])): ?>
                                <a href="<?php echo esc_url($plugin[\'admin_url\']); ?>" class="button button-primary">
                                    <?php _e(\'Configure\', \'35s-core\'); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="s35-info-panel">
                        <h3><?php _e(\'Plugin Information\', \'35s-core\'); ?></h3>
                        <p>
                            <?php if (get_option(\'s35_core_installed_from_github\')): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                <?php _e(\'Core installed from GitHub\', \'35s-core\'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-info" style="color: #0073aa;"></span>
                                <?php _e(\'Core created locally\', \'35s-core\'); ?>
                            <?php endif; ?>
                        </p>
                        <?php
                        $last_check = get_option(\'s35_core_last_update_check\');
                        if ($last_check):
                        ?>
                        <p>
                            <span class="dashicons dashicons-update" style="color: #666;"></span>
                            <?php printf(
                                __(\'Last update check: %s\', \'35s-core\'),
                                date(\'F j, Y g:i a\', $last_check)
                            ); ?>
                        </p>
                        <?php endif; ?>
                        
                        <p>
                            <a href="https://35sites.com/support" target="_blank" class="button">
                                <?php _e(\'Get Support\', \'35s-core\'); ?>
                            </a>
                            <a href="https://35sites.com/wordpress-plugins/" target="_blank" class="button">
                                <?php _e(\'More Plugins\', \'35s-core\'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            
            <style>
                .s35-dashboard { max-width: 1200px; }
                .s35-welcome-panel {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-radius: 4px;
                    padding: 20px;
                    margin: 20px 0;
                }
                .s35-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 20px;
                    margin: 15px 0;
                }
                .s35-plugin-card {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-radius: 4px;
                    padding: 20px;
                }
                .s35-plugin-card h4 {
                    margin-top: 0;
                    color: #1d2327;
                }
                .s35-info-panel {
                    background: #f6f7f7;
                    border: 1px solid #c3c4c7;
                    border-radius: 4px;
                    padding: 20px;
                    margin: 20px 0;
                }
                .s35-info-panel .dashicons {
                    margin-right: 5px;
                }
            </style>
            <?php
        }
        
        private function get_active_35s_plugins() {
            $plugins = array();
            
            $plugin_definitions = array(
                \'35s-post-gaps/35s-post-gaps.php\' => array(
                    \'name\' => \'35s Post Gaps\',
                    \'description\' => \'Manages post gaps and spacing for better content layout.\',
                    \'admin_url\' => admin_url(\'admin.php?page=35s-post-gaps\')
                ),
                \'35s-request-lister/35s-request-lister.php\' => array(
                    \'name\' => \'35s Request Lister\',
                    \'description\' => \'Lists and manages various types of requests efficiently.\',
                    \'admin_url\' => admin_url(\'admin.php?page=35s-request-lister\')
                ),
                \'35s-smart-tag-blocks/35s-smart-tag-blocks.php\' => array(
                    \'name\' => \'35s Smart Tag Blocks\',
                    \'description\' => \'Advanced tag management with intelligent block features.\',
                    \'admin_url\' => admin_url(\'admin.php?page=35s-smart-tag-blocks\')
                ),
                \'35sSecureFileDownload/35sSecureFileDownload.php\' => array(
                    \'name\' => \'35s Secure File Download\',
                    \'description\' => \'Secure file download management with access controls.\',
                    \'admin_url\' => admin_url(\'admin.php?page=35sSecureFileDownload\')
                )
            );
            
            foreach ($plugin_definitions as $plugin_file => $plugin_info) {
                if (is_plugin_active($plugin_file)) {
                    $plugins[] = $plugin_info;
                }
            }
            
            return $plugins;
        }
    }
}
';
        }
        
        /**
         * Fallback content for README
         */
        private static function get_fallback_readme_content() {
            return '=== 35s Core ===
Contributors: jorgep
Tags: utilities, core, management
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
License: GPL v2 or later

Core functionality for 35s plugins suite. Auto-installed from GitHub.

== Description ==

This plugin provides core functionality for the 35s plugins suite including:
- Centralized menu management
- Shared utilities and functions  
- Plugin coordination
- Automatic updates from GitHub

This plugin was automatically installed when you activated your first 35s plugin.

== Installation ==

This plugin is automatically installed and managed by other 35s plugins.
Updates are automatically downloaded from GitHub.

== Changelog ==

= 1.0.0 =
* Initial auto-generated release from GitHub
';
        }
    }
}
