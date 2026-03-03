jQuery(function ($) {

    // ----------------------------------------------------------------
    // AJAX: Install / Activate / Update
    // Triggers on outlined action buttons (.ext-btn--outline) only
    // ----------------------------------------------------------------
    $(document).on('click', '#ext-grid .ext-card .ext-btn--outline', function (e) {
        e.preventDefault();

        var btn       = $(this);
        var card      = btn.closest('.ext-card');
        var label     = btn.find('.label');
        var loader    = btn.find('.loader');
        var errEl     = card.find('.ext-error');
        var savedText = label.text();
        var productID = card.data('id');
        var action    = card.data('action');

        // Also lock any duplicate cards for the same product
        var sameCards   = $('#ext-grid .ext-card[data-id="' + productID + '"]');
        var sameBtns    = sameCards.find('.ext-btn--outline');

        $.ajax({
            url: salon.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action:        'salon',
                method:        'installPlugin',
                product_id:    productID,
                plugin_action: action,
            },
            beforeSend: function () {
                errEl.text('');
                sameBtns.addClass('loading');
                loader.show();
                label.text('');
            },
            success: function (response) {
                if (response.success) {
                    // Update label and next action on all matching cards
                    sameCards.data('action', response.action);
                    sameCards.find('.ext-btn--outline .label').text(response.text);
                } else {
                    errEl.text(response.message);
                    label.text(savedText);
                }
            },
            error: function () {
                errEl.text('Something went wrong.');
                label.text(savedText);
            },
            complete: function () {
                loader.hide();
                sameBtns.removeClass('loading');
            },
        });
    });

    // ----------------------------------------------------------------
    // Dismiss upgrade banner
    // ----------------------------------------------------------------
    $('#ext-banner-close').on('click', function () {
        $('#ext-banner').slideUp(200);
    });

    // ----------------------------------------------------------------
    // State: active filter tab + search query
    // ----------------------------------------------------------------
    var activeFilter = 'all';
    var searchQuery  = '';

    function applyFilters() {
        var q = searchQuery.toLowerCase().trim();

        $('#ext-grid .ext-card').each(function () {
            var card     = $(this);
            var category = card.data('category') || '';
            var title    = card.data('title') || '';

            var matchCategory = (activeFilter === 'all') || ((' ' + category + ' ').indexOf(' ' + activeFilter + ' ') !== -1);
            var matchSearch   = (q === '') || (title.indexOf(q) !== -1);

            if (matchCategory && matchSearch) {
                card.removeClass('ext-card--hidden');
            } else {
                card.addClass('ext-card--hidden');
            }
        });
    }

    // ----------------------------------------------------------------
    // Category filter tabs
    // ----------------------------------------------------------------
    $(document).on('click', '.ext-filter-tab', function () {
        var btn = $(this);
        if (btn.hasClass('ext-filter-tab--active')) return;

        $('.ext-filter-tab').removeClass('ext-filter-tab--active');
        btn.addClass('ext-filter-tab--active');

        activeFilter = btn.data('filter');
        applyFilters();
    });

    // ----------------------------------------------------------------
    // Search input — debounced
    // ----------------------------------------------------------------
    var searchTimer;
    $('#ext-search').on('input', function () {
        clearTimeout(searchTimer);
        var val = $(this).val();
        searchTimer = setTimeout(function () {
            searchQuery = val;
            applyFilters();
        }, 220);
    });

});
