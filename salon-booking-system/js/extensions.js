jQuery(function ($) {
    $(document)
        .on('click', '.extensions-section .extensions-wrapper .extensions-item:not(.disabled) .extensions-button.blue', function (e) {
            e.preventDefault();

            let btn = $(this);
            let loader = btn.find('.loader');
            let label = btn.find('.label');
            let labelText = label.text();
            let parent = btn.closest('.extensions-item');
            let wrapper = btn.closest('.extensions-wrapper');
            let allButtons = wrapper.find('.extensions-bottom .extensions-button');
            let productID = parent.data('id');
            let action = parent.data('action');
            let secErr = btn.closest('.extensions-bottom').find('.extensions-error');
            let currentItems = wrapper.find('.extensions-item[data-id="' + productID + '"]');
            let currentButtons = currentItems.find('.extensions-bottom .extensions-button .label');

            $.ajax({
                url: salon.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'salon',
                    method: 'installPlugin',
                    product_id: productID,
                    plugin_action: action,
                },
                beforeSend: function () {
                    secErr.text('');
                    btn.addClass('loading');
                    allButtons.addClass('loading');
                    loader.show();
                    label.text('Loading...');
                },
                success: function (response) {
                    if (response.success) {
                        //label.text(response.text);
                        //parent.data('action', response.action);
                        currentButtons.text(response.text);
                        currentItems.data('action', response.action);
                    } else {
                        secErr.text(response.message);
                        label.text(labelText);
                    }
                },
                error: function () {
                    secErr.text('Something went wrong.');
                    label.text(labelText);
                },
                complete: function () {
                    loader.hide();
                    btn.removeClass('loading');
                    allButtons.removeClass('loading');
                }
            });
        });

    sliderExtensions();

    function sliderExtensions() {
        $('.extensions-section').each(function () {
            const section = $(this);
            const wrapper = section.find('.extensions-wrapper');
            const slideCount = wrapper.children().length;

            if (wrapper.data('sort')) {
                const items = wrapper.find('.extensions-item');
                const installed = items.filter('[data-installed="1"]');
                const notInstalled = items.not('[data-installed="1"]');
                wrapper.empty().append(notInstalled).append(installed);
            }

            if (slideCount > 3) {
                wrapper.slick({
                    slidesToShow: 4,
                    slidesToScroll: 1,
                    dots: false,
                    arrows: true,
                    responsive: [
                        {
                            breakpoint: 1100,
                            settings: {
                                slidesToShow: 3,
                            }
                        },
                        {
                            breakpoint: 800,
                            settings: {
                                slidesToShow: 2,
                            }
                        },
                        {
                            breakpoint: 550,
                            settings: {
                                slidesToShow: 1,
                            }
                        }
                    ]
                });
            }
        });
    }
});