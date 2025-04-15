"use strict";

jQuery(function($) {});

function sln_applyDiscountCode() {
    var $ = jQuery;
    var code = $("#sln_discount").val();
    function sln_discountCodeInitButton(){
        $('[data-salon-toggle="next"]').on("click", function(e) {
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
                alertBox = $(
                    '<div class="sln-alert sln-alert--paddingleft sln-alert--success"></div>'
                );
                if(data.button != undefined){
                    $('.sln-btn.sln-btn--fullwidth.sln-btn--nextstep').html(data.button);
                    $('#sln-step-submit-complete').hide();
                    sln_discountCodeInitButton();
                }
            } else {
                $("#sln_discount_value").html(0);
                $('.sln-summary-row.sln-summary-row--discount').addClass('hide');
                $(".sln-total-price").html(data.total);
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
                alertBox.append("<p>").html(this);
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
