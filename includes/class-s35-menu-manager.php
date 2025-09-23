<?php
/**
 * 35s Plugins Menu Manager
 * This file goes in your GitHub repository: includes/class-s35-menu-manager.php
 */

if (!class_exists('S35_Menu_Manager')) {
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
            // Hook with priority 5 to ensure main menu is created first
            add_action('admin_menu', array($this, 'ensure_main_menu'), 5);
            
            // Add debug handlers if debug mode is enabled
            if (defined('DEBUG_GITHUB_35S') && DEBUG_GITHUB_35S === true) {
                add_action('admin_init', array($this, 'handle_debug_actions'));
            }
        }
        
        public function ensure_main_menu() {
            if (!self::$main_page_created && !$this->main_menu_exists()) {
                $this->create_main_menu();
                self::$main_page_created = true;
            }
        }
        
        private function main_menu_exists() {
            global $menu;
            
            if (!is_array($menu)) {
                return false;
            }
            
            foreach ($menu as $menu_item) {
                if (isset($menu_item[2]) && $menu_item[2] === '35s-plugins') {
                    return true;
                }
            }
            return false;
        }
        
        private function create_main_menu() {
            add_menu_page(
                __('35s Plugins', '35s-core'),
                __('35s Plugins', '35s-core'),
                'manage_options',
                '35s-plugins',
                array($this, 'display_main_page'),
                'dashicons-admin-plugins',
                30
            );
        }
        
        public function add_submenu($page_title, $menu_title, $menu_slug, $callback) {
            // Ensure main menu exists before adding submenu
            $this->ensure_main_menu();
            
            add_submenu_page(
                '35s-plugins',
                $page_title,
                $menu_title,
                'manage_options',
                $menu_slug,
                $callback
            );
        }

        public function handle_debug_actions() {
            if (!current_user_can('manage_options')) {
                return;
            }
            
            // Handle GitHub download test
            if (isset($_GET['test_github_download']) && $_GET['page'] === '35s-plugins') {
                $this->test_github_download();
                exit;
            }
            
            // Handle force GitHub install
            if (isset($_GET['force_github_install']) && $_GET['page'] === '35s-plugins') {
                $this->force_github_install();
                exit;
            }
        }

        private function test_github_download() {
            echo '<div style="margin: 20px; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">';
            echo '<h2>🔍 GitHub Download Test</h2>';
            
            $test_urls = array(
                '35s-core.php' => 'https://raw.githubusercontent.com/jorper98/35s-Plugins-Core0/main/35s-core.php',
                'class-s35-menu-manager.php' => 'https://raw.githubusercontent.com/jorper98/35s-Plugins-Core0/main/includes/class-s35-menu-manager.php',
                'README.txt' => 'https://raw.githubusercontent.com/jorper98/35s-Plugins-Core0/main/README.txt'
            );
            
            foreach ($test_urls as $filename => $url) {
                echo '<div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 4px;">';
                echo '<h3 style="margin-top: 0;">📁 ' . esc_html($filename) . '</h3>';
                echo '<p><strong>URL:</strong> <a href="' . esc_url($url) . '" target="_blank" style="font-family: monospace; font-size: 12px;">' . esc_html($url) . '</a></p>';
                
                $response = wp_remote_get($url, array(
                    'timeout' => 30,
                    'user-agent' => 'WordPress/35s-plugins-tester'
                ));
                
                if (is_wp_error($response)) {
                    echo '<p style="color: #dc3545; font-weight: bold;">❌ ERROR: ' . esc_html($response->get_error_message()) . '</p>';
                } else {
                    $http_code = wp_remote_retrieve_response_code($response);
                    $body = wp_remote_retrieve_body($response);
                    $content_length = strlen($body);
                    
                    if ($http_code === 200 && $content_length > 0) {
                        echo '<p style="color: #28a745; font-weight: bold;">✅ SUCCESS</p>';
                        echo '<p><strong>Size:</strong> ' . number_format($content_length) . ' bytes</p>';
                        echo '<details><summary style="cursor: pointer; color: #007cba;">📝 Preview first 500 characters</summary>';
                        echo '<pre style="background: #f8f9fa; padding: 10px; border-radius: 3px; overflow: auto; font-size: 11px;">' . esc_html(substr($body, 0, 500)) . '...</pre></details>';
                    } else {
                        echo '<p style="color: #dc3545; font-weight: bold;">❌ FAILED - HTTP ' . esc_html($http_code) . '</p>';
                        if ($content_length > 0) {
                            echo '<pre style="background: #f8d7da; padding: 10px; border-radius: 3px;">' . esc_html(substr($body, 0, 500)) . '</pre>';
                        }
                    }
                }
                echo '</div>';
            }
            
            echo '<div style="border: 1px solid #17a2b8; background: #d1ecf1; padding: 15px; margin: 20px 0; border-radius: 4px;">';
            echo '<h3 style="margin-top: 0; color: #0c5460;">💾 File System Test</h3>';
            $test_dir = WP_PLUGIN_DIR . '/35s-write-test';
            if (wp_mkdir_p($test_dir) && file_put_contents($test_dir . '/test.txt', 'test') !== false) {
                echo '<p style="color: #28a745;">✅ File system: Write permissions OK</p>';
                unlink($test_dir . '/test.txt');
                rmdir($test_dir);
            } else {
                echo '<p style="color: #dc3545;">❌ File system: Write permissions FAILED</p>';
            }
            echo '</div>';
            
            echo '<p><a href="' . admin_url('admin.php?page=35s-plugins') . '" class="button button-primary">← Back to 35s Plugins</a></p>';
            echo '</div>';
        }

        private function force_github_install() {
            echo '<div style="margin: 20px; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">';
            echo '<h2>🔄 Force GitHub Reinstall</h2>';
            
            // Only proceed if we have the installer class
            if (!class_exists('S35_GitHub_Core_Installer')) {
                echo '<p style="color: #dc3545;">❌ S35_GitHub_Core_Installer class not available</p>';
                echo '<p><a href="' . admin_url('admin.php?page=35s-plugins') . '" class="button">← Back</a></p>';
                echo '</div>';
                return;
            }
            
            echo '<div style="background: #fff3cd; padding: 15px; margin: 10px 0; border-radius: 4px;">';
            echo '<p><strong>⚠️ Warning:</strong> This will delete and reinstall the core plugin from GitHub.</p>';
            echo '</div>';
            
            // Remove existing core
            $core_dir = WP_PLUGIN_DIR . '/35s-core';
            if (is_dir($core_dir)) {
                echo '<p>🗑️ Removing existing core directory...</p>';
                $this->delete_directory_recursive($core_dir);
            }
            
            // Reset core options
            delete_option('s35_core_installed_from_github');
            delete_option('s35_core_created_locally');
            delete_option('s35_core_last_update_check');
            delete_option('s35_core_installed');
            
            echo '<p>📥 Attempting fresh install from GitHub...</p>';
            
            $result = S35_GitHub_Core_Installer::ensure_core_plugin();
            
            if ($result) {
                echo '<p style="color: #28a745; font-weight: bold;">✅ Installation: SUCCESS</p>';
            } else {
                echo '<p style="color: #dc3545; font-weight: bold;">❌ Installation: FAILED</p>';
            }
            
            // Check what was installed
            if (file_exists(WP_PLUGIN_DIR . '/35s-core/35s-core.php')) {
                $content = file_get_contents(WP_PLUGIN_DIR . '/35s-core/35s-core.php');
                if (strpos($content, 'Created as local fallback') !== false) {
                    echo '<p style="color: #fd7e14;">⚠️ Local fallback was created (GitHub download failed)</p>';
                } else {
                    echo '<p style="color: #28a745;">✅ GitHub version was downloaded successfully</p>';
                }
                
                if (is_plugin_active('35s-core/35s-core.php')) {
                    echo '<p style="color: #28a745;">✅ Plugin is ACTIVE</p>';
                } else {
                    echo '<p style="color: #dc3545;">❌ Plugin is NOT active</p>';
                }
            } else {
                echo '<p style="color: #dc3545;">❌ Core plugin file does not exist</p>';
            }
            
            echo '<p><a href="' . admin_url('admin.php?page=35s-plugins') . '" class="button button-primary">← Back to 35s Plugins</a></p>';
            echo '</div>';
        }

        private function delete_directory_recursive($dir) {
            if (!is_dir($dir)) return;
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? $this->delete_directory_recursive($path) : unlink($path);
            }
            rmdir($dir);
        }
        
        public function display_main_page() {
            $active_plugins = $this->get_active_35s_plugins();
            $core_info = $this->get_core_info();
            ?>
            <div class="wrap">
                <h1><?php _e('35s Plugins Suite', '35s-core'); ?></h1>
                
                <div class="s35-dashboard">
                    <div class="s35-welcome-panel">
                        <h2><?php _e('Welcome to 35s Plugins', '35s-core'); ?></h2>
                        <p><?php _e('Manage all your 35s plugins from this central dashboard. The core functionality is automatically synchronized with GitHub for seamless updates.', '35s-core'); ?></p>
                    </div>
                    
                    <!-- Debug Tools Section -->
                    <?php
                    $show_debug = defined('DEBUG_GITHUB_35S') && DEBUG_GITHUB_35S === true;
                    if ($show_debug && current_user_can('manage_options')): 
                    ?>
                    <div class="s35-debug-panel" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 4px;">
                        <h3 style="margin-top: 0; color: #856404;">🛠️ Debug Tools</h3>
                        <p style="margin-bottom: 15px; color: #856404;">
                            <strong>Developer Mode Active</strong> - These tools help debug GitHub integration issues.
                        </p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=35s-plugins&test_github_download=1'); ?>" class="button button-secondary">
                                Test GitHub Downloads
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=35s-plugins&force_github_install=1'); ?>" class="button button-secondary">
                                Force GitHub Reinstall
                            </a>
                        </p>
                        <p style="margin-bottom: 0; font-size: 12px; color: #856404;">
                            <strong>Note:</strong> Set <code>define('DEBUG_GITHUB_35S', false);</code> in wp-config.php to hide these tools.
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($active_plugins)): ?>
                    <div class="s35-plugins-grid">
                        <h3><?php _e('Active Plugins', '35s-core'); ?></h3>
                        <div class="s35-grid">
                            <?php foreach ($active_plugins as $plugin): ?>
                            <div class="s35-plugin-card">
                                <h4><?php echo esc_html($plugin['name']); ?></h4>
                                <p><?php echo esc_html($plugin['description']); ?></p>
                                <?php if (!empty($plugin['admin_url'])): ?>
                                <a href="<?php echo esc_url($plugin['admin_url']); ?>" class="button button-primary">
                                    <?php _e('Configure', '35s-core'); ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="notice notice-info inline">
                        <p><?php _e('No 35s plugins detected. Install and activate 35s plugins to see them here.', '35s-core'); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="s35-info-panel">
                        <h3><?php _e('System Information', '35s-core'); ?></h3>
                        
                        <div class="s35-info-grid">
                            <div class="s35-info-item">
                                <strong><?php _e('Core Version:', '35s-core'); ?></strong>
                                <span><?php echo esc_html($core_info['version']); ?></span>
                            </div>
                            
                            <div class="s35-info-item">
                                <strong><?php _e('Installation Method:', '35s-core'); ?></strong>
                                <?php if ($core_info['installed_from_github']): ?>
                                    <span class="status-github">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php _e('GitHub', '35s-core'); ?>
                                    </span>
                                <?php elseif ($core_info['created_locally']): ?>
                                    <span class="status-local">
                                        <span class="dashicons dashicons-admin-tools"></span>
                                        <?php _e('Local Fallback', '35s-core'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-unknown">
                                        <span class="dashicons dashicons-info"></span>
                                        <?php _e('Unknown', '35s-core'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($core_info['last_update_check']): ?>
                            <div class="s35-info-item">
                                <strong><?php _e('Last Update Check:', '35s-core'); ?></strong>
                                <span><?php echo esc_html(date('F j, Y g:i a', $core_info['last_update_check'])); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($core_info['activated_date']): ?>
                            <div class="s35-info-item">
                                <strong><?php _e('Core Activated:', '35s-core'); ?></strong>
                                <span><?php echo esc_html(date('F j, Y', strtotime($core_info['activated_date']))); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="s35-actions">
                            <h4><?php _e('Support & Resources', '35s-core'); ?></h4>
                            <p>
                                <a href="https://35sites.com/support" target="_blank" class="button">
                                    <span class="dashicons dashicons-sos"></span>
                                    <?php _e('Get Support', '35s-core'); ?>
                                </a>
                                <a href="https://35sites.com/wordpress-plugins/" target="_blank" class="button">
                                    <span class="dashicons dashicons-admin-plugins"></span>
                                    <?php _e('More Plugins', '35s-core'); ?>
                                </a>
                                <?php if ($core_info['installed_from_github']): ?>
                                <a href="https://github.com/jorper98/35s-Plugins-Core0" target="_blank" class="button">
                                    <span class="dashicons dashicons-external"></span>
                                    <?php _e('View on GitHub', '35s-core'); ?>
                                </a>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <?php if ($core_info['auto_generated']): ?>
                        <div class="s35-notice">
                            <p>
                                <span class="dashicons dashicons-info"></span>
                                <?php _e('This core plugin was automatically installed to support your 35s plugins. It can be safely deactivated if you remove all 35s plugins.', '35s-core'); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <style>
                .s35-dashboard {
                    max-width: 1200px;
                }
                
                .s35-welcome-panel {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    border-radius: 4px;
                    padding: 20px;
                    margin: 20px 0;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                
                .s35-plugins-grid {
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
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    transition: box-shadow 0.3s ease;
                }
                
                .s35-plugin-card:hover {
                    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
                }
                
                .s35-plugin-card h4 {
                    margin-top: 0;
                    color: #1d2327;
                    font-size: 16px;
                }
                
                .s35-plugin-card p {
                    color: #666;
                    margin: 10px 0 15px 0;
                    line-height: 1.5;
                }
                
                .s35-info-panel {
                    background: #f6f7f7;
                    border: 1px solid #c3c4c7;
                    border-radius: 4px;
                    padding: 20px;
                    margin: 20px 0;
                }
                
                .s35-info-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 15px;
                    margin: 15px 0;
                }
                
                .s35-info-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 10px;
                    background: #fff;
                    border-radius: 3px;
                    border: 1px solid #ddd;
                }
                
                .s35-info-item strong {
                    color: #1d2327;
                }
                
                .status-github {
                    color: #46b450;
                }
                
                .status-local {
                    color: #0073aa;
                }
                
                .status-unknown {
                    color: #666;
                }
                
                .s35-actions {
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                }
                
                .s35-actions h4 {
                    margin-top: 0;
                    color: #1d2327;
                }
                
                .s35-actions .button {
                    margin-right: 10px;
                    margin-bottom: 5px;
                }
                
                .s35-actions .button .dashicons {
                    margin-right: 5px;
                    vertical-align: middle;
                }
                
                .s35-notice {
                    margin-top: 20px;
                    padding: 15px;
                    background: #fff;
                    border-left: 4px solid #0073aa;
                    border-radius: 0 3px 3px 0;
                }
                
                .s35-notice p {
                    margin: 0;
                    color: #666;
                }
                
                .s35-notice .dashicons {
                    color: #0073aa;
                    margin-right: 5px;
                }
                
                .notice.inline {
                    display: block;
                    margin: 20px 0;
                    padding: 15px;
                }
            </style>
            <?php
        }
        
        private function get_active_35s_plugins() {
            $plugins = array();
            
            // Plugin definitions - add your plugins here
            $plugin_definitions = array(
                '35s-post-gaps/35s-post-gaps.php' => array(
                    'name' => '35s Post Gaps',
                    'description' => 'Identifies gaps in your posting schedule with calendar and list views.',
                    'admin_url' => admin_url('admin.php?page=35s-post-gaps')
                ),
                '35s-request-lister/35s-request-lister.php' => array(
                    'name' => '35s Request Lister',
                    'description' => 'Lists and manages various types of requests efficiently.',
                    'admin_url' => admin_url('admin.php?page=35s-request-lister')
                ),
                '35s-smart-tag-blocks/35s-smart-tag-blocks.php' => array(
                    'name' => '35s Smart Tag Blocks',
                    'description' => 'Advanced tag management with intelligent block features.',
                    'admin_url' => admin_url('admin.php?page=35s-smart-tag-blocks')
                ),
                '35sSecureFileDownload/35sSecureFileDownload.php' => array(
                    'name' => '35s Secure File Download',
                    'description' => 'Secure file download management with access controls.',
                    'admin_url' => admin_url('admin.php?page=35sSecureFileDownload')
                )
            );
            
            // Check which plugins are actually active
            foreach ($plugin_definitions as $plugin_file => $plugin_info) {
                if (is_plugin_active($plugin_file)) {
                    $plugins[] = $plugin_info;
                }
            }
            
            return $plugins;
        }
        
        private function get_core_info() {
            // Get core information
            if (function_exists('s35_get_core_info')) {
                return s35_get_core_info();
            }
            
            // Fallback if function doesn't exist
            return array(
                'version' => defined('S35_CORE_VERSION') ? S35_CORE_VERSION : '1.0.0',
                'installed_from_github' => get_option('s35_core_installed_from_github', false),
                'created_locally' => get_option('s35_core_created_locally', false),
                'last_update_check' => get_option('s35_core_last_update_check', 0),
                'activated_date' => get_option('s35_core_activated', ''),
                'auto_generated' => get_option('s35_core_auto_generated', false)
            );
        }
    }
}
