jQuery(document).ready(function($) {
    // Tab switching functionality
    $('.nav-tab-wrapper').on('click', '.nav-tab', function(e) {
        e.preventDefault();
        
        // Switch active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Hide all tab contents
        $('.elvo-tab-content').hide();
        
        // Show clicked tab content
        var targetTab = $(this).attr('href');
        $(targetTab).show();
    });

    // Default to showing the 'Media' tab on page load
    $('#media').show();

    // Scan Unused Media
    $('#scan-unused-media').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(elvoCleaner.scanning_text);

        $.post(elvoCleaner.ajax_url, {
            action: 'elvo_scan_unused_media',
            nonce: elvoCleaner.nonce
        }).done(function(response) {
            if (response.success && response.data.length) {
                renderMediaResults(response.data);
            } else {
                $('#unused-media-results').html(
                    '<div class="notice notice-info"><p>' + 
                    elvoCleaner.no_unused_media + 
                    '</p></div>'
                );
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            $('#unused-media-results').html(
                '<div class="notice notice-error"><p>' + 
                elvoCleaner.scan_error + 
                '</p></div>'
            );
        }).always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });

    function renderMediaResults(media) {
        var html = `
            <div class="elvo-media-results">
                <form id="elvo-bulk-delete-form">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th width="5%"><input type="checkbox" id="elvo-select-all"></th>
                                <th width="15%">${elvoCleaner.thumbnail}</th>
                                <th width="40%">${elvoCleaner.title}</th>
                                <th width="40%">${elvoCleaner.actions}</th>
                            </tr>
                        </thead>
                        <tbody>`;
        
        media.forEach(function(item) {
            html += `
                <tr data-id="${item.ID}">
                    <td><input type="checkbox" class="elvo-media-checkbox" value="${item.ID}"></td>
                    <td><img src="${item.url}" style="max-width: 100px; height: auto;"></td>
                    <td>
                        <strong>${item.title || elvoCleaner.no_title}</strong><br>
                        <small>${item.mime_type}</small><br>
                        <a href="${item.edit_link}" target="_blank">${elvoCleaner.edit}</a>
                    </td>
                    <td>
                        <button type="button" class="button button-small elvo-delete-single" data-id="${item.ID}">
                            ${elvoCleaner.delete}
                        </button>
                    </td>
                </tr>`;
        });
        
        html += `
                        </tbody>
                    </table>
                    <div class="tablenav bottom">
                        <button type="button" class="button button-primary elvo-bulk-delete">
                            ${elvoCleaner.bulk_delete}
                        </button>
                    </div>
                </form>
            </div>`;
        
        $('#unused-media-results').html(html);
    }

    // Bulk media actions
    $('#unused-media-results').on('change', '#elvo-select-all', function() {
        $('.elvo-media-checkbox').prop('checked', this.checked);
    });

    $('#unused-media-results').on('click', '.elvo-bulk-delete', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var ids = $('.elvo-media-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (!ids.length) {
            alert(elvoCleaner.no_selection);
            return;
        }
        
        if (!confirm(elvoCleaner.confirm_delete.replace('%d', ids.length))) {
            return;
        }
        
        $btn.prop('disabled', true).text(elvoCleaner.deleting_text);
        
        $.post(elvoCleaner.ajax_url, {
            action: 'elvo_bulk_delete_media',
            nonce: elvoCleaner.nonce,
            media_ids: ids
        }).done(function(response) {
            if (response.success) {
                ids.forEach(function(id) {
                    $(`tr[data-id="${id}"]`).remove();
                });
                
                if (!$('.elvo-media-checkbox').length) {
                    $('#unused-media-results').html(
                        '<div class="notice notice-success"><p>' + 
                        response.data.message + 
                        '</p></div>'
                    );
                }
            } else {
                alert(response.data || elvoCleaner.delete_error);
            }
        }).fail(function() {
            alert(elvoCleaner.delete_error);
        }).always(function() {
            $btn.prop('disabled', false).text(elvoCleaner.bulk_delete);
        });
    });

    // Single media delete
    $('#unused-media-results').on('click', '.elvo-delete-single', function() {
        var $btn = $(this);
        var id = $btn.data('id');
        
        if (!confirm(elvoCleaner.confirm_delete_single)) {
            return;
        }
        
        $btn.prop('disabled', true).text(elvoCleaner.deleting_text);
        
        $.post(elvoCleaner.ajax_url, {
            action: 'elvo_delete_media',
            nonce: elvoCleaner.nonce,
            media_id: id
        }).done(function(response) {
            if (response.success) {
                $(`tr[data-id="${id}"]`).remove();
                
                if (!$('.elvo-media-checkbox').length) {
                    $('#unused-media-results').html(
                        '<div class="notice notice-success"><p>' + 
                        elvoCleaner.all_deleted + 
                        '</p></div>'
                    );
                }
            } else {
                alert(response.data || elvoCleaner.delete_error);
            }
        }).fail(function() {
            alert(elvoCleaner.delete_error);
        });
    });

    // Scan Broken Links
    $('#scan-broken-links').on('click', function() {
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(elvoCleaner.scanning_text);

        $.post(elvoCleaner.ajax_url, {
            action: 'elvo_scan_broken_links',
            nonce: elvoCleaner.nonce
        }).done(function(response) {
            if (response.success && response.data.length) {
                renderLinkResults(response.data);
            } else {
                $('#broken-links-results').html(
                    '<div class="notice notice-info"><p>' + 
                    elvoCleaner.no_broken_links + 
                    '</p></div>'
                );
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            $('#broken-links-results').html(
                '<div class="notice notice-error"><p>' + 
                elvoCleaner.scan_error + 
                '</p></div>'
            );
        }).always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });

    function renderLinkResults(links) {
        var html = `
            <div class="elvo-link-results">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="40%">${elvoCleaner.url}</th>
                            <th width="15%">${elvoCleaner.status}</th>
                            <th width="45%">${elvoCleaner.location}</th>
                        </tr>
                    </thead>
                    <tbody>`;
        
        links.forEach(function(link) {
            html += `
                <tr>
                    <td><a href="${link.url}" target="_blank">${link.url}</a></td>
                    <td>${link.status}</td>
                    <td>
                        <a href="${link.post_edit_link}" target="_blank">${link.post_title}</a>
                    </td>
                </tr>`;
        });
        
        html += `
                    </tbody>
                </table>
            </div>`;
        
        $('#broken-links-results').html(html);
    }
});