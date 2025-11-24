/**
 * AJAX Update Handler for Active Filters Breadcrumb
 * 
 * Listens for Elementor query loop updates and refreshes the active filters breadcrumb
 */

(function($) {
    'use strict';

    // Elementor Pro Query Control update events
    // Fires when query loop results are updated via AJAX
    $(document).on('elementor_pro/query_control/render_items/success', function(event, settings, queryData) {
        refreshBreadcrumb();
    });

    // Also listen for elementor frontend updates
    $(document).on('elementor/frontend/init', function() {
        // Hook into Elementor Pro query control updates if available
        if (window.elementorProFrontend) {
            $(document).on('elementor_pro/query_control/items/render', function() {
                refreshBreadcrumb();
            });
        }
    });

    // Listen for custom filter button clicks (common Elementor filter pattern)
    $(document).on('click', '.e-filter-button, [data-elementor-filter]', function() {
        // Delay slightly to allow URL to update
        setTimeout(refreshBreadcrumb, 500);
    });

    /**
     * Refresh the breadcrumb via AJAX
     */
    function refreshBreadcrumb() {
        var $breadcrumb = $('[data-saf-breadcrumb="1"]');
        
        if (!$breadcrumb.length) {
            return; // No breadcrumb on this page
        }

        $.ajax({
            url: safAjax.ajaxUrl,
            type: 'POST',
            data: {
                action: 'saf_refresh_breadcrumb',
                nonce: safAjax.nonce,
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    // Replace the breadcrumb HTML with the updated version
                    $breadcrumb.replaceWith(response.data.html);
                }
            },
            error: function() {
                console.error('Failed to refresh active filters breadcrumb');
            }
        });
    }

})(jQuery);
