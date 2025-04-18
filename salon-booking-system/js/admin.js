"use strict";
if (jQuery("#toplevel_page_salon").hasClass("wp-menu-open")) {
	jQuery("#wpbody-content .wrap").addClass("sln-bootstrap");
	jQuery("#wpbody-content .wrap").attr("id", "sln-salon--admin");
}

(function (apiKey) {
	(function (p, e, n, d, o) {
		var v, w, x, y, z;
		o = p[d] = p[d] || {};
		o._q = o._q || [];
		v = ["initialize", "identify", "updateOptions", "pageLoad", "track"];
		for (w = 0, x = v.length; w < x; ++w)
			(function (m) {
				o[m] =
					o[m] ||
					function () {
						o._q[m === v[0] ? "unshift" : "push"](
							[m].concat([].slice.call(arguments, 0))
						);
					};
			})(v[w]);
		y = e.createElement(n);
		y.async = !0;
		y.src = "https://cdn.pendo.io/agent/static/" + apiKey + "/pendo.js";
		z = e.getElementsByTagName(n)[0];
		z.parentNode.insertBefore(y, z);
	})(window, document, "script", "pendo");

	if (typeof sln_pendo_user_id !== "undefined") {
		pendo.initialize({
			visitor: {
				id: sln_pendo_user_id,
				paidOrTrialUser: sln_pendo_paid_or_trail_user,
			},

			account: {
				id: sln_pendo_account_id,
			},
		});
	}
})("24521e10-d113-4085-71a3-b7808fddc272");

jQuery(function ($) {
	if (window.frameElement) {
		$("html").addClass("in-iframe");
	}
	$("#booking-accept, #booking-refuse").on("click", function () {
		$("#_sln_booking_status").val($(this).data("status"));
		$("#save-post").trigger("click");
	});

	$(".sln-toolbox-trigger").on("click", function (event) {
		$(this).parent().toggleClass("open");
		event.preventDefault();
	});
	$(".sln-toolbox-trigger-mob").on("click", function (event) {
		$(this).parent().find(".sln-toolbox").toggleClass("open");
		event.preventDefault();
	});
	$(".sln-box-info-trigger button").on("click", function (event) {
		$(this).parent().parent().parent().toggleClass("sln-box--info-visible");
		event.preventDefault();
	});
	$(".sln-box-info-content:after").on("click", function (event) {
		event.preventDefault();
	});
	if ($(".sln-admin-sidebar").length) {
		$(".sln-admin-sidebar").affix({
			offset: {
				top: $(".sln-admin-sidebar").offset().top - 96,
			},
		});
	}
	$(".sln-notice__dismiss").on("click", function () {
		$(this).closest(".sln-notice__wrapper").hide();
		document.cookie = "sln-notice__dismiss=1";
	});
	$("[data-action=change-service-type]").on("change", function () {
		var $this = $(this);
		var $target = $($this.attr("data-target"));
		var $exclusive = $("#exclusive_service");
		if ($this.is(":checked")) {
			$target.removeClass("hide");
			$exclusive.addClass("hide");
			$("#_sln_service_exclusive").val(0);
		} else {
			$target.addClass("hide");
			$exclusive.removeClass("hide");
		}
	});

	$("[data-action=change-secondary-service-mode]").on("change", function () {
		var $this = $(this);
		var $target = $($this.attr("data-target"));
		if ($this.val() === "service") {
			$target.removeClass("hide");
		} else {
			$target.addClass("hide");
		}
	});
	$(".sln-radiobox__wrapper--bd").each(function () {
		var inputTrigger = $(this).find('input[type="radio"]');
		if (inputTrigger.prop("checked")) {
			$(this).addClass("sln-radiobox__wrapper--checked");
		}
		inputTrigger.on("change", function () {
			$(".sln-radiobox__wrapper--bd").removeClass(
				"sln-radiobox__wrapper--checked"
			);
			$(this)
				.parent()
				.parent()
				.addClass("sln-radiobox__wrapper--checked");
		});
	});
	function premiumVersionBanner() {
		$(".sln-admin-banner--trigger, .sln-admin-banner--close").on(
			"click",
			function (event) {
				$(".sln-admin-banner").toggleClass("sln-admin-banner--inview");
				event.preventDefault();
			}
		);
	}
	if ($("#sln-salon--admin.sln-calendar--wrapper--loading").length) {
		$(".sln-calendar--wrapper--sub").css("opacity", "1");
		$(".sln-calendar--wrapper").removeClass(
			"sln-calendar--wrapper--loading sln-calendar--wrapper"
		);
		//$("#sln-pageloading").addClass(
		//    "sln-pageloading--inactive"
		//);
	}
	if ($(".sln-calendar--wrapper").length) {
		$(".sln-calendar--wrapper--sub").css("opacity", "1");
		$(".sln-calendar--wrapper").removeClass(
			"sln-calendar--wrapper--loading"
		);
		//$("#sln-pageloading").addClass(
		//    "sln-pageloading--inactive"
		//);
	}
	if ($(window).width() < 1024) {
		premiumVersionBanner();
	}

	if ($("#import-customers-drag").length > 0) {
		sln_initImporter($("#import-customers-drag"), "Customers");
	}
	if ($("#import-services-drag").length > 0) {
		sln_initImporter($("#import-services-drag"), "Services");
	}
	if ($("#import-assistants-drag").length > 0) {
		sln_initImporter($("#import-assistants-drag"), "Assistants");
	}
	if ($("#import-category-drag").length) {
		sln_initImporter($("#import-category-drag"), "Category");
	}
	if ($("#import-bookings-drag").length) {
		sln_initImporter($("#import-bookings-drag"), "Bookings");
	}

	$("#_sln_service_price")
		.on("sln_add_error_tip", function (e, element, error_type) {
			var offset = element.position();

			if (element.parent().find(".sln_error_tip").length === 0) {
				element.after(
					'<div class="sln_error_tip ' +
						error_type +
						'">' +
						salon_admin[error_type] +
						"</div>"
				);
				element
					.parent()
					.find(".sln_error_tip")
					.css(
						"left",
						offset.left +
							element.width() -
							element.width() / 2 -
							$(".sln_error_tip").width() / 2
					)
					.css("top", offset.top + element.height())
					.fadeIn("100");
			}
		})
		.on("sln_remove_error_tip", function (e, element, error_type) {
			element
				.parent()
				.find(".sln_error_tip." + error_type)
				.fadeOut("100", function () {
					$(this).remove();
				});
		})
		.on("blur", function () {
			$(".sln_error_tip").fadeOut("100", function () {
				$(this).remove();
			});
		})
		.on("change", function () {
			var regex = new RegExp(
				"[^-0-9%\\" + salon_admin.mon_decimal_point + "]+",
				"gi"
			);
			var value = $(this).val();
			var newvalue = value.replace(regex, "");

			if (value !== newvalue) {
				$(this).val(newvalue);
			}
		})
		.on("keyup", function () {
			var regex, error;
			regex = new RegExp(
				"[^-0-9%\\" + salon_admin.mon_decimal_point + "]+",
				"gi"
			);
			error = "i18n_mon_decimal_error";
			var value = $(this).val();
			var newvalue = value.replace(regex, "");

			if (value !== newvalue) {
				$("#_sln_service_price").triggerHandler("sln_add_error_tip", [
					$(this),
					error,
				]);
			} else {
				$("#_sln_service_price").triggerHandler(
					"sln_remove_error_tip",
					[$(this), error]
				);
			}
		});

	$("#salon_settings_sms_provider").on("change", function () {
		$("#salon_settings_whatsapp_enabled").prop("checked", false);
		$(".enabled-whatsapp-checkbox").toggleClass(
			"hide",
			$(this).val() !== "twilio"
		);
	});
	function settingsPanel() {
		$(".sln-box--haspanel").each(function () {
			var trigger = $(this).find(".sln-box__paneltitle"),
				target = $(this).find(".sln-box__panelcollapse");
			trigger.on("click", function () {
				$(".sln-box--haspanel .sln-box__paneltitle").removeClass(
					"sln-box__paneltitle--open"
				);
				$(".sln-box--haspanel .sln-box__panelcollapse.in").collapse(
					"hide"
				);
				target.collapse("toggle");
			});
			target.on("hidden.bs.collapse", function () {
				var parentID = $(this).parent().attr("id"),
					navbarLink = $("a[href$='#" + parentID + "']").parent();
				navbarLink.removeClass("active");
				trigger.removeClass("sln-box__paneltitle--open");
				$(this).parent().removeClass("sln-box--haspanel--open");
			});
			target.on("show.bs.collapse", function () {
				var parentID = $(this).parent().attr("id"),
					navbarLink = $("a[href$='#" + parentID + "']").parent(),
					x = $("a[href$='#" + parentID + "']").attr(
						"data-initialOffset"
					);
				$(".sln-inpage_navbar_inner").scrollLeft(x - 10);
				$("#sln-inpage_navbar li").removeClass("active");
				navbarLink.addClass("active");
				console.log(parentID + " " + navbarLink);
				trigger.addClass("sln-box__paneltitle--open");
				$(this).parent().addClass("sln-box--haspanel--open");
				sln_ProFeatureTooltip();
			});
		});
		$("#salon_settings_enable_booking_tax_calculation").on(
			"change",
			function () {
				$(this)
					.closest(".row")
					.next()
					.toggleClass("hide", $(this).val());
			}
		);
		setTimeout(function () {
			if (window.location.hash) {
				$(
					"#" +
						window.location.hash.replace("#", "") +
						" .sln-box__paneltitle"
				).trigger("click");
				$([document.documentElement, document.body]).animate(
					{
						scrollTop: $(
							"#" + window.location.hash.replace("#", "")
						).offset().top,
					},
					2000
				);
			}
		}, 0);
	}
	if ($(".sln-box--haspanel").length) {
		settingsPanel();
	}
	$("#salon_settings_attendant_enabled").on("change", function () {
		// !$(this).prop("checked") &&
		//     $("#salon_settings_only_from_backend_attendant_enabled").prop(
		//         "checked",
		//         false
		//     );
		$(
			".only-from-backend-attendant-enable-checkbox, .assistant-selections-options"
		).toggleClass("hide", !$(this).prop("checked"));
		$(this)
			.closest(".row")
			.toggleClass("sln-box--appeared", $(this).prop("checked"));
	});

	$(".sln-booking-holiday-rules").on(
		"change",
		".sln-from-date .sln-input",
		function () {
			$(this)
				.closest(".row")
				.find(".sln-to-date .sln-input")
				.val($(this).val());
		}
	);

	$("#_sln_attendant_email").select2({
		containerCssClass: "sln-select-rendered",
		dropdownCssClass: "sln-select-dropdown",
		theme: "sln",
		width: "100%",
		placeholder: $("#_sln_attendant_email").data("placeholder"),
		tags: true,
		allowClear: true,
		language: {
			noResults: function () {
				return $("#_sln_attendant_email").data("nomatches");
			},
		},

		ajax: {
			url:
				salon.ajax_url +
				"&action=salon&method=SearchAssistantStaffMember&security=" +
				salon.ajax_nonce,
			dataType: "json",
			delay: 250,
			data: function (params) {
				return {
					s: params.term,
				};
			},
			minimumInputLength: 3,
			processResults: function (data, page) {
				let selected = $("#_sln_attendant_email :first-child");
				if (selected.data("staff-member-id") == "") {
					data.result.forEach(function (el) {
						if (selected.val() == el.id) {
							selected.data(
								"staff-member-id",
								el.staff_member_id
							);
							return false;
						}
					});
				}
				return {
					results: data.result,
				};
			},
		},
	});

	$("#_sln_attendant_email").on("change", function () {
		var selected_option = $("#_sln_attendant_email").select2("data")[0];
		var user_id =
			typeof selected_option !== "undefined"
				? selected_option["staff_member_id"] ||
				  $(selected_option.element).data("staff-member-id")
				: "";
		$('[name="_sln_attendant_staff_member_id"]').val(user_id);
		$(
			'[name="_sln_attendant_limit_staff_member_to_assigned_bookings_only"]'
		).prop("checked", false);
		$(
			'[name="_sln_attendant_limit_staff_member_to_backend_calendar_only"]'
		).prop("checked", false);
		if (+user_id) {
			$(".sln-staff-member-assigned-bookings-only").removeClass("hide");
			$(".sln-staff-member-backend-calendar-only").removeClass("hide");
		} else {
			$(".sln-staff-member-assigned-bookings-only").addClass("hide");
			$(".sln-staff-member-backend-calendar-only").addClass("hide");
		}
	});

	$('input[type="tel"]').on("keydown", function (event) {
		if (
			/[^0-9]/.test(event.key) &&
			!/(Backspace)|(Enter)|(Tab)|(ArrowLeft)|(ArrowRight)|(Delete)/.test(
				event.key
			)
		) {
			event.preventDefault();
		}
	});

	var input = document.querySelector("#sln_customer_meta__sln_phone");

	if (input && $("#sln_customer_meta__sln_sms_prefix").length) {
		function getCountryCodeByDialCode(dialCode) {
			var countryData = window.intlTelInputGlobals.getCountryData();
			var countryCode = "";
			countryData.forEach(function (data) {
				if (data.dialCode == dialCode) {
					countryCode = data.iso2;
				}
			});
			return countryCode;
		}

		var iti = window.intlTelInput(input, {
			initialCountry: getCountryCodeByDialCode(
				($("#sln_customer_meta__sln_sms_prefix").val() || "").replace(
					"+",
					""
				)
			),
			separateDialCode: true,
			autoHideDialCode: true,
			nationalMode: false,
		});

		input.addEventListener("countrychange", function () {
			if (iti.getSelectedCountryData().dialCode) {
				$("#sln_customer_meta__sln_sms_prefix").val(
					"+" + iti.getSelectedCountryData().dialCode
				);
			}
		});

		input.addEventListener("blur", function () {
			if (iti.getSelectedCountryData().dialCode) {
				$("#sln_customer_meta__sln_phone").val(
					$("#sln_customer_meta__sln_phone")
						.val()
						.replace(
							"+" + iti.getSelectedCountryData().dialCode,
							""
						)
				);
			}
		});
	}

	var input = document.querySelector("#_sln_attendant_phone");

	if (input && $("#_sln_attendant_sms_prefix").length) {
		function getCountryCodeByDialCode(dialCode) {
			var countryData = window.intlTelInputGlobals.getCountryData();
			var countryCode = "";
			countryData.forEach(function (data) {
				if (data.dialCode == dialCode) {
					countryCode = data.iso2;
				}
			});
			return countryCode;
		}

		var iti = window.intlTelInput(input, {
			initialCountry: getCountryCodeByDialCode(
				($("#_sln_attendant_sms_prefix").val() || "").replace("+", "")
			),
			separateDialCode: true,
			autoHideDialCode: true,
			nationalMode: false,
		});

		input.addEventListener("countrychange", function () {
			if (iti.getSelectedCountryData().dialCode) {
				$("#_sln_attendant_sms_prefix").val(
					"+" + iti.getSelectedCountryData().dialCode
				);
			}
		});

		input.addEventListener("blur", function () {
			if (iti.getSelectedCountryData().dialCode) {
				$("#_sln_attendant_phone").val(
					$("#_sln_attendant_phone")
						.val()
						.replace(
							"+" + iti.getSelectedCountryData().dialCode,
							""
						)
				);
			}
		});
	}

	$("#sln-booking-editor-modal").on("shown.bs.modal", function (e) {
		$(this)
			.find("iframe")
			.on("load", function () {
				$(this).contents().find("body").addClass("inmodal");
			});
	});

	$(
		".sln-booking-confirmation .sln-booking-confirmation-success, .sln-booking-confirmation .sln-booking-confirmation-error"
	).on("click", function () {
		var self = $(this);

		if (self.closest(".sln-booking-confirmation-disabled").length) {
			return false;
		}

		self.closest(".sln-booking-confirmation")
			.find(".sln-booking-confirmation-alert-loading")
			.html(self.attr("title"))
			.addClass(self.data("class"));
		self.closest(".sln-booking-confirmation").addClass("loading");

		jQuery.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "salon",
				method: "setBookingStatus",
				status: self.data("status"),
				booking_id: self.data("booking-id"),
			},
			cache: false,
			dataType: "json",
			success: function (response) {
				self.closest("tr")
					.find(".booking_status")
					.html(response.status);
				self.closest("td").html("");
			},
		});

		return false;
	});

	$("#_sln_booking_status")
		.on("change", function () {
			var default_status = $(this)
				.closest(".row")
				.find(".sln-set-default-booking-status--block-labels")
				.data("defaultStatus");

			if ($(this).val() !== default_status) {
				$(".sln-set-default-booking-status--label-set").removeClass(
					"hide"
				);
				$(this)
					.closest(".row")
					.find(".select2-selection__rendered")
					.removeClass("sln-booking-default-status");
			} else {
				$(".sln-set-default-booking-status--label-set").addClass(
					"hide"
				);
				$(this)
					.closest(".row")
					.find(".select2-selection__rendered")
					.addClass("sln-booking-default-status");
			}
		})
		.trigger("change");

	$(".sln-set-default-booking-status--label-set").on("click", function () {
		if (
			$(this).closest(
				".sln-set-default-booking-status--block-label-disabled"
			).length
		) {
			return false;
		}

		var status = $("#_sln_booking_status").val();
		var self = $(this);

		self.addClass("hide");
		self.closest(".sln-set-default-booking-status--block-labels")
			.find(".sln-set-default-booking-status--alert-loading")
			.removeClass("hide");

		jQuery.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "salon",
				method: "setDefaultBookingStatus",
				status: status,
			},
			cache: false,
			dataType: "json",
			success: function (response) {
				self.closest(
					".sln-set-default-booking-status--block-labels"
				).data("defaultStatus", status);
				var done_label = self
					.closest(".sln-set-default-booking-status--block-labels")
					.find(".sln-set-default-booking-status--label-done");
				$("#sln-booking__status__label").attr(
					"data-default_status",
					status
				);
				done_label.removeClass("hide");
				setTimeout(function () {
					done_label.addClass("hide");
				}, 3000);
				$("#_sln_booking_status").trigger("change");
				self.closest(
					".sln-set-default-booking-status--block-labels"
				).addClass("selected_is_default");
				self.closest(".sln-set-default-booking-status--block-labels")
					.find(".sln-set-default-booking-status--alert-loading")
					.addClass("hide");
			},
		});
		return false;
	});

	$(".generate-onesignal-app").on("click", function (e) {
		$(this).addClass("loading");
		var self = this;
		jQuery.ajax({
			url: ajaxurl,
			type: "POST",
			data: {
				action: "salon",
				method: "GenerateOnesignalApp",
			},
			cache: false,
			dataType: "json",
			success: function (response) {
				$(self).removeClass("loading");
				$("#salon_settings_onesignal_app_id").val(response.app_id);
			},
		});
		return false;
	});

	function sln_select2MultiSearch() {
		$(".sln-select--multiple--search select").each(function () {
			jQuery(this).on("select2:closing", function (e) {
				if (
					jQuery(this)
						.parent()
						.find(
							".select2-selection__rendered .select2-selection__choice"
						).length
				) {
					jQuery(this)
						.closest(".sln-select--inwrapper")
						.removeClass("has_no_choices");
				} else {
					jQuery(this)
						.closest(".sln-select--inwrapper")
						.addClass("has_no_choices");
				}
			});
		});
	}
	if ($(".sln-select--multiple--search").length) {
		sln_select2MultiSearch();
	}
	if (window.location !== window.parent.location) {
		$("#detailsWrapper").addClass("isInIframe");
	}
	function sln_ProFeatureTooltip() {
		const elements = document.getElementsByClassName("sln-profeature__cta");
		const elementsArr = Array.from(elements);
		elementsArr.forEach((el) => {
			const dialog = el.getElementsByClassName("sln-profeature__dialog");
			const openButton = el.getElementsByClassName(
				"sln-profeature__open-button"
			);
			const closeButton = el.getElementsByClassName(
				"sln-profeature__close-button"
			);
			//dialog[0].showModal();
			openButton[0].addEventListener("click", (event) => {
				event.preventDefault();
				dialog[0].showModal();
				dialog[0].classList.add("open");
			});
			closeButton[0].addEventListener("click", (event) => {
				event.preventDefault();
				dialog[0].close();
				dialog[0].classList.remove("open");
			});
			dialog[0].addEventListener("click", (event) => {
				if (event.target.nodeName === "DIALOG") {
					dialog[0].close();
					dialog[0].classList.remove("open");
				}
			});
		});
	}
	if (document.querySelector(".sln-profeature__cta")) {
		sln_ProFeatureTooltip();
	}
});

//function pageInIframe() {
//  return (window.location !== window.parent.location);
//}

var sln_importRows;
function sln_initImporter($item, mode) {
    var $importArea = $item;

    $importArea[0].ondragover = function () {
        $importArea.addClass("hover");
        return false;
    };

    $importArea[0].ondragleave = function () {
        $importArea.removeClass("hover");
        return false;
    };

    $importArea[0].ondrop = function (event) {
        event.preventDefault();
        $importArea.removeClass("hover").addClass("drop");

        var file = event.dataTransfer.files[0];

        $importArea.file = file;

        $importArea.find(".text").html(file.name);
        $importArea.removeClass("is_loading");
        importShowFileInfo();
    };

    jQuery(
        "[data-action=sln_import][data-target=" + $importArea.attr("id") + "]"
    ).on("click", function () {
        var $importBtn = jQuery(this);
        $importBtn.button("loading");
        jQuery(this).parent().parent().addClass("is_loading");
        if (!$importArea.file) {
            $importBtn.button("reset");
            jQuery(this).parent().parent().removeClass("is_loading");
            return false;
        }
        $importArea
            .find(".progress-bar")
            .attr("aria-valuenow", 0)
            .css("width", "0%");
        importShowInfo();

        var data = new FormData();

        data.append("action", "salon");
        data.append("method", "import" + mode);
        data.append("step", "start");
        data.append('_wpnonce', jQuery('input#_wpnonce').val());
        data.append("file", $importArea.file);

        jQuery.ajax({
            url: ajaxurl,
            type: "POST",
            data: data,
            cache: false,
            dataType: "json",
            processData: false, //(Don't process the files)
            contentType: false,
            success: function (response) {
                $importBtn.button("reset");
                if (response.success) {
                    console.log(response);
                    sln_importRows = response.data.rows;

                    var $modal = jQuery("#import-matching-modal");

                    var $modalBtn = $modal.find(
                        "[data-action=sln_import_matching]"
                    );
                    $modalBtn.button("reset");

                    $modal.find("table tbody").html(response.data.matching);
                    jQuery("#wpwrap").css("z-index", "auto");
                    $modal.modal({
                        keyboard: false,
                        backdrop: true,
                    });
                    sln_createSelect2Full(jQuery);
                    sln_validImportMatching();
                    $modal
                        .find("[data-action=sln_import_matching_select]")
                        .on("change", sln_changeImportMatching);

                    jQuery("[data-action=sln_import_matching]")
                        .off("click")
                        .on("click", function () {
                            if (!sln_validImportMatching()) {
                                return false;
                            }
                            $modalBtn.button("loading");

                            jQuery.ajax({
                                url: ajaxurl,
                                type: "POST",
                                data: {
                                    action: "salon",
                                    method: "import" + mode,
                                    step: "matching",
                                    _wpnonce: jQuery('input#_wpnonce').val(),
                                    form: $modal.closest("form").serialize(),
                                },
                                cache: false,
                                dataType: "json",
                                success: function (response) {
                                    console.log(response);
                                    $modal.modal("hide");
                                    if (response.success) {
                                        jQuery('#import-skipped-booking-modal .alert-skipped .skipped-bookings').html('')
                                        importShowPB();
                                        importProgressPB(
                                            response.data.total,
                                            response.data.left,
                                        );
                                    } else {
                                        importShowError();
                                    }
                                },
                                error: function () {
                                    $modal.modal("hide");
                                    importShowError();
                                },
                            });
                        });
                } else {
                    importShowError();
                }
            },
            error: function () {
                $importBtn.button("reset");
                importShowError();
            },
        });

        $importArea.file = false;

        return false;
    });

    function importProgressPB(total, left, skipped=true) {
        total = parseInt(total);
        left = parseInt(left);

        var value = ((total - left) / total) * 100;
        $importArea
            .find(".progress-bar")
            .attr("aria-valuenow", value)
            .css("width", value + "%");
        if(skipped !== true){
            jQuery('.alert.alert-success .alert-skipped').removeClass('hide');
            let skipped_list = jQuery('#import-skipped-booking-modal .alert-skipped .skipped-bookings');
            skipped_list.append(
                '<li><span class="skipped-booking--id">' + skipped.id + '</span>' +
                    '<span class="skipped-booking--datetime">' + skipped.datetime + '</span>' +
                    '<span class="skipped-booking--first-name">' + skipped.first_name + '</span>' +
                    '<span class="skipped-booking--last-name">' + skipped.last_name + '</span>' +
                    '<span class="skipped-booking--email">' + skipped.email + '</span>' +
                    '<span class="skipped-booking--error">' + skipped.error + '</span>' +
                '</li>'
            );
        }

        if (left != 0) {
            jQuery.ajax({
                url: ajaxurl,
                type: "GET",
                data: {
                    action: "salon",
                    method: "import" + mode,
                    step: "process",
                    _wpnonce: jQuery('input#_wpnonce').val(),
                },
                cache: false,
                dataType: "json",
                success: function (response) {
                    if (response.success) {
                        console.log(response);
                        importProgressPB(
                            response.data.total,
                            response.data.left,
                            response.data.skipped
                        );
                    } else {
                        importShowError();
                    }
                },
                error: function () {
                    importShowError();
                },
            });
        } else {
            jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "salon",
                    method: "import" + mode,
                    step: "finish",
                    _wpnonce: jQuery('input#_wpnonce').val(),
                },
                cache: false,
                dataType: "json",
                success: function (response) {
                    if (response.success) {
                        importShowSuccess();
                        if(jQuery('#import-skipped-booking-modal .alert-skipped .skipped-bookings li').length){
                        	jQuery("#wpwrap").css("z-index", "auto");
                        	let $modal = jQuery('#import-skipped-booking-modal').modal({
                        		keyboard: false,
                        		backdrop: true,
                        	});
                        	$modal.find('.skipped-bookings--number').text($modal.find('.skipped-bookings li').length);
                        	$modal.find('.skipped-bookings--total').text(total);
                        }
                    } else {
                        importShowError();
                    }
                },
                error: function () {
                    importShowError();
                },
            });
        }
    }

    function importShowPB() {
        $importArea.find(".info, .alert").addClass("hide");
        $importArea.find(".progress").removeClass("hide");
        $importArea.removeClass("drop");
    }

    function importShowFileInfo() {
        $importArea.find(".alert, .progress").addClass("hide");
        $importArea.find(".info").removeClass("hide");
    }

    function importShowInfo() {
        $importArea
            .find(".text")
            .html($importArea.find(".text").attr("placeholder"));

        $importArea.removeClass("is_loading");
        $importArea.find(".alert, .progress").addClass("hide");
        $importArea.find(".info").removeClass("hide");
    }

    function importShowSuccess() {
        $importArea.find(".info, .alert, .progress").addClass("hide");
        $importArea.find(".alert-success").removeClass("hide");
        $importArea.removeClass("drop");
    }

    function importShowError() {
        $importArea.find(".alert, .progress").addClass("hide");
        $importArea.find(".alert-danger").removeClass("hide");
        $importArea.removeClass("drop");
    }
}

function sln_changeImportMatching() {
	var $select = jQuery(this);
	var field = $select.val();
	var col = $select.attr("data-col");

	$select
		.closest("table")
		.find("tr.import_matching")
		.each(function (index, v) {
			var $cell = jQuery(this).find("td[data-col=" + col + "] span");

			var text;
			if (
				sln_importRows[index] !== undefined &&
				sln_importRows[index][field] !== undefined
			) {
				$cell
					.addClass("pull-left")
					.removeClass("half-opacity")
					.html(sln_importRows[index][field]);
			} else {
				$cell
					.removeClass("pull-left")
					.addClass("half-opacity")
					.html($cell.closest("td").attr("placeholder"));
			}
		});

	sln_validImportMatching();
}

function show_btn_save() {
	const button = document.querySelector(
		'button[data-action="save-edited-booking"]'
	);
	if (button) {
		button.classList.remove("sln-btn-disabled");
	}
}

function close_btn_save() {
	const button = document.querySelector(
		'button[data-action="save-edited-booking"]'
	);
	if (button) {
		button.classList.add("sln-btn-disabled");
	}
}

function sln_validImportMatching() {
	var $modal = jQuery("#import-matching-modal");

	var valid = true;
	$modal.find("select").each(function () {
		if (jQuery(this).prop("required") && jQuery(this).val() == "") {
			valid = false;
		}
	});

	if (valid) {
		$modal.find(".alert").addClass("hide");
		$modal
			.find("[data-action=sln_import_matching]")
			.prop("disabled", false);
	} else {
		$modal.find(".alert").removeClass("hide");
		$modal
			.find("[data-action=sln_import_matching]")
			.prop("disabled", "disabled");
	}

	return valid;
}
