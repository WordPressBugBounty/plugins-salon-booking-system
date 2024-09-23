"use strict";

function sln_serviceBreakSliderRange($, $elements) {

    function initSliderRange($elements) {

        var duration = $('#_sln_service_duration');
        var break_duration_input = $elements.closest('.sln-slider--break').find("#_sln_service_break_duration");
        $elements.closest('.sln-slider--break').show();
        var from = $elements.closest('.sln-slider--break').find('.sln-slider--break-time-input-from');
        var to   = $elements.closest('.sln-slider--break').find('.sln-slider--break-time-input-to');

        // TIME RANGE //

        var max = Number(getMinutesDuration(duration.val())) + Number(break_duration_input.val());

        var slider_bar = $elements.closest('.sln-slider--break').find('.ui-slider-range');
        updateSlider(slider_bar, break_duration_input.val()*100/max, from.val()*100/max);
        slider_bar.closest('.sln-slider').find('.sln-slider--break-time-max-value').html(max);
        to.val(Number(from.val()) + Number(break_duration_input.val()));
        slider_bar.parent().find('.sln-slider--break-time-to-value').html(to.val());

        break_duration_input.closest('.sln-slider--break-length').find('.sln-slider--break-length--minus').off('click').on('click', function(){
            if(Number(break_duration_input.val()) <= Number(break_duration_input.attr('step'))) return;

            break_duration_input.val(Number(break_duration_input.val()) - Number(break_duration_input.attr('step')));
            max = Number(getMinutesDuration(duration.val())) + Number(break_duration_input.val());
            slider_bar.closest('.sln-slider--break').find('.sln-slider--break-time-max-value').html(max);
            updateSlider(slider_bar, break_duration_input.val()*100/max, from.val()*100/max);
            to.val(Number(from.val()) + Number(break_duration_input.val()));
            slider_bar.parent().find('.sln-slider--break-time-to-value').html(to.val());
        });
        break_duration_input.closest('.sln-slider--break-length').find('.sln-slider--break-length--plus').off('click').on('click', function(){
            if(Number(break_duration_input.val()) >= 180) return;
            break_duration_input.val(Number(break_duration_input.val()) + Number(break_duration_input.attr('step')));
            max = Number(getMinutesDuration(duration.val())) + Number(break_duration_input.val());
            slider_bar.closest('.sln-slider--break').find('.sln-slider--break-time-max-value').html(max);
            updateSlider(slider_bar, break_duration_input.val()*100/max, from.val()*100/max);
            to.val(Number(from.val()) + Number(break_duration_input.val()));
            slider_bar.parent().find('.sln-slider--break-time-to-value').html(to.val());
        });
        slider_bar.off('mousedown').on('mousedown', function(start_event){
            let diff = 0;
            let start_pos = $(this).css('left').replace('px', '') / $(this).closest('.service-break-slider-range').width() * 100;
            $(this).on('mousemove', function(event){
                diff = Math.round((start_event.pageX - event.pageX) * max / $(this).closest('.service-break-slider-range').width());
                updateSlider($(this), break_duration_input.val()*100/max, clamp(start_pos - diff*100/max, 0, 100-break_duration_input.val()*100/max));
                $(this).parent().find('.sln-slider--break-time-from-value').html(clamp(from.val() - diff, 0, max-break_duration_input.val()));
                $(this).parent().find('.sln-slider--break-time-to-value').html(clamp(to.val() - diff, break_duration_input.val(), max));
            });
        });
        slider_bar.off('mouseup').on('mouseup', function(){
            $(this).off('mousemove');
            let diff = $(this).css('left').replace('px','')/$(this).closest('.service-break-slider-range').width();
            from.val(Math.round(max*diff));
            to.val(Math.round(max*diff) + Number(break_duration_input.val()));
        });
    }

    $('#_sln_service_break_duration_enabled').on('change', function () {

        $('.sln-slider-break-duration-wrapper').toggleClass('hide', !$(this).prop('checked'));
        $(this).closest(".row").toggleClass("open");
        if ($(this).prop('checked')) {
            let break_duration_input = $('.sln-slider-break-duration-wrapper #_sln_service_break_duration');
            if ( break_duration_input.val() == '0' ) {
                break_duration_input.val(+break_duration_input.attr('step'));
                $('.sln-slider-break-duration-wrapper .sln-slider--break-time-input-from').val(parseInt(+break_duration_input.attr('step') / 2));
                $('.sln-slider-break-duration-wrapper .sln-slider--break-time-input-to').val(parseInt(+break_duration_input.attr('step') / 2) + +break_duration_input.attr('step'));
            }
            initSliderRange($elements);
        } else {
            $('.sln-slider-break-duration-wrapper #_sln_service_break_duration').val("0");
            $('.sln-slider-break-duration-wrapper .slider-time-input-from').val(0);
            $('.sln-slider-break-duration-wrapper .slider-time-input-to').val(0);

            $($elements).each(function() {
                if ($(this).hasClass('ui-slider')) {
                    $(this).closest('.sln-slider--break').hide();
                }
            });
        }
    }).trigger('change');

    function getMinutesDuration(_duration) {
        var tmp = _duration.split(':');
        return parseInt(tmp[0]) * 60 + parseInt(tmp[1]);
    }

    function getDuration(minutes) {
        var tmp = parseInt(minutes / 60) > 9 ? parseInt(minutes / 60) : "0" + parseInt(minutes / 60);
        tmp    += ":" + ((minutes % 60) > 9 ? (minutes % 60) : "0" + (minutes % 60));
        return tmp;
    }

    function clamp(val, min, max){
        if(val > max) return max;
        if(val < min) return min;
        return val;
    }

    function updateSlider(slider_bar, width, left){
        slider_bar.css('width', String(width) + '%');
        slider_bar.css('left', String(left) + '%');
        slider_bar.parent().find('.sln-slider--break-time-range-max').css('left', String(width + left) + '%' );
        slider_bar.parent().find('.sln-slider--break-time-range-min').css('left', String(left)+ '%');
    }
}

jQuery(function($) {
    sln_serviceBreakSliderRange($, $(".service-break-slider-range"));
});
