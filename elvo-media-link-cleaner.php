<?php
/**
 * Plugin Name: ELVO Media & Link Cleaner
 * Description: Scan for unused media files and broken links with deletion and recovery options.
 * Version: 1.2.0
 * Author: ELVO Web Studio
 * Author URI: https://www.elvoweb.com
 * Plugin URI: https://github.com/raihan73/ELVO-Media-Link-Cleaner
 * License: GPL2 or later
 * Text Domain: elvo-cleaner
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('ELVO_CLEANER_VERSION', '1.2.0');
define('ELVO_CLEANER_PATH', plugin_dir_path(__FILE__));
define('ELVO_CLEANER_URL', plugin_dir_url(__FILE__));
define('ELVO_CLEANER_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once ELVO_CLEANER_PATH . 'includes/class-media-cleaner.php';
require_once ELVO_CLEANER_PATH . 'includes/class-link-checker.php';

class ELVO_Media_Link_Cleaner {
    private static $instance = null;
    private $media_cleaner;
    private $link_checker;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->media_cleaner = new ELVO_Media_Cleaner();
        $this->link_checker = new ELVO_Link_Checker();
        
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'load_admin_assets']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // AJAX handlers
        add_action('wp_ajax_elvo_scan_unused_media', [$this->media_cleaner, 'ajax_scan_unused_media']);
        add_action('wp_ajax_elvo_delete_media', [$this->media_cleaner, 'ajax_delete_media']);
        add_action('wp_ajax_elvo_bulk_delete_media', [$this->media_cleaner, 'ajax_bulk_delete_media']);
        add_action('wp_ajax_elvo_scan_broken_links', [$this->link_checker, 'ajax_scan_broken_links']);
        
        // GitHub Updater integration
        if (is_admin()) {
            add_filter('pre_set_site_transient_update_plugins', 'elvo_github_updater_check');
            add_filter('plugins_api', 'elvo_github_updater_api', 10, 3);
        }
    }

    public function register_admin_menu() {
        add_menu_page(
            __('ELVO Cleaner', 'elvo-cleaner'),
            __('ELVO Cleaner', 'elvo-cleaner'),
            'manage_options',
            'elvo-cleaner',
            [$this, 'render_admin_page'],
            'dashicons-trash',
            75
        );
    }

    public function load_admin_assets($hook) {
        if ('toplevel_page_elvo-cleaner' === $hook) {
            wp_enqueue_style(
                'elvo-cleaner-admin-css',
                ELVO_CLEANER_URL . 'assets/css/admin.css',
                [],
                ELVO_CLEANER_VERSION
            );
            
            wp_enqueue_script(
                'elvo-cleaner-admin-js',
                ELVO_CLEANER_URL . 'assets/js/admin.js',
                ['jquery'],
                ELVO_CLEANER_VERSION,
                true
            );
            
            wp_localize_script('elvo-cleaner-admin-js', 'elvoCleaner', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('elvo_cleaner_nonce'),
                'scanning_text' => __('Scanning...', 'elvo-cleaner'),
                'deleting_text' => __('Deleting...', 'elvo-cleaner'),
                'no_unused_media' => __('No unused media found.', 'elvo-cleaner'),
                'no_broken_links' => __('No broken links found.', 'elvo-cleaner'),
                'thumbnail' => __('Thumbnail', 'elvo-cleaner'),
                'title' => __('Title', 'elvo-cleaner'),
                'actions' => __('Actions', 'elvo-cleaner'),
                'no_title' => __('(no title)', 'elvo-cleaner'),
                'edit' => __('Edit', 'elvo-cleaner'),
                'delete' => __('Delete', 'elvo-cleaner'),
                'bulk_delete' => __('Bulk Delete', 'elvo-cleaner'),
                'no_selection' => __('Please select at least one item.', 'elvo-cleaner'),
                'confirm_delete' => __('Are you sure you want to delete %d items?', 'elvo-cleaner'),
                'confirm_delete_single' => __('Are you sure you want to delete this item?', 'elvo-cleaner'),
                'all_deleted' => __('All selected items have been deleted.', 'elvo-cleaner'),
                'delete_error' => __('Error deleting items. Please try again.', 'elvo-cleaner'),
                'scan_error' => __('Error scanning. Please try again.', 'elvo-cleaner'),
                'scan_media_text' => __('Scan Unused Media', 'elvo-cleaner'),
                'scan_links_text' => __('Scan Broken Links', 'elvo-cleaner'),
                'url' => __('URL', 'elvo-cleaner'),
                'status' => __('Status', 'elvo-cleaner'),
                'location' => __('Location', 'elvo-cleaner')
            ]);
        }
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('ELVO Media & Link Cleaner', 'elvo-cleaner'); ?></h1>
            
            <?php if (isset($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved successfully!', 'elvo-cleaner'); ?></p>
                </div>
            <?php endif; ?>
            
            <div id="elvo-cleaner-tabs">
                <h2 class="nav-tab-wrapper">
                    <a href="#media" class="nav-tab nav-tab-active"><?php esc_html_e('Unused Media', 'elvo-cleaner'); ?></a>
                    <a href="#links" class="nav-tab"><?php esc_html_e('Broken Links', 'elvo-cleaner'); ?></a>
                    <a href="#settings" class="nav-tab"><?php esc_html_e('Settings', 'elvo-cleaner'); ?></a>
                </h2>
                
                <div id="media" class="elvo-tab-content">
                    <?php $this->media_cleaner->render_media_tab(); ?>
                </div>
                
                <div id="links" class="elvo-tab-content" style="display:none">
                    <?php $this->link_checker->render_links_tab(); ?>
                </div>
                
                <div id="settings" class="elvo-tab-content" style="display:none">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('elvo_cleaner_settings');
                        do_settings_sections('elvo-cleaner');
                        submit_button(__('Save Settings', 'elvo-cleaner'));
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting('elvo_cleaner_settings', 'elvo_cleaner_options', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);

        // Media Settings Section
        add_settings_section(
            'elvo_cleaner_media_section',
            __('Media Settings', 'elvo-cleaner'),
            [$this, 'media_section_callback'],
            'elvo-cleaner'
        );

        add_settings_field(
            'scan_threshold',
            __('Media Scan Threshold', 'elvo-cleaner'),
            [$this, 'scan_threshold_callback'],
            'elvo-cleaner',
            'elvo_cleaner_media_section'
        );

        add_settings_field(
            'exclude_mime_types',
            __('Exclude MIME Types', 'elvo-cleaner'),
            [$this, 'exclude_mime_types_callback'],
            'elvo-cleaner',
            'elvo_cleaner_media_section'
        );

        add_settings_field(
            'exclude_categories',
            __('Exclude Categories', 'elvo-cleaner'),
            [$this, 'exclude_categories_callback'],
            'elvo-cleaner',
            'elvo_cleaner_media_section'
        );

        // Link Settings Section
        add_settings_section(
            'elvo_cleaner_links_section',
            __('Link Settings', 'elvo-cleaner'),
            [$this, 'links_section_callback'],
            'elvo-cleaner'
        );

        add_settings_field(
            'link_timeout',
            __('Link Check Timeout (seconds)', 'elvo-cleaner'),
            [$this, 'link_timeout_callback'],
            'elvo-cleaner',
            'elvo_cleaner_links_section'
        );

        add_settings_field(
            'ignore_url_patterns',
            __('URL Patterns to Ignore', 'elvo-cleaner'),
            [$this, 'ignore_url_patterns_callback'],
            'elvo-cleaner',
            'elvo_cleaner_links_section'
        );

        add_settings_field(
            'check_frequency',
            __('Automatic Check Frequency', 'elvo-cleaner'),
            [$this, 'check_frequency_callback'],
            'elvo-cleaner',
            'elvo_cleaner_links_section'
        );
    }

    public function sanitize_settings($input) {
        $output = [];
        
        if (isset($input['scan_threshold'])) {
            $output['scan_threshold'] = absint($input['scan_threshold']);
            $output['scan_threshold'] = max(10, min(1000, $output['scan_threshold']));
        }
        
        if (isset($input['exclude_mime_types'])) {
            $output['exclude_mime_types'] = array_map('sanitize_text_field', $input['exclude_mime_types']);
        }
        
        if (isset($input['exclude_categories'])) {
            $output['exclude_categories'] = array_map('absint', $input['exclude_categories']);
        }
        
        if (isset($input['link_timeout'])) {
            $output['link_timeout'] = absint($input['link_timeout']);
            $output['link_timeout'] = max(1, min(60, $output['link_timeout']));
        }
        
        if (isset($input['ignore_url_patterns'])) {
            $output['ignore_url_patterns'] = sanitize_textarea_field($input['ignore_url_patterns']);
        }
        
        if (isset($input['check_frequency'])) {
            $valid_frequencies = ['daily', 'weekly', 'monthly', 'never'];
            $output['check_frequency'] = in_array($input['check_frequency'], $valid_frequencies) 
                ? $input['check_frequency'] 
                : 'weekly';
        }
        
        return $output;
    }

    public function media_section_callback() {
        echo '<p>' . __('Configure media scanning behavior.', 'elvo-cleaner') . '</p>';
    }

    public function links_section_callback() {
        echo '<p>' . __('Configure broken link detection settings.', 'elvo-cleaner') . '</p>';
    }

    public function scan_threshold_callback() {
        $options = get_option('elvo_cleaner_options');
        $value = $options['scan_threshold'] ?? 100;
        echo '<input type="number" id="scan_threshold" name="elvo_cleaner_options[scan_threshold]" value="' . esc_attr($value) . '" min="10" max="1000" step="10">';
        echo '<p class="description">' . __('Maximum number of items to scan at once (affects performance)', 'elvo-cleaner') . '</p>';
    }

    public function exclude_mime_types_callback() {
        $options = get_option('elvo_cleaner_options');
        $current = isset($options['exclude_mime_types']) ? (array)$options['exclude_mime_types'] : [];
        
        $common_types = [
            'image/svg+xml' => 'SVG Images',
            'application/pdf' => 'PDF Documents',
            'application/msword' => 'Word Documents',
            'application/vnd.ms-excel' => 'Excel Files',
            'application/zip' => 'ZIP Archives',
            'audio/' => 'All Audio Files',
            'video/' => 'All Video Files'
        ];
        
        foreach ($common_types as $type => $label) {
            $checked = in_array($type, $current) ? 'checked' : '';
            echo '<label><input type="checkbox" name="elvo_cleaner_options[exclude_mime_types][]" value="' . esc_attr($type) . '" ' . $checked . '> ' . esc_html($label) . '</label><br>';
        }
        
        echo '<p class="description">' . __('Exclude these file types from media scans', 'elvo-cleaner') . '</p>';
    }

    public function exclude_categories_callback() {
        $options = get_option('elvo_cleaner_options');
        $current = isset($options['exclude_categories']) ? (array)$options['exclude_categories'] : [];
        
        $categories = get_categories(['hide_empty' => false]);
        
        foreach ($categories as $category) {
            $checked = in_array($category->term_id, $current) ? 'checked' : '';
            echo '<label><input type="checkbox" name="elvo_cleaner_options[exclude_categories][]" value="' . esc_attr($category->term_id) . '" ' . $checked . '> ' . esc_html($category->name) . '</label><br>';
        }
        
        echo '<p class="description">' . __('Exclude media attached to posts in these categories', 'elvo-cleaner') . '</p>';
    }

    public function link_timeout_callback() {
        $options = get_option('elvo_cleaner_options');
        $value = $options['link_timeout'] ?? 10;
        echo '<input type="number" id="link_timeout" name="elvo_cleaner_options[link_timeout]" value="' . esc_attr($value) . '" min="1" max="60">';
        echo '<p class="description">' . __('Timeout for checking each external link (in seconds)', 'elvo-cleaner') . '</p>';
    }

    public function ignore_url_patterns_callback() {
        $options = get_option('elvo_cleaner_options');
        $value = isset($options['ignore_url_patterns']) ? $options['ignore_url_patterns'] : '';
        echo '<textarea id="ignore_url_patterns" name="elvo_cleaner_options[ignore_url_patterns]" rows="3" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('One pattern per line. Links matching these patterns will be ignored. Example: example.com, /local-path/, *.domain.com', 'elvo-cleaner') . '</p>';
    }

    public function check_frequency_callback() {
        $options = get_option('elvo_cleaner_options');
        $value = $options['check_frequency'] ?? 'weekly';
        
        $frequencies = [
            'daily' => __('Daily', 'elvo-cleaner'),
            'weekly' => __('Weekly', 'elvo-cleaner'),
            'monthly' => __('Monthly', 'elvo-cleaner'),
            'never' => __('Never', 'elvo-cleaner')
        ];
        
        echo '<select id="check_frequency" name="elvo_cleaner_options[check_frequency]">';
        foreach ($frequencies as $key => $label) {
            $selected = selected($value, $key, false);
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('How often to automatically check for broken links', 'elvo-cleaner') . '</p>';
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'elvo-cleaner',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
}

// Initialize the plugin
ELVO_Media_Link_Cleaner::get_instance();

// GitHub Updater functions
function elvo_github_updater_check($transient) {
    if (empty($transient->checked)) return $transient;

    $plugin_slug = plugin_basename(__FILE__);
    $github_response = wp_remote_get('https://api.github.com/repos/raihan73/ELVO-Media-Link-Cleaner/releases/latest');

    if (!is_wp_error($github_response)) {
        $body = json_decode(wp_remote_retrieve_body($github_response));
        if (isset($body->tag_name)) {
            $transient->response[$plugin_slug] = (object) [
                'slug' => $plugin_slug,
                'new_version' => ltrim($body->tag_name, 'v'),
                'url' => 'https://github.com/raihan73/ELVO-Media-Link-Cleaner',
                'package' => $body->zipball_url,
            ];
        }
    }

    return $transient;
}

function elvo_github_updater_api($result, $action, $args) {
    if ($action === 'plugin_information' && $args->slug === plugin_basename(__FILE__)) {
        $plugin_info = [
            'name' => 'ELVO Media & Link Cleaner',
            'slug' => plugin_basename(__FILE__),
            'version' => ELVO_CLEANER_VERSION,
            'author' => '<a href="https://www.elvoweb.com">ELVO Web Studio</a>',
            'homepage' => 'https://github.com/raihan73/ELVO-Media-Link-Cleaner',
            'sections' => [
                'description' => 'Scan and delete unused media files and broken links.',
                'changelog' => 'Added comprehensive settings panel and improved scanning algorithms.',
            ],
        ];
        return (object) $plugin_info;
    }
    return $result;
}