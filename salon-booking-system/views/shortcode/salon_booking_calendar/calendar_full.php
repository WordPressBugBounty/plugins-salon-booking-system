<div id="sln-salon-booking-calendar-shortcode" class="alignwide">
    <div class="sbc-header-row">
        <h2 class="sbc-heading"><?php esc_html_e('Booking Calendar by Assistant', 'salon-booking-system'); ?></h2>
        <span class="sbc-sync-badge" aria-live="polite" aria-atomic="true"></span>
    </div>
    <div class="wrapper">
	<?php include 'calendar_content.php' ?>
    </div>
</div>
<script>
(function () {
    var ROOT_ID  = 'sln-salon-booking-calendar-shortcode';
    var activeId = null;   // null = no filter (show all on desktop)
    var pageState = {};    // colId → currentPage (0-based)

    function isDesktop() {
        return window.innerWidth >= 768;
    }

    /* ── Tab filter ─────────────────────────────────────────── */

    function applyActive(root, targetId) {
        root.querySelectorAll('.sbc-tab').forEach(function (t) {
            t.classList.toggle('is-active', t.getAttribute('data-target') === targetId);
        });
        root.querySelectorAll('.sbc-col').forEach(function (col) {
            col.classList.toggle('is-active', col.id === targetId);
        });
        var grid = root.querySelector('.sbc-grid');
        if (grid) {
            grid.classList.toggle('is-filtered', !!targetId);
        }
    }

    /* ── Pagination ─────────────────────────────────────────── */

    function applyPage(col, page) {
        var pageSize  = parseInt(col.getAttribute('data-page-size'), 10) || 7;
        var totalDays = parseInt(col.getAttribute('data-total-days'), 10) || pageSize;
        var totalPages = Math.ceil(totalDays / pageSize);
        var start = page * pageSize;
        var end   = start + pageSize;

        col.querySelectorAll('.sbc-day').forEach(function (day) {
            var idx = parseInt(day.getAttribute('data-day-index'), 10);
            day.classList.toggle('sbc-day--hidden', idx < start || idx >= end);
        });

        var prevBtn = col.querySelector('.sbc-nav-btn--prev');
        var nextBtn = col.querySelector('.sbc-nav-btn--next');
        if (prevBtn) prevBtn.style.display = page === 0              ? 'none' : '';
        if (nextBtn) nextBtn.style.display = page >= totalPages - 1  ? 'none' : '';
    }

    function initAllPages(root) {
        root.querySelectorAll('.sbc-col').forEach(function (col) {
            var page = pageState[col.id] || 0;
            applyPage(col, page);
        });
    }

    /* ── Event delegation ───────────────────────────────────── */

    document.addEventListener('click', function (e) {
        var root = document.getElementById(ROOT_ID);
        if (!root) return;

        /* Tab clicks */
        var tab = e.target.closest('#' + ROOT_ID + ' .sbc-tab');
        if (tab) {
            var targetId = tab.getAttribute('data-target');
            if (isDesktop() && activeId === targetId) {
                activeId = null;
            } else {
                activeId = targetId;
            }
            applyActive(root, activeId);
            return;
        }

        /* Nav button clicks */
        var btn = e.target.closest('#' + ROOT_ID + ' .sbc-nav-btn');
        if (btn) {
            var col       = btn.closest('.sbc-col');
            var pageSize  = parseInt(col.getAttribute('data-page-size'), 10) || 7;
            var totalDays = parseInt(col.getAttribute('data-total-days'), 10) || pageSize;
            var totalPages = Math.ceil(totalDays / pageSize);
            var current   = pageState[col.id] || 0;

            if (btn.classList.contains('sbc-nav-btn--prev')) {
                current = Math.max(0, current - 1);
            } else if (btn.classList.contains('sbc-nav-btn--next')) {
                current = Math.min(totalPages - 1, current + 1);
            }

            pageState[col.id] = current;
            applyPage(col, current);
        }
    });

    /* ── Restore state after AJAX content refresh ───────────── */

    var wrapper = document.querySelector('#' + ROOT_ID + ' > .wrapper');
    if (wrapper && window.MutationObserver) {
        new MutationObserver(function () {
            var root = document.getElementById(ROOT_ID);
            if (!root) return;
            applyActive(root, activeId);
            initAllPages(root);
        }).observe(wrapper, { childList: true });
    }
}());
</script>
