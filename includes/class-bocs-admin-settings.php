<?php
/**
 * Class Bocs_Admin_Settings
 *
 * Handles the admin settings for the BOCS plugin.
 */
class Bocs_Admin_Settings {

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register settings sections
        add_settings_section(
            'bocs_api_settings_section',
            __('API Settings', 'bocs-wordpress'),
            array($this, 'render_api_settings_section'),
            'bocs-settings-page'
        );
        
        add_settings_section(
            'bocs_log_settings_section',
            __('Logging Settings', 'bocs-wordpress'),
            array($this, 'render_log_settings_section'),
            'bocs-settings-page'
        );
        
        add_settings_section(
            'bocs_product_mapping_section',
            __('Product Mappings', 'bocs-wordpress'),
            array($this, 'render_product_mapping_section'),
            'bocs-settings-page'
        );
        
        // Register API fields
        
        // ... existing code ...
        
        // Register product mapping field
        register_setting('bocs-settings-page', 'bocs_product_mapping');
        
        add_settings_field(
            'bocs_product_mapping',
            __('BOCS to WooCommerce Product Mappings', 'bocs-wordpress'),
            array($this, 'render_product_mapping_field'),
            'bocs-settings-page',
            'bocs_product_mapping_section'
        );
    }

    /**
     * Render the product mapping section
     */
    public function render_product_mapping_section() {
        echo '<p>' . __('Manage BOCS product ID to WooCommerce product ID mappings. These mappings are used to connect subscription items between systems.', 'bocs-wordpress') . '</p>';
    }
    
    /**
     * Render the product mapping field
     */
    public function render_product_mapping_field() {
        $product_mapping = get_option('bocs_product_mapping', []);
        
        if (empty($product_mapping)) {
            echo '<p class="description">' . __('No product mappings found. Mappings will be created automatically when subscriptions are processed.', 'bocs-wordpress') . '</p>';
        } else {
            echo '<div class="bocs-product-mapping-table-container">';
            echo '<table class="widefat bocs-product-mapping-table">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('BOCS Product ID', 'bocs-wordpress') . '</th>';
            echo '<th>' . __('WooCommerce Product ID', 'bocs-wordpress') . '</th>';
            echo '<th>' . __('WooCommerce Product', 'bocs-wordpress') . '</th>';
            echo '<th>' . __('Actions', 'bocs-wordpress') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($product_mapping as $bocs_id => $wc_id) {
                $product_title = 'Unknown';
                $product = wc_get_product($wc_id);
                if ($product) {
                    $product_title = $product->get_name();
                }
                
                echo '<tr>';
                echo '<td>' . esc_html($bocs_id) . '</td>';
                echo '<td>' . esc_html($wc_id) . '</td>';
                echo '<td>' . esc_html($product_title) . '</td>';
                echo '<td><a href="#" class="bocs-remove-mapping button button-small" data-bocs-id="' . esc_attr($bocs_id) . '">' . __('Remove', 'bocs-wordpress') . '</a></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            
            echo '<div class="bocs-add-mapping-container">';
            echo '<h4>' . __('Add New Mapping', 'bocs-wordpress') . '</h4>';
            echo '<div class="bocs-add-mapping-form">';
            echo '<input type="text" id="bocs-new-bocs-id" placeholder="' . esc_attr__('BOCS Product ID', 'bocs-wordpress') . '" />';
            echo '<input type="text" id="bocs-new-wc-id" placeholder="' . esc_attr__('WooCommerce Product ID', 'bocs-wordpress') . '" />';
            echo '<button type="button" class="button" id="bocs-add-mapping-btn">' . __('Add Mapping', 'bocs-wordpress') . '</button>';
            echo '</div>';
            echo '</div>';
            
            // Add JavaScript to handle add/remove actions
            ?>
            <script>
            jQuery(document).ready(function($) {
                // Handle remove button click
                $('.bocs-remove-mapping').on('click', function(e) {
                    e.preventDefault();
                    
                    if (confirm('<?php echo esc_js(__('Are you sure you want to remove this mapping?', 'bocs-wordpress')); ?>')) {
                        var bocsId = $(this).data('bocs-id');
                        var $row = $(this).closest('tr');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'bocs_remove_product_mapping',
                                bocs_id: bocsId,
                                nonce: '<?php echo wp_create_nonce('bocs_admin_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    $row.fadeOut(300, function() {
                                        $(this).remove();
                                        
                                        // Check if table is empty
                                        if ($('.bocs-product-mapping-table tbody tr').length === 0) {
                                            $('.bocs-product-mapping-table-container').html('<p class="description"><?php echo esc_js(__('No product mappings found. Mappings will be created automatically when subscriptions are processed.', 'bocs-wordpress')); ?></p>');
                                        }
                                    });
                                } else {
                                    alert(response.data.message || '<?php echo esc_js(__('Error removing mapping', 'bocs-wordpress')); ?>');
                                }
                            },
                            error: function() {
                                alert('<?php echo esc_js(__('Network error when trying to remove mapping', 'bocs-wordpress')); ?>');
                            }
                        });
                    }
                });
                
                // Handle add button click
                $('#bocs-add-mapping-btn').on('click', function() {
                    var bocsId = $('#bocs-new-bocs-id').val().trim();
                    var wcId = $('#bocs-new-wc-id').val().trim();
                    
                    if (!bocsId || !wcId) {
                        alert('<?php echo esc_js(__('Please enter both BOCS Product ID and WooCommerce Product ID', 'bocs-wordpress')); ?>');
                        return;
                    }
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'bocs_add_product_mapping',
                            bocs_id: bocsId,
                            wc_id: wcId,
                            nonce: '<?php echo wp_create_nonce('bocs_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload the page to show the updated table
                                location.reload();
                            } else {
                                alert(response.data.message || '<?php echo esc_js(__('Error adding mapping', 'bocs-wordpress')); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('Network error when trying to add mapping', 'bocs-wordpress')); ?>');
                        }
                    });
                });
            });
            </script>
            <?php
        }
        
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=bocs-logs')) . '" class="button">' . __('View Logs', 'bocs-wordpress') . '</a></p>';
    }
} 