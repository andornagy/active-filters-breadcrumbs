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
});
