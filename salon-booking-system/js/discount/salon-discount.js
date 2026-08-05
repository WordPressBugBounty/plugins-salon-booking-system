"use strict";

jQuery(function($) {
    // Document-level delegation: #sln-salon-booking is replaced on every AJAX step swap,
    // so handlers bound directly to it would be lost. Enter/Return in the discount field
    // must trigger Apply — not the form's default submit (Next step / mode=confirm).
    $(document)
        .off("keydown.slnDiscount", "#sln_discount, input[name=\"sln[discount]\"]")
        .on("keydown.slnDiscount", "#sln_discount, input[name=\"sln[discount]\"]", function(e) {
            if (e.key === "Enter" || e.keyCode === 13) {
                e.preventDefault();
                e.stopPropagation();
                sln_applyDiscountCode();
            }
        });
});

function sln_applyDiscountCode() {
    var $ = jQuery;
    var code = $("#sln_discount").val();
    function sln_discountCodeInitButton(){
        $('[data-salon-toggle="next"]')
            .off("click.slnDiscountNext")
            .on("click.slnDiscountNext", function(e) {
            var form = $(this).closest("form");
            $(
                "#sln-salon input.sln-invalid,#sln-salon textarea.sln-invalid,#sln-salon select.sln-invalid"
            ).removeClass("sln-invalid");
            if (form[0].checkValidity()) {
                let form_data = null;
                if(form.attr('enctype') == 'multipart/form-data'){
                    form_data = new FormData(form[0]);
                    let sln_data = $(this).data('salon-data').split('&');
                    for(let i = 0; i < sln_data.length; i++){
                        form_data.append(sln_data[i].split('=')[0], sln_data[i].split('=')[1]);
                    }
                }else{
                    form_data = form.serialize() + "&" + $(this).data("salon-data")
                }
                
                sln_loadStep(
                    $,
                    form_data
                );
            } else {
                $(
                    "#sln-salon input:invalid,#sln-salon textarea:invalid,#sln-salon select:invalid"
                )
                    .addClass("sln-invalid")
                    .attr("placeholder", salon.checkout_field_placeholder);
                $(
                    "#sln-salon input:invalid,#sln-salon textarea:invalid,#sln-salon select:invalid"
                )
                    .parent()
                    .addClass("sln-invalid-p")
                    .attr("data-invtext", salon.checkout_field_placeholder);
            }
            chooseAsistentForMe = undefined;
            return false;
        });
    }

    var data =
        "sln[discount]=" +
        code +
        "&action=salon_discount&method=applyDiscountCode&security=" +
        salon.ajax_nonce;
    
    // Use the dynamic client state (updated after each step response) as the primary
    // source for client_id. This keeps the discount AJAX in sync with the booking
    // step navigation which also uses sln_getClientState().id.
    // Falls back to salon.client_id (static page-load value) for backward compatibility.
    var clientId = (typeof sln_getClientState === "function" && sln_getClientState().id)
        ? sln_getClientState().id
        : salon.client_id;

    if (clientId) {
        data += "&sln_client_id=" + encodeURIComponent(clientId);
    }

    // Send the booking ID so the server always applies the discount to the
    // correct booking, not a stale one from the session/transient.
    var bookingId = $('input[name="sln_booking_id"]').val();
    if (bookingId) {
        data += "&sln_booking_id=" + encodeURIComponent(bookingId);
    }

    $.ajax({
        url: salon.ajax_url,
        data: data,
        method: "POST",
        dataType: "json",
        success: function(data) {
            $("#sln_discount_status")
                .find(".sln-alert")
                .remove();
            var alertBox;
            if (data.success) {
                $("#sln_discount_value").html(data.discount);
                $('.sln-summary-row.sln-summary-row--discount').removeClass('hide');
                $(".sln-total-price").html(data.total);
                // Refresh the amount inside the PAY button (deposit or full total).
                // Only the .sln-pay-amount span is updated, so the anchor and its
                // bound click handlers (overbooking check / gateway redirect) survive.
                if (data.payButtonAmount != undefined) {
                    $('.sln-btn--nextstep .sln-pay-amount').html(data.payButtonAmount);
                }
                alertBox = $(
                    '<div class="sln-alert sln-alert--paddingleft sln-alert--success"></div>'
                );
                if(data.button != undefined){
                    $('.sln-btn.sln-btn--fullwidth.sln-btn--nextstep').html(data.button);
                    $('#sln-step-submit-complete').hide();
                    if (data.booking_id) {
                        var $bookingIdInput = $('input[name="sln_booking_id"]');
                        if ($bookingIdInput.length) {
                            $bookingIdInput.val(data.booking_id);
                        }
                    }
                    sln_discountCodeInitButton();
                }
            } else {
                $("#sln_discount_value").html(0);
                $('.sln-summary-row.sln-summary-row--discount').addClass('hide');
                $(".sln-total-price").html(data.total);
                // Discount was reverted: refresh the PAY button amount back to the
                // current (undiscounted) value so it stays in sync with the total.
                if (data.payButtonAmount != undefined) {
                    $('.sln-btn--nextstep .sln-pay-amount').html(data.payButtonAmount);
                }
                if(data.button != undefined){
                    $('.sln-btn.sln-btn--fullwidth.sln-btn--nextstep').html(data.button);
                    $('#sln-step-submit-complete').hide();
                    sln_discountCodeInitButton();
                }
                alertBox = $(
                    '<div class="sln-alert sln-alert--paddingleft sln-alert--problem"></div>'
                );
            }
            $(data.errors).each(function() {
                alertBox.append($("<p>").html(this));
            });
            $("#sln_discount_status")
                .html("")
                .append(alertBox);
        },
        error: function(data) {
            alert("error");
            console.log(data);
        },
    });

    return false;
}
