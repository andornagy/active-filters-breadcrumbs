/**
 * AJAX Update Handler for Active Filters Breadcrumb
 * 
 * Listens for Search and Filter (paid) AJAX updates and refreshes the active filters breadcrumb
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
            error: function () {
                console.error('Failed to refresh active filters breadcrumb');
            }
        });
    }

    /**
     * Search and Filter Pro (paid) events
     * Listens for S&F AJAX filtering events
     */

    // Event fired when Search and Filter starts processing filters
    $(document).on('sf:ajaxstart', function () {
        // Optional: show loading state
    });

    // Event fired when Search and Filter finishes AJAX and updates results
    $(document).on('sf:ajaxfinish', function () {
        refreshBreadcrumb();
    });

    // Alternative event (some versions use this)
    $(document).on('sf:filterupdate', function () {
        refreshBreadcrumb();
    });

    // Fallback: watch for URL changes (S&F might use History API)
    var lastUrl = window.location.href;
    setInterval(function () {
        if (window.location.href !== lastUrl) {
            lastUrl = window.location.href;
            // Give a small delay for the DOM to update
            setTimeout(refreshBreadcrumb, 200);
        }
    }, 500);

})(jQuery);
