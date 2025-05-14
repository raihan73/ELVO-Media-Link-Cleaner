<?php
class ELVO_Link_Checker {
    public function render_links_tab() {
        ?>
        <div class="elvo-links-tab">
            <p>
                <button class="button button-primary" id="scan-broken-links">
                    <?php esc_html_e('Scan Broken Links', 'elvo-cleaner'); ?>
                </button>
                <span class="description">
                    <?php esc_html_e('Scan posts and pages for broken external links.', 'elvo-cleaner'); ?>
                </span>
            </p>
            <div id="broken-links-results"></div>
        </div>
        <?php
    }
    
    public function ajax_scan_broken_links() {
        check_ajax_referer('elvo_cleaner_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to scan links.', 'elvo-cleaner'));
        }
        
        $options = get_option('elvo_cleaner_options');
        $timeout = isset($options['link_timeout']) ? intval($options['link_timeout']) : 10;
        $ignore_patterns = isset($options['ignore_url_patterns']) ? array_filter(array_map('trim', explode("\n", $options['ignore_url_patterns']))) : [];
        
        global $wpdb;
        
        // Get all published posts with content
        $posts = $wpdb->get_results(
            "SELECT ID, post_content 
             FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
             AND post_content LIKE '%http%'"
        );
        
        $broken_links = [];
        
        foreach ($posts as $post) {
            // Extract all URLs from post content
            preg_match_all(
                '#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#',
                $post->post_content,
                $matches
            );
            
            foreach ($matches[0] as $url) {
                // Skip local URLs
                if (false !== strpos($url, site_url())) {
                    continue;
                }
                
                // Check against ignore patterns
                $skip = false;
                foreach ($ignore_patterns as $pattern) {
                    if (fnmatch($pattern, $url)) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;
                
                // Check URL status
                $response = wp_remote_head($url, [
                    'timeout' => $timeout,
                    'redirection' => 3
                ]);
                
                $status = wp_remote_retrieve_response_code($response);
                
                if (is_wp_error($response) || $status >= 400) {
                    $broken_links[] = [
                        'url' => esc_url_raw($url),
                        'status' => $status ?: __('Unknown', 'elvo-cleaner'),
                        'post_id' => $post->ID,
                        'post_title' => get_the_title($post->ID),
                        'post_edit_link' => get_edit_post_link($post->ID)
                    ];
                }
                
                // Small delay to avoid overwhelming servers
                usleep(500000); // 0.5 seconds
            }
        }
        
        wp_send_json_success($broken_links);
    }
}