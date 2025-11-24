/**
 * AJAX Update Handler for Active Filters Breadcrumb
 * 
 * Monitors URL changes and refreshes the active filters breadcrumb
 */

(function ($) {
    'use strict';

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
            success: function (response) {
                if (response.success && response.data.html) {
                    // Replace the breadcrumb HTML with the updated version
                    $breadcrumb.replaceWith(response.data.html);
                }
            },
            error: function (xhr, status, error) {
                console.error('SAF: Failed to refresh breadcrumb', error);
            }
        });
    }

    /**
     * Monitor URL changes using History API
     * This catches both pushState and popstate events
     */
    var lastUrl = window.location.href;

    // Override pushState to detect URL changes
    var originalPushState = window.history.pushState;
    var originalReplaceState = window.history.replaceState;

    window.history.pushState = function () {
        originalPushState.apply(window.history, arguments);
        var newUrl = window.location.href;
        if (newUrl !== lastUrl) {
            lastUrl = newUrl;
            setTimeout(refreshBreadcrumb, 300);
        }
    };

    window.history.replaceState = function () {
        originalReplaceState.apply(window.history, arguments);
        var newUrl = window.location.href;
        if (newUrl !== lastUrl) {
            lastUrl = newUrl;
            setTimeout(refreshBreadcrumb, 300);
        }
    };

    // Handle browser back/forward buttons
    $(window).on('popstate', function () {
        var newUrl = window.location.href;
        if (newUrl !== lastUrl) {
            lastUrl = newUrl;
            setTimeout(refreshBreadcrumb, 300);
        }
    });

    // Fallback: periodic check for URL changes (every 500ms)
    setInterval(function () {
        var newUrl = window.location.href;
        if (newUrl !== lastUrl) {
            lastUrl = newUrl;
            setTimeout(refreshBreadcrumb, 100);
        }
    }, 500);

})(jQuery);
