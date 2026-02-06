"use strict";

jQuery(function ($) {
    if ($('.sln-panel').length) {
        sln_initSlnPanel($);
    }

    sln_settingsLogo($);
    sln_settingsPayment($);
    sln_settingsCheckout($);
    sln_settingsGeneral($);
});

function sln_settingsLogo($) {
    var $dropzone = $('#logo_dropzone');
    var $input = $('#gen_logo_input');
    var $preview = $('#logo_preview');
    var $hiddenInput = $('#salon_settings_gen_logo');
    var $removeBtn = $('#remove_logo');
    var $progress = $dropzone.find('.sln-logo-dropzone__progress');
    var $progressBar = $dropzone.find('.sln-logo-dropzone__progress-bar');

    // Click to browse
    $dropzone.on('click', function(e) {
        if (!$(e.target).is('input')) {
            $input.trigger('click');
        }
    });

    // Drag and drop events
    $dropzone.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('sln-logo-dropzone--dragover');
    });

    $dropzone.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('sln-logo-dropzone--dragover');
    });

    $dropzone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('sln-logo-dropzone--dragover');
        
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            // Create a new DataTransfer object and assign the file to the input
            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(files[0]);
            $input[0].files = dataTransfer.files;
            
            handleFile(files[0]);
        }
    });

    // File input change
    $input.on('change', function() {
        console.log('File input changed:', this.files);
        if (this.files && this.files.length > 0) {
            handleFile(this.files[0]);
        }
    });

    // Remove button
    $removeBtn.on('click', function() {
        $hiddenInput.val('');
        $input.val('');
        $preview.addClass('hide');
        $dropzone.removeClass('hide');
    });

    function handleFile(file) {
        // Validate file type
        var validTypes = ['image/png', 'image/jpeg', 'image/jpg'];
        if (validTypes.indexOf(file.type) === -1) {
            alert('Please upload a PNG or JPG image.');
            return;
        }

        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB.');
            return;
        }

        // Show progress
        $progress.show();
        $progressBar.css('width', '0%');

        // Read file
        var reader = new FileReader();
        
        reader.onprogress = function(e) {
            if (e.lengthComputable) {
                var percentLoaded = Math.round((e.loaded / e.total) * 100);
                $progressBar.css('width', percentLoaded + '%');
            }
        };

        reader.onload = function(e) {
            // Update preview
            $preview.find('img').attr('src', e.target.result);
            $preview.removeClass('hide');
            $dropzone.addClass('hide');
            $progress.hide();
            $progressBar.css('width', '0%');
            
            console.log('File loaded successfully. Input files:', $input[0].files);
        };

        reader.onerror = function() {
            alert('Error reading file. Please try again.');
            $progress.hide();
            $progressBar.css('width', '0%');
        };

        reader.readAsDataURL(file);
    }
}

function sln_settingsPayment($) {

    $('input.sln-pay_method-radio').on('change', function () {
        $('.payment-mode-data').hide().removeClass('sln-box--fadein');
        $('#payment-mode-' + $(this).data('method')).show().addClass('sln-box--fadein');
    });

    $('#salon_settings_pay_enabled').on('change', function(){
        if($(this).is(':checked') && !$('#salon_settings_pay_offset_enabled').is(':checked')){
            $('#sln-create_booking_after_pay').removeClass('hide');
        }else{
            $('#sln-create_booking_after_pay').addClass('hide');
        }
        $('#salon_settings_create_booking_after_pay').removeAttr('checked');
    });

    $('#salon_settings_pay_offset_enabled').on('change', function(){
        if(!$(this).is(':checked') && $('#salon_settings_pay_enabled').is(':checked')){
            $('#sln-create_booking_after_pay').removeClass('hide');
        }else{
            $('#sln-create_booking_after_pay').addClass('hide');
        }
        $('#salon_settings_create_booking_after_pay').removeAttr('checked');
    });

    $("#salon_settings_pay_method")
        .on("change", function() {
            $(".payment-mode-data").hide();
            $("#payment-mode-" + $(this).val()).show();
        })
        .trigger("change");

    $("input.sln-pay_method-radio").each(function() {
        if ($(this).is(":checked")) {
            $("#payment-mode-" + $(this).data("method"))
                .show()
                .addClass("sln-box--fadein");
        }
    });

    $("#salon_settings_pay_deposit")
        .on("change", function() {
            var current = $(this).val();
            var expected = $("#salon_settings_pay_deposit_fixed_amount").data(
                "relate-to"
            );
            $("#salon_settings_pay_deposit_fixed_amount").attr(
                "disabled",
                current === expected ? false : "disabled"
            );
        })
        .trigger("change");

    $("#salon_settings_enable_pay_deposit_advanced_rules").on("change", function () {
        if ($(this).is(':checked')) {
            $('#sln-pay_deposit_advanced_rules').removeClass('hide');
        } else {
            $('#sln-pay_deposit_advanced_rules').addClass('hide');
        }
    });
}

function sln_settingsCheckout($) {
    $("#salon_settings_enabled_force_guest_checkout")
        .on("change", function() {
            if ($(this).is(":checked")) {
                $("#salon_settings_enabled_guest_checkout")
                    .attr("checked", "checked")
                    .trigger("change");
            }
        })
        .trigger("change");
    // $("#salon_settings_primary_services_count").on("change", function() {
    //     if (+$(this).val()) {
    //         $("#salon_settings_is_services_count_primary_services")
    //             .closest(".row")
    //             .removeClass("hide");
    //     } else {
    //         $("#salon_settings_is_services_count_primary_services")
    //             .closest(".row")
    //             .addClass("hide");
    //         $("#salon_settings_is_services_count_primary_services").prop(
    //             "checked",
    //             false
    //         );
    //     }
    // });
    $("#salon_settings_secondary_services_count").on("change", function() {
        if (+$(this).val()) {
            $("#salon_settings_is_secondary_services_selection_required")
                .closest(".row")
                .removeClass("hide");
        } else {
            $("#salon_settings_is_secondary_services_selection_required")
                .closest(".row")
                .addClass("hide");
            $("#salon_settings_is_secondary_services_selection_required").prop(
                "checked",
                false
            );
        }
    });
}

function sln_initCountryCodeSelector($, $providerSection) {
    var $input = $providerSection.find("#salon_settings_sms_prefix");
    
    if ($input.length === 0) {
        return; // Field not found in this section
    }
    
    var input = $input[0];
    
    // Destroy existing intlTelInput instance if any
    if (input.intlTelInput) {
        try {
            $(input).intlTelInput("destroy");
        } catch(e) {
            // Ignore if destroy fails
        }
    }
    
    function getCountryCodeByDialCode(dialCode) {
        if (!window.intlTelInputGlobals || !window.intlTelInputGlobals.getCountryData) {
            return '';
        }
        var countryData = window.intlTelInputGlobals.getCountryData();
        var countryCode = '';
        countryData.forEach(function(data) {
           if (data.dialCode == dialCode) {
               countryCode = data.iso2;
           }
        });
        return countryCode;
    }
    
    // Initialize intlTelInput on the visible field
    var iti = window.intlTelInput(input, {
        initialCountry: getCountryCodeByDialCode(($(input).val() || '').replace('+', '')),
    });
    
    // Store reference to the instance
    input.intlTelInput = iti;
    
    // Update the field value when country changes
    input.addEventListener("countrychange", function() {
        if (iti.getSelectedCountryData().dialCode) {
            $(input).val('+' + iti.getSelectedCountryData().dialCode);
        }
    });
}

function sln_settingsGeneral($) {
    if(!window.location.search.endsWith('salon-settings') && !window.location.search.endsWith('tab=general')){
        return;
    }
    $("#salon_settings_m_attendant_enabled")
        .on("change", function() {
            if ($(this).is(":checked")) {
                $("#salon_settings_attendant_enabled")
                    .attr("checked", "checked")
                    .trigger("change");
            }
        })
        .trigger("change");

    $("#salon_settings_follow_up_interval")
        .on("change", function() {
            $("#salon_settings_follow_up_interval_custom_hint").css(
                "display",
                $(this).val() === "custom" ? "" : "none"
            );
            $("#salon_settings_follow_up_interval_hint").css(
                "display",
                $(this).val() !== "custom" ? "" : "none"
            );
        })
        .trigger("change");

    $("#salon_settings_sms_provider")
        .on("change", function() {
            // First, re-enable all fields in all sections (in case they were disabled)
            $(".sms-provider-data").find("input, select, textarea").prop("disabled", false);
            
            // Hide all provider sections
            $(".sms-provider-data")
                .hide()
                .removeClass("sln-box--fadein");
            
            var selectedProvider = $(this).val();
            var $providerSection;
            
            if (
                $("#sms-provider-" + selectedProvider)
                    .html()
                    .trim() !== ""
            ) {
                $providerSection = $("#sms-provider-" + selectedProvider);
                $providerSection.show().addClass("sln-box--fadein");
            } else {
                $providerSection = $("#sms-provider-default");
                $providerSection.show().addClass("sln-box--fadein");
            }
            
            // Disable all fields in sections OTHER than the visible one to prevent duplicate submission
            $(".sms-provider-data").not($providerSection).find("input, select, textarea").prop("disabled", true);
            
            // Re-initialize intlTelInput for the visible sms_prefix field
            sln_initCountryCodeSelector($, $providerSection);
        })
        .trigger("change");

    $("#salon_settings_google_maps_api_key").on("change", function() {
        var successCallback = function() {
            var service = new google.maps.places.AutocompleteService();

            service.getQueryPredictions({ input: "pizza near Syd" }, function(
                predictions,
                status
            ) {
                if (status === google.maps.places.PlacesServiceStatus.OK) {
                    $('#salon_settings_google_maps_api_key_valid').val('1');
                } else if (status === google.maps.places.PlacesServiceStatus.REQUEST_DENIED) {
                    $('#salon_settings_google_maps_api_key_valid').val('0');
                } else {
                    $('#salon_settings_google_maps_api_key_valid').val('0');
                }
            });
        };

        var errorCallback = function() {
            $("#salon_settings_google_maps_api_key_valid").val("0");
        };

        document
            .querySelectorAll('script[src*="maps.google"]')
            .forEach((script) => {
                script.remove();
            });

        if (!$(this).val()) {
            return;
        }

        if (typeof google === "object") {
            google.maps = false;
        }

        window.gm_authFailure = errorCallback;

        var scriptTag = document.createElement("script");
        scriptTag.src =
            "https://maps.googleapis.com/maps/api/js?key=" +
            $(this).val() +
            "&libraries=places&language=en";

        scriptTag.onload = successCallback;
        scriptTag.onreadystatechange = successCallback;
        scriptTag.async = true;
        scriptTag.defer = true;

        document.body.appendChild(scriptTag);
    });
    
    // Note: intlTelInput initialization moved to sln_initCountryCodeSelector()
    // which is called dynamically when provider sections are shown/hidden
    
    // Test Email Functionality
    $('#sln-test-email').on('click', function(e) {
        e.preventDefault();
        
        console.log('Test Email button clicked');
        
        var $btn = $(this);
        var $result = $('#sln-bulk-feedback-result');
        var nonce = $btn.data('nonce');
        var originalText = $btn.text();
        
        console.log('Button:', $btn);
        console.log('Nonce:', nonce);
        console.log('ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'UNDEFINED');
        
        if (typeof ajaxurl === 'undefined') {
            alert('Error: ajaxurl is not defined. This is a WordPress configuration issue.');
            return;
        }
        
        if (!nonce) {
            alert('Error: Security nonce is missing. Please reload the page.');
            return;
        }
        
        // Show loading state
        $btn.prop('disabled', true).text('Testing...');
        $result.html('');
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sln_test_email',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    var msg = '<div class="notice notice-success inline" style="padding: 10px; margin: 0;"><p style="margin: 0;">';
                    msg += '<strong>' + response.data.message + '</strong><br>';
                    msg += 'Test email sent to: ' + response.data.admin_email + '<br>';
                    msg += '<small>Check plugin logs for detailed results.</small>';
                    msg += '</p></div>';
                    $result.html(msg);
                } else {
                    $result.html(
                        '<div class="notice notice-error inline" style="padding: 10px; margin: 0;"><p style="margin: 0;">' +
                        (response.data.message || 'Test failed') + '<br>' +
                        '<small>Check plugin logs for details.</small>' +
                        '</p></div>'
                    );
                }
            },
            error: function(xhr, status, error) {
                $result.html(
                    '<div class="notice notice-error inline" style="padding: 10px; margin: 0;"><p style="margin: 0;">' +
                    'Request failed: ' + error + '<br>' +
                    '<small>Check plugin logs and browser console.</small>' +
                    '</p></div>'
                );
                console.error('Test email error:', xhr, status, error);
            },
            complete: function() {
                // Restore button state
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Preview Bulk Feedback Count
    $('#sln-preview-bulk-feedback').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $preview = $('#sln-feedback-preview');
        var $count = $('#sln-feedback-count');
        var nonce = $btn.data('nonce');
        
        // Show loading state
        $btn.prop('disabled', true).text('Checking...');
        $preview.hide();
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sln_preview_bulk_feedback',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var html = '<strong>' + data.message + '</strong>';
                    
                    if (data.count > 0) {
                        html += '<br><span style="color: #2271b1; font-size: 14px;">' + data.breakdown + '</span>';
                    }
                    
                    if (data.details && data.details.length > 0 && data.count === 0) {
                        html += '<br><br><strong>Check settings:</strong><ul style="margin: 5px 0 0 0; padding-left: 20px;">';
                        data.details.forEach(function(detail) {
                            html += '<li>' + detail + '</li>';
                        });
                        html += '</ul>';
                    }
                    
                    $count.html(html);
                    $preview.css({
                        'background': data.count > 0 ? '#e7f5e7' : '#fff8e5',
                        'border-left-color': data.count > 0 ? '#46b450' : '#f0b849'
                    }).fadeIn();
                } else {
                    alert('Error: ' + (response.data || 'An error occurred'));
                }
            },
            error: function(xhr, status, error) {
                alert('Request failed: ' + error);
            },
            complete: function() {
                // Restore button state
                $btn.prop('disabled', false).text('Check eligible bookings');
            }
        });
    });
    
    // Bulk Feedback Sender
    $('#sln-send-bulk-feedback').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $text = $btn.find('.sln-btn__text');
        var $loader = $btn.find('.sln-btn__loader');
        var $result = $('#sln-bulk-feedback-result');
        var nonce = $btn.data('nonce');
        
        // Confirm action
        if (!confirm(salon.confirm_feedback_send || 'Send feedback requests to all eligible bookings?')) {
            return;
        }
        
        // Show loading state
        $btn.prop('disabled', true);
        $text.hide();
        $loader.show();
        $result.html('');
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sln_send_bulk_feedback',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    var msg = '<div class="notice notice-success inline" style="padding: 10px; margin: 0;"><p style="margin: 0;"><strong>' + 
                        (response.data.message || 'Feedback sent successfully') + 
                        '</strong></p>';
                    
                    // Show error details if any errors occurred but some succeeded
                    if (response.data.errors > 0 && response.data.error_details) {
                        msg += '<details style="margin-top: 10px;"><summary style="cursor: pointer;">Show errors (' + response.data.errors + ')</summary>';
                        msg += '<ul style="margin: 5px 0 0 20px;">';
                        response.data.error_details.forEach(function(error) {
                            msg += '<li style="color: #d63638;">' + error + '</li>';
                        });
                        msg += '</ul></details>';
                    }
                    
                    msg += '</div>';
                    $result.html(msg);
                } else {
                    // Server returned error
                    var errorMsg = 'An error occurred';
                    if (response.data) {
                        if (typeof response.data === 'string') {
                            errorMsg = response.data;
                        } else if (response.data.message) {
                            errorMsg = response.data.message;
                            if (response.data.error) {
                                errorMsg += '<br><small>Details: ' + response.data.error + '</small>';
                            }
                        }
                    }
                    $result.html(
                        '<div class="notice notice-error inline" style="padding: 10px; margin: 0;"><p style="margin: 0;">' + 
                        errorMsg + 
                        '</p></div>'
                    );
                    console.error('Feedback send failed:', response);
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Request failed. Please try again.';
                
                // Try to get more details
                if (xhr.responseText) {
                    try {
                        var responseData = JSON.parse(xhr.responseText);
                        if (responseData.data && responseData.data.message) {
                            errorMsg = responseData.data.message;
                        }
                    } catch (e) {
                        // Response is not JSON, show generic error
                        errorMsg += '<br><small>Server error. Check logs for details.</small>';
                    }
                } else if (error) {
                    errorMsg += ' (' + error + ')';
                }
                
                $result.html(
                    '<div class="notice notice-error inline" style="padding: 10px; margin: 0;"><p style="margin: 0;">' +
                    errorMsg +
                    '</p></div>'
                );
                console.error('AJAX error:', xhr, status, error);
            },
            complete: function() {
                // Restore button state
                $btn.prop('disabled', false);
                $text.show();
                $loader.hide();
            }
        });
    });
}


function sln_initSlnPanel($) {
    $('.sln-panel .collapse').on('shown.bs.collapse', function () {
        $(this).parent().find('.sln-paneltrigger').addClass('sln-btn--active');
        $(this).parent().addClass('sln-panel--active');
    }).on('hide.bs.collapse', function () {
        $(this).parent().find('.sln-paneltrigger').removeClass('sln-btn--active');
        $(this).parent().removeClass('sln-panel--active');
    });
    $('.sln-panel--oncheck .sln-panel-heading input:checkbox').on('change', function () {
        if ($(this).is(':checked')) {
            $(this).parent().parent().parent().find('.sln-paneltrigger').removeClass('sln-btn--disabled');
        } else {
            $(this).parent().parent().parent().find('.sln-paneltrigger').addClass('sln-btn--disabled');
            $(this).parent().parent().parent().find('.collapse').collapse('hide');
        }
    });
    $(".sln-panel--oncheck .sln-panel-heading input").each(function() {
        if ($(this).is(":checked")) {
            $(this)
                .parent()
                .parent()
                .parent()
                .find(".sln-paneltrigger")
                .removeClass("sln-btn--disabled");
        } else {
            $(this)
                .parent()
                .parent()
                .parent()
                .find(".sln-paneltrigger")
                .addClass("sln-btn--disabled");
        }
    });
}
