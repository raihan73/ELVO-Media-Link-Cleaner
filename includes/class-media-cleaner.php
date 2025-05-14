<?php
class ELVO_Media_Cleaner {
    public function render_media_tab() {
        ?>
        <div class="elvo-media-tab">
            <p>
                <button class="button button-primary" id="scan-unused-media">
                    <?php esc_html_e('Scan Unused Media', 'elvo-cleaner'); ?>
                </button>
                <span class="description">
                    <?php esc_html_e('Scan for media files that are not used in any posts or pages.', 'elvo-cleaner'); ?>
                </span>
            </p>
            <div id="unused-media-results"></div>
        </div>
        <?php
    }

    public function ajax_scan_unused_media() {
        check_ajax_referer('elvo_cleaner_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('You do not have permission to scan media.', 'elvo-cleaner'));
        }
        
        $options = get_option('elvo_cleaner_options');
        $threshold = isset($options['scan_threshold']) ? intval($options['scan_threshold']) : 100;
        $excluded_types = isset($options['exclude_mime_types']) ? (array)$options['exclude_mime_types'] : [];
        $excluded_categories = isset($options['exclude_categories']) ? (array)$options['exclude_categories'] : [];
        
        global $wpdb;
        
        // Build the query with exclusions
        $query = "SELECT p.ID, p.guid, p.post_title, p.post_mime_type 
                  FROM {$wpdb->posts} p
                  WHERE p.post_type = 'attachment'";
        
        // Exclude MIME types
        if (!empty($excluded_types)) {
            $placeholders = array_fill(0, count($excluded_types), '%s');
            $prepared = $wpdb->prepare(implode(', ', $placeholders), $excluded_types);
            $query .= " AND p.post_mime_type NOT IN ($prepared)";
        }
        
        // Limit results
        $query .= " LIMIT $threshold";
        
        $attachments = $wpdb->get_results($query);
        $unused = [];
        
        // Get all post content that might reference media
        $post_contents = $wpdb->get_col(
            "SELECT post_content 
             FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
             AND (post_content LIKE '%wp-image-%' OR post_content LIKE '%href=%')"
        );
        
        foreach ($attachments as $attachment) {
            $is_used = false;
            
            // Skip if attachment is in excluded category
            if (!empty($excluded_categories)) {
                $parent_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d",
                        $attachment->ID
                    )
                );
                
                if ($parent_id) {
                    $parent_categories = wp_get_post_categories($parent_id, ['fields' => 'ids']);
                    if (array_intersect($parent_categories, $excluded_categories)) {
                        continue;
                    }
                }
            }
            
            // Check if media is used in post content
            foreach ($post_contents as $content) {
                // Check for image classes
                if (false !== strpos($content, 'wp-image-' . $attachment->ID)) {
                    $is_used = true;
                    break;
                }
                
                // Check for direct URL references
                if (false !== strpos($content, $attachment->guid)) {
                    $is_used = true;
                    break;
                }
            }
            
            // Check if media has a parent post (featured image)
            if (!$is_used) {
                $parent_id = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d",
                        $attachment->ID
                    )
                );
                $is_used = ($parent_id && $parent_id != 0);
            }
            
            // Check if media is used in custom fields
            if (!$is_used) {
                $meta_count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                         WHERE meta_value LIKE %s",
                        '%' . $wpdb->esc_like($attachment->guid) . '%'
                    )
                );
                $is_used = ($meta_count > 0);
            }
            
            if (!$is_used) {
                $unused[] = [
                    'ID' => $attachment->ID,
                    'title' => $attachment->post_title,
                    'url' => $attachment->guid,
                    'mime_type' => $attachment->post_mime_type,
                    'edit_link' => get_edit_post_link($attachment->ID)
                ];
            }
        }
        
        wp_send_json_success($unused);
    }
    
    public function ajax_delete_media() {
        check_ajax_referer('elvo_cleaner_nonce', 'nonce');
        
        if (!current_user_can('delete_post', $_POST['media_id'])) {
            wp_send_json_error(__('Permission denied.', 'elvo-cleaner'));
        }
        
        $media_id = intval($_POST['media_id']);
        $result = $this->delete_media($media_id);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete media.', 'elvo-cleaner'));
        }
    }

    public function ajax_bulk_delete_media() {
        check_ajax_referer('elvo_cleaner_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Permission denied.', 'elvo-cleaner'));
        }
        
        $media_ids = isset($_POST['media_ids']) ? array_map('intval', $_POST['media_ids']) : [];
        
        if (empty($media_ids)) {
            wp_send_json_error(__('No media selected.', 'elvo-cleaner'));
        }
        
        $results = [];
        foreach ($media_ids as $id) {
            $results[$id] = $this->delete_media($id);
        }
        
        wp_send_json_success([
            'deleted' => array_keys(array_filter($results)),
            'message' => sprintf(
                _n('%d item deleted.', '%d items deleted.', count($results), 'elvo-cleaner'),
                count(array_filter($results))
            )
        ]);
    }

    private function delete_media($media_id) {
        if (!current_user_can('delete_post', $media_id)) {
            return false;
        }
        
        return wp_delete_attachment($media_id, true);
    }
}