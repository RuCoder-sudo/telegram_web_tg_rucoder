/**
 * Admin JavaScript for MoySklad Integration.
 *
 * @package WooMoySkladIntegration
 */

(function($) {
    'use strict';

    /**
     * Handle synchronization button clicks
     */
    function initSyncButtons() {
        $('.sync-button').on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const action = $button.data('action');
            let resultContainer;

            // Determine which result container to use
            switch (action) {
                case 'woo_moysklad_sync_categories':
                    resultContainer = $('#category-sync-result');
                    break;
                case 'woo_moysklad_sync_products':
                    resultContainer = $('#product-sync-result');
                    break;
                case 'woo_moysklad_sync_orders':
                    resultContainer = $('#order-sync-result');
                    break;
                case 'woo_moysklad_register_webhooks':
                    resultContainer = $('#webhook-result');
                    break;
                default:
                    return;
            }

            // Disable button and show loading state
            $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + $button.text());
            
            // Show starting message
            if (action === 'woo_moysklad_register_webhooks') {
                resultContainer.html('<div class="alert alert-info">' + wooMoyskladAdmin.i18n.webhook_register_started + '</div>');
            } else {
                resultContainer.html('<div class="alert alert-info">' + wooMoyskladAdmin.i18n.sync_started + '</div>');
            }

            // Make AJAX request
            $.ajax({
                url: wooMoyskladAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: action,
                    nonce: wooMoyskladAdmin.nonce
                },
                success: function(response) {
                    // Re-enable button
                    $button.prop('disabled', false).html($button.text());

                    if (response.success) {
                        // Handle webhook registration success
                        if (action === 'woo_moysklad_register_webhooks') {
                            displayWebhookResults(response.data, resultContainer);
                        } else {
                            // Handle sync success
                            displaySyncResults(response.data, resultContainer);
                        }
                    } else {
                        // Handle error
                        const errorMessage = response.data || 'Unknown error';
                        resultContainer.html('<div class="alert alert-danger">' + wooMoyskladAdmin.i18n.sync_error + ' ' + errorMessage + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    // Re-enable button
                    $button.prop('disabled', false).html($button.text());
                    
                    // Display error
                    resultContainer.html('<div class="alert alert-danger">' + wooMoyskladAdmin.i18n.sync_error + ' ' + error + '</div>');
                }
            });
        });
    }

    /**
     * Display synchronization results
     */
    function displaySyncResults(data, container) {
        let html = '<div class="alert alert-success">' + wooMoyskladAdmin.i18n.sync_success + '</div>';
        
        html += '<div class="mt-3"><h6>Results:</h6>';
        html += '<table class="table table-sm">';
        html += '<thead><tr><th>Action</th><th>Count</th></tr></thead>';
        html += '<tbody>';
        
        if (data.created !== undefined) {
            html += '<tr><td>Created</td><td>' + data.created + '</td></tr>';
        }
        
        if (data.updated !== undefined) {
            html += '<tr><td>Updated</td><td>' + data.updated + '</td></tr>';
        }
        
        if (data.skipped !== undefined) {
            html += '<tr><td>Skipped</td><td>' + data.skipped + '</td></tr>';
        }
        
        if (data.errors !== undefined) {
            html += '<tr><td>Errors</td><td>' + data.errors + '</td></tr>';
        }
        
        html += '</tbody></table></div>';
        
        container.html(html);
    }

    /**
     * Display webhook registration results
     */
    function displayWebhookResults(data, container) {
        let html = '<div class="alert alert-success">' + wooMoyskladAdmin.i18n.webhook_register_success + '</div>';
        
        html += '<div class="mt-3"><h6>Registered Webhooks:</h6>';
        html += '<table class="table table-sm">';
        html += '<thead><tr><th>Entity Type</th><th>Action</th><th>Status</th></tr></thead>';
        html += '<tbody>';
        
        for (let i = 0; i < data.length; i++) {
            const webhook = data[i];
            const statusClass = webhook.status === 'success' ? 'bg-success' : 'bg-danger';
            const statusText = webhook.status === 'success' ? 'Success' : 'Error: ' + webhook.message;
            
            html += '<tr>';
            html += '<td>' + webhook.type + '</td>';
            html += '<td>' + webhook.action + '</td>';
            html += '<td><span class="badge ' + statusClass + '">' + statusText + '</span></td>';
            html += '</tr>';
        }
        
        html += '</tbody></table></div>';
        
        container.html(html);
    }

    /**
     * Document ready
     */
    $(function() {
        initSyncButtons();
    });

})(jQuery);
