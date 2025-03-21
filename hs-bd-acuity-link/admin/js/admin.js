/**
 * Acuity Integration Admin Scripts
 */
(function($) {
    'use strict';
    
    /**
     * Handle manual sync button
     */
    function initManualSync() {
        $('.acuity-manual-sync').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            $button.text(acuityAdmin.syncingText).prop('disabled', true);
            
            $.ajax({
                url: acuityAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'acuity_manual_sync',
                    _ajax_nonce: acuityAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.text(acuityAdmin.doneText);
                        
                        // Show success message
                        var $message = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        $('.acuity-admin-header').after($message);
                        
                        // Reset button after delay
                        setTimeout(function() {
                            $button.text(originalText).prop('disabled', false);
                        }, 2000);
                        
                        // Reload page after delay to show updated data
                        setTimeout(function() {
                            window.location.reload();
                        }, 2500);
                    } else {
                        $button.text(originalText).prop('disabled', false);
                        
                        // Show error message
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error occurred';
                        var $error = $('<div class="notice notice-error is-dismissible"><p>' + errorMsg + '</p></div>');
                        $('.acuity-admin-header').after($error);
                    }
                },
                error: function() {
                    $button.text(originalText).prop('disabled', false);
                    
                    // Show generic error message
                    var $error = $('<div class="notice notice-error is-dismissible"><p>Server error occurred. Please try again.</p></div>');
                    $('.acuity-admin-header').after($error);
                }
            });
        });
    }
    
    /**
     * Handle tabs on settings page
     */
    function initSettingsTabs() {
        $('.nav-tab-wrapper a').on('click', function(e) {
            e.preventDefault();
            
            var $this = $(this);
            var tabId = $this.attr('href').replace('#', '');
            
            // Update tab URL
            var url = window.location.href.split('&tab=')[0] + '&tab=' + tabId;
            history.pushState({}, '', url);
            
            // Update active tab
            $('.nav-tab-wrapper a').removeClass('nav-tab-active');
            $this.addClass('nav-tab-active');
            
            // Show tab content
            $('.acuity-tabs-content > div').removeClass('active');
            $('#' + tabId).addClass('active');
        });
    }
    
    /**
     * Handle log detail toggles
     */
    function initLogToggles() {
        $('.acuity-toggle-details').on('click', function() {
            var $details = $(this).next('.acuity-error-details');
            $details.toggle();
            
            if ($details.is(':visible')) {
                $(this).text('Hide Details');
            } else {
                $(this).text('Show Details');
            }
        });
    }
    
    /**
     * Initialize all functionality
     */
    function init() {
        initManualSync();
        initSettingsTabs();
        initLogToggles();
    }
    
    // Initialize when DOM is ready
    $(document).ready(init);
    
})(jQuery);
