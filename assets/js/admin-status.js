(function($) {
    'use strict';

    var CpcStatus = {
        init: function() {
            this.bindEvents();
            this.initializeTooltips();
            this.setupLogViewer();
        },

        bindEvents: function() {
            $('.run-tests').on('click', this.runTests.bind(this));
            $('.clear-logs').on('click', this.clearLogs.bind(this));
            $('.create-sample').on('click', this.createSampleProduct.bind(this));
            $('.toggle-section').on('click', this.toggleSection);
        },

        initializeTooltips: function() {
            $('.status-badge').each(function() {
                var $badge = $(this);
                if ($badge.hasClass('fail')) {
                    $badge.tooltip({
                        content: $badge.siblings('.error-message').text(),
                        position: { my: 'left center', at: 'right+10 center' }
                    });
                }
            });
        },

        setupLogViewer: function() {
            var $logViewer = $('.log-viewer');
            if ($logViewer.length) {
                // Auto-scroll to bottom
                var pre = $logViewer.find('pre')[0];
                if (pre) {
                    pre.scrollTop = pre.scrollHeight;
                }

                // Add refresh button
                $logViewer.prepend(
                    $('<button>')
                        .addClass('button refresh-logs')
                        .text('Refresh')
                        .on('click', this.refreshLogs.bind(this))
                );
            }
        },

        runTests: function(e) {
            e.preventDefault();
            var $button = $(e.currentTarget);
            var $results = $('#system-tests tbody');

            $button.prop('disabled', true)
                  .text('Running Tests...');

            $.ajax({
                url: cpcStatus.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpc_run_system_test',
                    nonce: cpcStatus.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.updateTestResults(response.data);
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError(cpcStatus.i18n.error);
                }.bind(this),
                complete: function() {
                    $button.prop('disabled', false)
                           .text('Run Tests');
                }
            });
        },

        updateTestResults: function(results) {
            var $tbody = $('#system-tests tbody');
            $tbody.empty();

            Object.keys(results).forEach(function(test) {
                var result = results[test];
                var $row = $('<tr>');
                
                // Test name
                $row.append(
                    $('<td>').text(
                        test.replace(/_/g, ' ')
                            .replace(/\b\w/g, function(l) { return l.toUpperCase(); })
                    )
                );

                // Status badge
                var $status = $('<td>').append(
                    $('<span>')
                        .addClass('status-badge')
                        .addClass(result.test ? 'pass' : 'fail')
                        .text(result.test ? 'PASS' : 'FAIL')
                );
                $row.append($status);

                // Actions
                var $actions = $('<td>');
                if (!result.test) {
                    $actions.append(
                        $('<span>').addClass('error-message')
                                 .text(result.message)
                    );
                    $actions.append(
                        $('<a>').addClass('button button-small')
                               .attr('href', result.docs)
                               .attr('target', '_blank')
                               .text('View Docs')
                    );
                }
                $row.append($actions);

                $tbody.append($row);
            });

            this.initializeTooltips();
        },

        clearLogs: function(e) {
            e.preventDefault();

            if (!confirm(cpcStatus.i18n.confirmClearLogs)) {
                return;
            }

            var $button = $(e.currentTarget);
            $button.prop('disabled', true);

            $.ajax({
                url: cpcStatus.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpc_clear_logs',
                    nonce: cpcStatus.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.log-viewer pre').empty();
                        $('.log-viewer').append(
                            $('<p>').addClass('no-logs')
                                   .text('No logs available.')
                        );
                        this.showSuccess(cpcStatus.i18n.logsCleared);
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError(cpcStatus.i18n.error);
                }.bind(this),
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        refreshLogs: function(e) {
            e.preventDefault();
            var $button = $(e.currentTarget);
            $button.prop('disabled', true);

            $.ajax({
                url: cpcStatus.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpc_get_logs',
                    nonce: cpcStatus.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var $logViewer = $('.log-viewer');
                        $logViewer.find('pre').text(response.data);
                        
                        if (!response.data) {
                            $logViewer.append(
                                $('<p>').addClass('no-logs')
                                       .text('No logs available.')
                            );
                        }
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError(cpcStatus.i18n.error);
                }.bind(this),
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        createSampleProduct: function(e) {
            e.preventDefault();
            var $button = $(e.currentTarget);
            $button.prop('disabled', true)
                  .text('Creating...');

            $.ajax({
                url: cpcStatus.ajaxurl,
                type: 'POST',
                data: {
                    action: 'cpc_create_sample_product',
                    nonce: cpcStatus.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        this.showError(response.data);
                    }
                }.bind(this),
                error: function() {
                    this.showError(cpcStatus.i18n.error);
                }.bind(this),
                complete: function() {
                    $button.prop('disabled', false)
                           .text('Create Sample Product');
                }
            });
        },

        toggleSection: function(e) {
            e.preventDefault();
            var $section = $(this).closest('.status-section');
            $section.find('.section-content').slideToggle();
            $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
        },

        showError: function(message) {
            var $notice = $('<div>')
                .addClass('notice notice-error is-dismissible')
                .append($('<p>').text(message));

            $('.wrap').prepend($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },

        showSuccess: function(message) {
            var $notice = $('<div>')
                .addClass('notice notice-success is-dismissible')
                .append($('<p>').text(message));

            $('.wrap').prepend($notice);
            
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    $(function() {
        CpcStatus.init();
    });

})(jQuery);
