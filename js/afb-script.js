document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.afb-remove-btn');
        if (!btn) {
            return;
        }

        // Allow modifier clicks to open in new tab/window
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
            return;
        }

        e.preventDefault();
        var pill = btn.closest('.afb-filter');
        if (pill) {
            pill.classList.add('afb-removing');
        }

        // Delay to let the CSS fade animate, then navigate
        setTimeout(function () {
            window.location.href = btn.getAttribute('href');
        }, 180);
    });

    (function ($) {
        // Intercept remove filter (X) clicks
        $(document).on('click', '.afb-remove-btn', function (e) {
            e.preventDefault();
            var url = $(this).attr('href');
            if (!url) return;

            // Parse the URL to get the param to remove
            var urlObj = new URL(url, window.location.origin);
            var params = new URLSearchParams(urlObj.search);

            params.forEach(function (value, key) {
                // Find checkbox or input by value (for checkboxes)
                var $input = $('[data-option-value="' + value + '"] input[type="checkbox"], [name="' + key + '"][value="' + value + '"]');
                if ($input.length) {
                    $input.prop('checked', false).trigger('change');
                } else {
                    // For text inputs, clear the value
                    var $textInput = $('[name="' + key + '"]:input[type="text"]');
                    if ($textInput.length) {
                        $textInput.val('').trigger('change');
                    }
                }
            });

            // Trigger a change on the filter wrapper to ensure AJAX fires
            $('.search-filter, .searchandfilter, .search-filter-form').trigger('change');
        });

        // Intercept clear all clicks
        $(document).on('click', '.afb-clear', function (e) {
            e.preventDefault();
            var url = $(this).attr('href');
            // Try AJAX clear first
            var $filter = $('.search-filter, .searchandfilter, .search-filter-form'); // Add all possible classes
            if ($filter.length) {
                $filter.find('input[type="checkbox"], input[type="radio"]').prop('checked', false).trigger('change');
                $filter.find('input[type="text"], input[type="search"], select').val('').trigger('change');
                $filter.trigger('change');
                // Optionally, trigger submit if your filter system needs it:
                // $filter.trigger('submit');
                setTimeout(function () {
                    // If nothing changed after 500ms, force redirect as fallback
                    if (window.location.search && window.location.search.indexOf('search-filter-api=1') !== -1) {
                        window.location.href = window.location.pathname;
                    }
                }, 500);
            } else {
                // No filter found, just redirect
                window.location.href = window.location.pathname;
            }
        });
    })(jQuery);
});
