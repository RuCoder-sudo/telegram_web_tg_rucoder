/**
 * Admin JavaScript for WooCommerce MoySklad Integration.
 *
 * @since 1.0.0
 */
(function($) {
    'use strict';

    /**
     * Handle order synchronization buttons
     */
    function setupOrderSyncButtons() {
        // Original single order sync
        $('#sync-order-button').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(wooMoySkladAdmin.messages.confirmSync)) {
                return;
            }
            
            var $button = $(this);
            var $status = $('#order-sync-status');
            var $stopButton = $('#stop-order-sync');
            var orderId = $('#order_id').val();
            
            if (!orderId) {
                $status.html('<div class="notice notice-error inline"><p>Укажите ID заказа</p></div>');
                return;
            }
            
            $button.prop('disabled', true).addClass('loading');
            $status.html(wooMoySkladAdmin.messages.syncInProgress);
            
            // Показываем кнопку остановки
            $stopButton.show();
            
            // Update sync status flag
            updateSyncStatus(true);
            
            $.ajax({
                url: wooMoySkladAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_moysklad_sync_orders',
                    nonce: wooMoySkladAdmin.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    } else {
                        $status.html('<div class="notice notice-error inline"><p>' + 
                            (response.data.message || wooMoySkladAdmin.messages.syncFailed) + '</p></div>');
                    }
                },
                error: function() {
                    $status.html('<div class="notice notice-error inline"><p>' + wooMoySkladAdmin.messages.syncFailed + '</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('loading');
                    updateSyncStatus(false);
                    $stopButton.hide();
                }
            });
        });
        
        // Новая форма синхронизации отдельного заказа
        $('#sync-single-order').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Вы уверены, что хотите синхронизировать этот заказ?')) {
                return;
            }
            
            var $button = $(this);
            var $status = $('#order-sync-status');
            var $stopButton = $('#stop-order-sync');
            var orderId = $('#single-order-id').val();
            
            if (!orderId) {
                $status.html('<div class="notice notice-error inline"><p>Укажите ID заказа</p></div>');
                return;
            }
            
            $button.prop('disabled', true);
            $status.html('<div class="notice notice-info inline"><p>Синхронизация заказа #' + orderId + '...</p></div>');
            
            // Показываем кнопку остановки
            $stopButton.show();
            
            // Update sync status flag
            updateSyncStatus(true);
            
            $.ajax({
                url: wooMoySkladAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_moysklad_sync_orders',
                    nonce: wooMoySkladAdmin.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    } else {
                        $status.html('<div class="notice notice-error inline"><p>' + 
                            (response.data.message || 'Не удалось синхронизировать заказ.') + '</p></div>');
                    }
                },
                error: function() {
                    $status.html('<div class="notice notice-error inline"><p>Произошла ошибка при синхронизации заказа.</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    updateSyncStatus(false);
                    $stopButton.hide();
                }
            });
        });
        
        // Bulk order sync
        $('#bulk-sync-orders').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(wooMoySkladAdmin.messages.confirmSync)) {
                return;
            }
            
            var $button = $(this);
            var $status = $('#sync-status-message');
            var $progressBar = $('#bulk-sync-progress');
            var $stopButton = $('#stop-order-sync');
            
            var fromDate = $('#date_from').val();
            var toDate = $('#date_to').val();
            var statuses = [];
            
            $('input[name="order_statuses[]"]:checked').each(function() {
                statuses.push($(this).val());
            });
            
            if (statuses.length === 0) {
                $status.html('<div class="notice notice-error inline"><p>Выберите хотя бы один статус заказа</p></div>');
                return;
            }
            
            $button.prop('disabled', true);
            $status.html(wooMoySkladAdmin.messages.syncInProgress);
            $progressBar.show().find('.progress-bar').css('width', '0%').attr('aria-valuenow', 0).text('0%');
            $stopButton.show();
            
            // Update sync status flag
            updateSyncStatus(true);
            
            $.ajax({
                url: wooMoySkladAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_moysklad_sync_order_batch',
                    nonce: wooMoySkladAdmin.nonce,
                    date_from: fromDate,
                    date_to: toDate,
                    statuses: statuses
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    } else {
                        $status.html('<div class="notice notice-error inline"><p>' + 
                            (response.data.message || wooMoySkladAdmin.messages.syncFailed) + '</p></div>');
                    }
                },
                error: function() {
                    $status.html('<div class="notice notice-error inline"><p>' + wooMoySkladAdmin.messages.syncFailed + '</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $stopButton.hide();
                    updateSyncStatus(false);
                }
            });
        });
    }

    /**
     * Handle sync button clicks
     */
    function setupSyncButtons() {
        // Sync products
        $('#sync-products-button').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(wooMoySkladAdmin.messages.confirmSync)) {
                return;
            }
            
            var $button = $(this);
            var $status = $('#sync-status');
            var $stopButton = $('#stop-sync-button');
            
            $button.prop('disabled', true).addClass('loading');
            $status.html(wooMoySkladAdmin.messages.syncInProgress);
            $stopButton.show();
            
            // Update sync status flag
            updateSyncStatus(true);
            
            $.ajax({
                url: wooMoySkladAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_moysklad_sync_products',
                    nonce: wooMoySkladAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html(response.data.message);
                        
                        // Refresh sync status after a short delay
                        setTimeout(function() {
                            getSyncStatus();
                        }, 1000);
                    } else {
                        $status.html(response.data.message || wooMoySkladAdmin.messages.syncFailed);
                        updateSyncStatus(false);
                        $stopButton.hide();
                    }
                },
                error: function() {
                    $status.html(wooMoySkladAdmin.messages.syncFailed);
                    updateSyncStatus(false);
                    $stopButton.hide();
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('loading');
                }
            });
        });
        
        // Sync inventory
        $('#sync-inventory-button').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(wooMoySkladAdmin.messages.confirmSync)) {
                return;
            }
            
            var $button = $(this);
            var $status = $('#inventory-sync-status');
            
            $button.prop('disabled', true).addClass('loading');
            $status.html(wooMoySkladAdmin.messages.syncInProgress);
            
            $.ajax({
                url: wooMoySkladAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_moysklad_sync_inventory',
                    nonce: wooMoySkladAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html(response.data.message);
                        
                        // Refresh sync status after a short delay
                        setTimeout(function() {
                            getSyncStatus();
                        }, 1000);
                    } else {
                        $status.html(response.data.message || wooMoySkladAdmin.messages.syncFailed);
                    }
                },
                error: function() {
                    $status.html(wooMoySkladAdmin.messages.syncFailed);
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('loading');
                }
            });
        });
        
        // Sync categories
        $('#sync-categories-button').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(wooMoySkladAdmin.messages.confirmSync)) {
                return;
            }
            
            var $button = $(this);
            var $status = $('#category-sync-status');
            
            $button.prop('disabled', true).addClass('loading');
            $status.html(wooMoySkladAdmin.messages.syncInProgress);
            
            $.ajax({
                url: wooMoySkladAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_moysklad_sync_categories',
                    nonce: wooMoySkladAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html(response.data.message);
                    } else {
                        $status.html(response.data.message || wooMoySkladAdmin.messages.syncFailed);
                    }
                },
                error: function() {
                    $status.html(wooMoySkladAdmin.messages.syncFailed);
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('loading');
                }
            });
        });
    }
    
    /**
     * Get synchronization status
     */
    function getSyncStatus() {
        if ($('#sync-status').length === 0) {
            return;
        }
        
        $.ajax({
            url: wooMoySkladAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woo_moysklad_get_sync_status',
                nonce: wooMoySkladAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Update last sync times
                    if ($('#last-sync-time').length > 0) {
                        $('#last-sync-time').text(data.lastSyncTime);
                    }
                    
                    if ($('#last-inventory-sync-time').length > 0) {
                        $('#last-inventory-sync-time').text(data.lastInventorySyncTime);
                    }
                    
                    // Update sync status
                    updateSyncStatus(data.inProgress);
                    
                    // Re-check if sync is in progress
                    if (data.inProgress) {
                        setTimeout(getSyncStatus, 5000);
                    }
                }
            }
        });
    }
    
    /**
     * Update sync status
     */
    function updateSyncStatus(inProgress) {
        var $stopButton = $('#stop-sync-button');
        
        if (inProgress) {
            $('.sync-status-indicator').addClass('sync-active').removeClass('sync-inactive');
            $('.sync-status-text').text($('.sync-status-indicator').data('active-text'));
            $stopButton.show();
        } else {
            $('.sync-status-indicator').removeClass('sync-active').addClass('sync-inactive');
            $('.sync-status-text').text($('.sync-status-indicator').data('inactive-text'));
            $stopButton.hide();
        }
    }
    
    /**
     * Setup stop sync button
     */
    function setupStopSyncButton() {
        // Обработчик для основной кнопки остановки
        $('#stop-sync-button').on('click', function(e) {
            stopSynchronization(e, $(this), $('#sync-status'));
        });
        
        // Обработчик для кнопки остановки синхронизации отдельного заказа
        $('#stop-order-sync').on('click', function(e) {
            stopSynchronization(e, $(this), $('#order-sync-status'));
        });
    }
    
    // Общая функция остановки синхронизации
    function stopSynchronization(e, $button, $statusElement) {
        e.preventDefault();
        
        if (!confirm('Вы уверены, что хотите остановить синхронизацию?')) {
            return;
        }
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: wooMoySkladAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'woo_moysklad_stop_sync',
                nonce: wooMoySkladAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateSyncStatus(false);
                    $statusElement.html('<div class="notice notice-info inline"><p>' + 
                        wooMoySkladAdmin.messages.syncStopped + '</p></div>');
                } else {
                    $statusElement.html('<div class="notice notice-error inline"><p>' + 
                        (response.data.message || 'Не удалось остановить синхронизацию.') + '</p></div>');
                }
            },
            error: function() {
                $statusElement.html('<div class="notice notice-error inline"><p>' + 
                    'Ошибка при попытке остановить синхронизацию.' + '</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
                // Скрываем все кнопки остановки
                $('.button-danger').hide();
            }
        });
    }
    
    /**
     * Handle settings tabs
     */
    function setupSettingsTabs() {
        var $tabs = $('.woo-moysklad-tabs');
        var $tabLinks = $('.woo-moysklad-tab-link');
        var $tabContents = $('.woo-moysklad-tab-content');
        
        if ($tabs.length === 0) {
            return;
        }
        
        $tabLinks.on('click', function(e) {
            e.preventDefault();
            
            var tabId = $(this).attr('href');
            
            // Update active tab link
            $tabLinks.removeClass('active');
            $(this).addClass('active');
            
            // Show selected tab content
            $tabContents.hide();
            $(tabId).show();
            
            // Store active tab in hash
            window.location.hash = tabId;
        });
        
        // Set active tab from hash or default to first tab
        var activeTab = window.location.hash || $tabLinks.first().attr('href');
        $tabLinks.filter('[href="' + activeTab + '"]').click();
    }
    
    /**
     * Test API connection
     */
    function setupConnectionTest() {
        $('#test-connection-button').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $status = $('#connection-status');
            
            $button.prop('disabled', true).addClass('loading');
            $status.html('');
            
            $.ajax({
                url: wooMoySkladAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_moysklad_test_connection',
                    nonce: wooMoySkladAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<div class="notice notice-success inline"><p>' + wooMoySkladAdmin.messages.connectionSuccess + '</p></div>');
                    } else {
                        $status.html('<div class="notice notice-error inline"><p>' + (response.data.message || wooMoySkladAdmin.messages.connectionFailed) + '</p></div>');
                    }
                },
                error: function() {
                    $status.html('<div class="notice notice-error inline"><p>' + wooMoySkladAdmin.messages.connectionFailed + '</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('loading');
                }
            });
        });
    }
    
    /**
     * Setup webhook registration
     */
    function setupWebhookRegistration() {
        $('#register-webhooks-button').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $status = $('#webhook-status');
            
            $button.prop('disabled', true).addClass('loading');
            $status.html('');
            
            $.ajax({
                url: wooMoySkladAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_moysklad_register_webhooks',
                    nonce: wooMoySkladAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<div class="notice notice-success inline"><p>' + wooMoySkladAdmin.messages.webhooksRegistered + '</p></div>');
                        
                        if (response.data && response.data.webhook_url) {
                            $('#webhook-url').text(response.data.webhook_url);
                            $('.webhook-url-container').show();
                        }
                    } else {
                        $status.html('<div class="notice notice-error inline"><p>' + (response.data.message || wooMoySkladAdmin.messages.webhooksFailed) + '</p></div>');
                    }
                },
                error: function() {
                    $status.html('<div class="notice notice-error inline"><p>' + wooMoySkladAdmin.messages.webhooksFailed + '</p></div>');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('loading');
                }
            });
        });
    }
    
    /**
     * Setup logs functionality
     */
    function setupLogs() {
        var $logsTable = $('#woo-moysklad-logs-table');
        var $pagination = $('#woo-moysklad-logs-pagination');
        var $levelFilter = $('#log-level-filter');
        var $clearLogsButton = $('#clear-logs-button');
        var currentPage = 1;
        var logsPerPage = 20;
        
        if ($logsTable.length === 0) {
            return;
        }
        
        // Load logs
        function loadLogs(page, level) {
            var offset = (page - 1) * logsPerPage;
            
            $.ajax({
                url: wooMoySkladAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_moysklad_get_logs',
                    nonce: wooMoySkladAdmin.nonce,
                    limit: logsPerPage,
                    offset: offset,
                    level: level
                },
                success: function(response) {
                    if (response.success) {
                        renderLogs(response.data.logs, response.data.total);
                    }
                }
            });
        }
        
        // Render logs
        function renderLogs(logs, total) {
            var $tbody = $logsTable.find('tbody');
            $tbody.empty();
            
            if (logs.length === 0) {
                $tbody.html('<tr><td colspan="4">' + 'Логи не найдены' + '</td></tr>');
                $pagination.empty();
                return;
            }
            
            // Render logs
            $.each(logs, function(index, log) {
                var $row = $('<tr></tr>');
                var levelClass = 'log-level-' + log.level;
                
                $row.append('<td>' + log.timestamp + '</td>');
                $row.append('<td><span class="' + levelClass + '">' + log.level + '</span></td>');
                $row.append('<td>' + log.message + '</td>');
                
                var context = log.context ? '<pre>' + log.context + '</pre>' : '';
                $row.append('<td>' + context + '</td>');
                
                $tbody.append($row);
            });
            
            // Render pagination
            renderPagination(total);
        }
        
        // Render pagination
        function renderPagination(total) {
            var pages = Math.ceil(total / logsPerPage);
            $pagination.empty();
            
            if (pages <= 1) {
                return;
            }
            
            var $ul = $('<ul class="woo-moysklad-pagination"></ul>');
            
            // Previous button
            var $prev = $('<li><a href="#" class="prev-page">&laquo;</a></li>');
            if (currentPage === 1) {
                $prev.addClass('disabled');
            }
            $ul.append($prev);
            
            // Page numbers
            var start = Math.max(1, currentPage - 2);
            var end = Math.min(pages, start + 4);
            
            if (end - start < 4) {
                start = Math.max(1, end - 4);
            }
            
            for (var i = start; i <= end; i++) {
                var $page = $('<li><a href="#" class="page-number" data-page="' + i + '">' + i + '</a></li>');
                if (i === currentPage) {
                    $page.addClass('active');
                }
                $ul.append($page);
            }
            
            // Next button
            var $next = $('<li><a href="#" class="next-page">&raquo;</a></li>');
            if (currentPage === pages) {
                $next.addClass('disabled');
            }
            $ul.append($next);
            
            $pagination.append($ul);
            
            // Attach event handlers
            $ul.find('.page-number').on('click', function(e) {
                e.preventDefault();
                currentPage = parseInt($(this).data('page'), 10);
                loadLogs(currentPage, $levelFilter.val());
            });
            
            $ul.find('.prev-page').on('click', function(e) {
                e.preventDefault();
                if (currentPage > 1) {
                    currentPage--;
                    loadLogs(currentPage, $levelFilter.val());
                }
            });
            
            $ul.find('.next-page').on('click', function(e) {
                e.preventDefault();
                if (currentPage < pages) {
                    currentPage++;
                    loadLogs(currentPage, $levelFilter.val());
                }
            });
        }
        
        // Level filter change
        $levelFilter.on('change', function() {
            currentPage = 1;
            loadLogs(currentPage, $(this).val());
        });
        
        // Clear logs
        $clearLogsButton.on('click', function(e) {
            e.preventDefault();
            
            if (!confirm(wooMoySkladAdmin.messages.confirmClearLogs)) {
                return;
            }
            
            $.ajax({
                url: wooMoySkladAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_moysklad_clear_logs',
                    nonce: wooMoySkladAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload logs
                        loadLogs(1, $levelFilter.val());
                    }
                }
            });
        });
        
        // Initial load
        loadLogs(currentPage, $levelFilter.val());
    }
    
    /**
     * Initialize on DOM ready
     */
    $(function() {
        setupSyncButtons();
        setupOrderSyncButtons();
        setupStopSyncButton();
        setupSettingsTabs();
        setupConnectionTest();
        setupWebhookRegistration();
        setupLogs();
        
        // Initial sync status check
        if (wooMoySkladAdmin.syncInProgress === '1') {
            updateSyncStatus(true);
        }
        
        getSyncStatus();
    });

})(jQuery);
