"use strict";

var sln_discount_rule_html;
jQuery(function ($) {
    var $rule_wrapper  = $('.sln_discount_rule[data-rule-id=__new_discount_rule__]').wrap('<p></p>').closest('p');
    sln_discount_rule_html = $rule_wrapper.html();
    $rule_wrapper.remove();

    sln_bindDiscountTypeChange($);
    sln_bindDiscountRuleModeChange($);
    sln_bindDiscountRuleRemove($);
    sln_bindDiscountRuleAdd($);

    // Exclusion rules: mode selector (weekdays / specific_dates)
    sln_bindExclusionRuleModeChange($);

    // Exclusion rules: time restriction toggle and second-shift toggle
    sln_bindExclusionTimeRestriction($);
    sln_bindExclusionSecondShift($);
});

function sln_bindDiscountTypeChange($) {
    $('[data-type=discount-type]').off('change').on('change', function() {
        $('.sln_discount_type').addClass('hide');
        $('.sln_discount_type--'+$(this).val()).removeClass('hide');
    });
}

function sln_bindDiscountRuleModeChange($) {
    $('[data-type=discount-rule-mode]').off('change').on('change', function() {
        var $rule = $(this).closest('.sln_discount_rule');
        $rule.find('.sln_discount_rule_mode_details').addClass('hide');
        $rule.find('.sln_discount_rule_mode_details--'+$(this).val()).removeClass('hide');
    }).trigger('change');
}

function sln_bindDiscountRuleAdd($) {
    $('[data-action=add-discount-rule]').off('click').on('click', function() {

        var id = 0;
        if ($('.sln_discount_rule').length > 0) {
            id = parseInt($('.sln_discount_rule:last').attr('data-rule-id')) + 1;
        }
        var rule_html = sln_discount_rule_html.replace(/__new_discount_rule__/g, id).replace(/hide/, ''); // remove only first 'hide'

        $('#sln_discount_rules').append($(rule_html));

        sln_bindDiscountRuleModeChange($);
        sln_bindDiscountRuleRemove($);

        sln_createSelect2Full($);
        sln_initDatepickers($);
    });
}

function sln_bindDiscountRuleRemove($) {
    $('[data-action=remove-discount-rule]').off('click').on('click', function() {
        $(this).closest('.sln_discount_rule').remove();
    });
}

/**
 * Handles the "Exclude bookings on" mode selector inside each exclusion-rule row.
 * Uses event delegation so it works for both existing rows and rows added via the
 * customRulesCollections.js prototype mechanism.
 */
function sln_bindExclusionRuleModeChange($) {
    // Use document-level delegation so dynamically added rows are covered automatically.
    $(document).off('change.sln-exclusion', '[data-type="discount-exclusion-rule-mode"]')
        .on('change.sln-exclusion', '[data-type="discount-exclusion-rule-mode"]', function () {
            var $row  = $(this).closest('.sln-booking-rule');
            var mode  = $(this).val();

            // Show / hide the correct mode panel
            $row.find('.sln-discount-exclusion-mode').addClass('hide');
            $row.find('.sln-discount-exclusion-mode--' + mode).removeClass('hide');

            // The date-range scope ("always / apply from") is only relevant for weekdays mode.
            // Specific-dates mode already carries its own date information.
            if (mode === 'specific_dates') {
                $row.find('.sln-always-valid-section').addClass('hide');
                // Trigger datepicker initialisation / active-date highlighting
                setTimeout(function () {
                    $('body').trigger('sln_date');
                    setTimeout(function () {
                        if (typeof sln_updateSelectedDatesList === 'function') {
                            sln_updateSelectedDatesList($row);
                        }
                    }, 100);
                }, 50);
            } else {
                $row.find('.sln-always-valid-section').removeClass('hide');
            }
        });

    // Set correct initial state for every existing exclusion-rule row on page load.
    $('[data-type="discount-exclusion-rule-mode"]').trigger('change');
}

/**
 * "All day / Time restriction" toggle inside each exclusion-rule row.
 * Shows or hides the shift sliders. Uses document-level delegation so
 * dynamically added rows (via prototype) are handled automatically.
 * When revealed, re-initialises any sliders that were hidden on load.
 */
function sln_bindExclusionTimeRestriction($) {
    $(document)
        .off('change.sln-exclusion-time', '[data-type="exclusion-time-restriction"]')
        .on('change.sln-exclusion-time', '[data-type="exclusion-time-restriction"]', function () {
            var $row     = $(this).closest('.sln-booking-rule');
            var $section = $row.find('.sln-exclusion-time-shifts');
            var isOn     = $(this).prop('checked');

            $section.toggleClass('hide', !isOn);

            if (isOn && typeof sln_customSliderRange === 'function') {
                sln_customSliderRange($, $section.find('.slider-range'));
            }
        });

    $('[data-type="exclusion-time-restriction"]').trigger('change');
}

/**
 * Second-shift toggle inside exclusion-rule rows.
 * Shows/hides the slider inner content and enables/disables the hidden inputs.
 * Scoped to #sln-discount-exclusion-rules to avoid conflicting with availability rows.
 */
function sln_bindExclusionSecondShift($) {
    $(document)
        .off('change.sln-exclusion-second-shift', '#sln-discount-exclusion-rules .sln-disable-second-shift input')
        .on('change.sln-exclusion-second-shift', '#sln-discount-exclusion-rules .sln-disable-second-shift input', function () {
            var $row      = $(this).closest('.sln-booking-rule');
            var disabled  = $(this).prop('checked');
            var $inner    = $row.find('.sln-second-shift .sln-slider__inner');

            $inner.toggle(!disabled);
            $row.find('.sln-second-shift .slider-time-input-from, .sln-second-shift .slider-time-input-to')
                .prop('disabled', disabled);
        });

    $('#sln-discount-exclusion-rules .sln-disable-second-shift input').trigger('change');
}