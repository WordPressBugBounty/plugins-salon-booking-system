"use strict";

jQuery(function () {
	jQuery(".sln-booking-rules").each(function () {
		sln_initBookingRules(jQuery(this));
	});
	jQuery(".sln-booking-holiday-rules").each(function () {
		sln_initBookingHolidayRules(jQuery(this));
	});

	jQuery("body").on("change", "[data-unhide]", function () {
		jQuery(jQuery(this).data("unhide")).toggle(
			jQuery(this).is(":checked") ? false : true
		);
		if (jQuery(this).is(":checked")) {
			console.log("checked");
			jQuery(jQuery(this).data("unhide")).removeClass(
				"sln-box--appeared"
			);
		} else {
			console.log("not checked");
			jQuery(jQuery(this).data("unhide")).addClass("sln-box--appeared");
		}
	});
	jQuery("[data-unhide]").trigger("change");

	jQuery("body").on(
		"change",
		".sln-disable-second-shift input",
		sln_toggleSecondShift
	);
	jQuery(".sln-disable-second-shift input").trigger("change");

	jQuery("body").on(
		"change",
		".sln-select-specific-dates input",
		function () {
			if (jQuery(this).prop("checked")) {
				jQuery(this)
					.closest(".sln-booking-rule")
					.find(".sln-checkbutton-group")
					.addClass("hide");
				jQuery(this)
					.closest(".sln-booking-rule")
					.find(".sln-select-specific-dates-calendar")
					.removeClass("hide");
			} else {
				jQuery(this)
					.closest(".sln-booking-rule")
					.find(".sln-checkbutton-group")
					.removeClass("hide");
				jQuery(this)
					.closest(".sln-booking-rule")
					.find(".sln-select-specific-dates-calendar")
					.addClass("hide");
			}
		}
	);

	jQuery("body").on("sln_date", function () {
		setTimeout(function () {
			jQuery(".datetimepicker-days table tr td.day").on(
				"click",
				function () {
					if (jQuery(this).hasClass("disabled")) {
						return;
					}

					if (
						!jQuery(this).closest(
							".sln-select-specific-dates-calendar"
						).length
					) {
						return;
					}

					var date = jQuery(this).attr("data-ymd");

					var values = jQuery(this)
						.closest(".sln-select-specific-dates-calendar")
						.find('input[name*="specific_dates"]')
						.val()
						.split(",");

					values = values.filter(function (item) {
						return item;
					});

					if (values.indexOf(date) > -1) {
						values = values.filter(function (item) {
							return item !== date;
						});
					} else {
						values.push(date);
					}

					jQuery(this)
						.closest(".sln-select-specific-dates-calendar")
						.find('input[name*="specific_dates"]')
						.val(values.join(","));

					var datepicker = jQuery(this)
						.closest(".sln_datepicker")
						.find('div[name*="specific_dates"]');
					setTimeout(function () {
						datepicker.find(".day.active").removeClass("active");
						values.forEach(function (item) {
							datepicker
								.find('.day[data-ymd="' + item + '"]')
								.addClass("active");
						});
					});
				}
			);
		});
	});

	jQuery(".sln_datepicker div").on("changeDay", function () {
		jQuery("body").trigger("sln_date");
	});

	setTimeout(() => {
		jQuery(".datetimepicker-days table tr th.next").on(
			"click",
			function () {
				jQuery("body").trigger("sln_date");

				var values = jQuery(this)
					.closest(".sln-select-specific-dates-calendar")
					.find('input[name*="specific_dates"]')
					.val()
					.split(",");

				values = values.filter(function (item) {
					return item;
				});

				var datepicker = jQuery(this)
					.closest(".sln_datepicker")
					.find('div[name*="specific_dates"]');
				setTimeout(function () {
					datepicker.find(".day.active").removeClass("active");
					values.forEach(function (item) {
						datepicker
							.find('.day[data-ymd="' + item + '"]')
							.addClass("active");
					});
				});
			}
		);
		jQuery(".datetimepicker-days table tr th.prev").on(
			"click",
			function () {
				jQuery("body").trigger("sln_date");

				var values = jQuery(this)
					.closest(".sln-select-specific-dates-calendar")
					.find('input[name*="specific_dates"]')
					.val()
					.split(",");

				values = values.filter(function (item) {
					return item;
				});

				var datepicker = jQuery(this)
					.closest(".sln_datepicker")
					.find('div[name*="specific_dates"]');
				setTimeout(function () {
					datepicker.find(".day.active").removeClass("active");
					values.forEach(function (item) {
						datepicker
							.find('.day[data-ymd="' + item + '"]')
							.addClass("active");
					});
				});
			}
		);
	}, 0);

	sln_initSelectSpecificDates();
});

function sln_bindRemoveFunction() {
	jQuery(this).parent().parent().parent().remove();
	return false;
}

function sln_bindRemove() {
	jQuery('button[data-collection="remove"]')
		.off("click", sln_bindRemoveFunction)
		.on("click", sln_bindRemoveFunction);
}

function sln_bindDisableSecondShift() {
	jQuery(".sln-disable-second-shift input")
		.off("change", sln_bindDisableSecondShiftFunction)
		.on("change", sln_bindDisableSecondShiftFunction);
}

function sln_bindDisableSecondShiftFunction() {
	jQuery(this)
		.closest(".sln-booking-rule")
		.find(
			".sln-second-shift .slider-time-input-from, .sln-second-shift .slider-time-input-to"
		)
		.prop("disabled", jQuery(this).prop("checked"));
}

function sln_initBookingRules(elem) {
	var prototype = elem.find('div[data-collection="prototype"]');
	var wrapper = elem.find(".sln-booking-rules-wrapper");
	var html = prototype.html();
	var count = prototype.data("count");
	prototype.remove();

	jQuery('button[data-collection="addnew"]', elem).on("click", function (e) {
		count++;
		e.preventDefault();
		wrapper.append(html.replace(/__new__/g, count));
		sln_bindRemove();
		sln_bindDisableSecondShift();

		jQuery(".sln_datepicker div").on("changeDay", function () {
			jQuery("body").trigger("sln_date");
		});

		setTimeout(() => {
			jQuery(".datetimepicker-days table tr th.next").on(
				"click",
				function () {
					jQuery("body").trigger("sln_date");

					var values = jQuery(this)
						.closest(".sln-select-specific-dates-calendar")
						.find('input[name*="specific_dates"]')
						.val()
						.split(",");

					values = values.filter(function (item) {
						return item;
					});

					var datepicker = jQuery(this)
						.closest(".sln_datepicker")
						.find('div[name*="specific_dates"]');
					setTimeout(function () {
						datepicker.find(".day.active").removeClass("active");
						values.forEach(function (item) {
							datepicker
								.find('.day[data-ymd="' + item + '"]')
								.addClass("active");
						});
					});
				}
			);
			jQuery(".datetimepicker-days table tr th.prev").on(
				"click",
				function () {
					jQuery("body").trigger("sln_date");

					var values = jQuery(this)
						.closest(".sln-select-specific-dates-calendar")
						.find('input[name*="specific_dates"]')
						.val()
						.split(",");

					values = values.filter(function (item) {
						return item;
					});

					var datepicker = jQuery(this)
						.closest(".sln_datepicker")
						.find('div[name*="specific_dates"]');
					setTimeout(function () {
						datepicker.find(".day.active").removeClass("active");
						values.forEach(function (item) {
							datepicker
								.find('.day[data-ymd="' + item + '"]')
								.addClass("active");
						});
					});
				}
			);
		}, 0);

		sln_initSelectSpecificDates();
		sln_initSelectSpecificService();

		sln_initDatepickers(jQuery);
		sln_initTimepickers(jQuery);
		sln_customSliderRange(jQuery, jQuery(".slider-range"));
		jQuery("[data-unhide]", elem).trigger("change");
		jQuery(
			".sln-booking-rule:last-child .sln-checkbutton input",
			elem
		).prop("checked", true);
	});
	sln_bindRemove();
	sln_bindDisableSecondShift();
	sln_initSelectSpecificService();
}

function sln_initBookingHolidayRules(elem) {
	var prototype = elem.find('div[data-collection="prototype"]');
	var html = prototype.html();
	var count = prototype.data("count");
	var wrapper = elem.find(".sln-booking-holiday-rules-wrapper");
	prototype.remove();

	jQuery('button[data-collection="addnewholiday"]', elem).on(
		"click",
		function (e) {
			e.preventDefault();
			wrapper.append(html.replace(/__new__/g, count));
			count++;
			sln_initDatepickers(jQuery);
			sln_initTimepickers(jQuery);
			sln_bindRemove();
		}
	);
	sln_bindRemove();
}

function sln_toggleSecondShift(e) {
	var disable = jQuery(this).prop("checked");
	if (disable) {
		jQuery(this)
			.parent()
			.parent()
			.parent()
			.find(
				'input[name="salon_settings[availabilities][1][from][1]"],input[name="salon_settings[availabilities][1][to][1]"]'
			)
			.attr("disabled", "disabled");
		jQuery(this)
			.parent()
			.parent()
			.parent()
			.find(".sln-slider-wrapper-second-shift")
			.removeClass("sln-slider--disabled")
			.removeAttr("hidden")
			.find(".sln-slider__inner")
			.hide();
	} else {
		jQuery(this)
			.parent()
			.parent()
			.parent()
			.find(".sln-slider-wrapper-second-shift")
			.find(
				'input[name="salon_settings[availabilities][1][from][1]"],input[name="salon_settings[availabilities][1][to][1]"]'
			)
			.removeAttr("disabled");
		jQuery(this)
			.parent()
			.parent()
			.parent()
			.find(".sln-slider-wrapper-second-shift")
			.addClass("sln-slider--disabled")
			.find(".sln-slider__inner")
			.show();
	}
}
function sln_initSelectSpecificDates(e) {
	jQuery(".sln-select-specific-dates-calendar .sln_datepicker > div").each(
		function () {
			var $this = jQuery(this);
			if (jQuery($this).hasClass("started")) {
				return;
			} else {
				var disabledWeekDays = [];

				var availabilityDates =
					jQuery($this).data("availability-dates");

				availabilityDates.forEach(function (item, index) {
					if (Array.isArray(item) && !item.length) {
						disabledWeekDays.push(index);
					}
				});

				var picker = jQuery($this)
					.addClass("started")
					.datetimepicker({
						format: jQuery($this).data("format"),
						weekStart: jQuery($this).data("weekstart"),
						autoclose: true,
						minView: 2,
						maxView: 4,
						language: jQuery($this).data("locale"),
						daysOfWeekDisabled: disabledWeekDays,
					})
					.on("show", function () {
						jQuery("body").trigger("sln_date");
					})
					.on("place", function () {
						jQuery("body").trigger("sln_date");
					})
					.on("changeMonth", function () {
						jQuery("body").trigger("sln_date");
					})
					.on("changeYear", function () {
						jQuery("body").trigger("sln_date");
					})
					.data("datetimepicker").picker;

				jQuery("body").trigger("sln_date");

				var values = jQuery(this)
					.closest(".sln-select-specific-dates-calendar")
					.find('input[name*="specific_dates"]')
					.val()
					.split(",");

				values = values.filter(function (item) {
					return item;
				});

				var datepicker = jQuery(this);

				setTimeout(function () {
					datepicker.find(".day.active").removeClass("active");
					values.forEach(function (item) {
						datepicker
							.find('.day[data-ymd="' + item + '"]')
							.addClass("active");
					});
				});

				picker.addClass(jQuery($this).data("popup-class"));
			}
		}
	);
}

function sln_initSelectSpecificService(e) {
	jQuery(".sln-select select")
		.not(
			"#_sln_attendant_availabilities___new___day_specific_service, #_sln_attendant_availabilities___new___day_specific_resource"
		)
		.each(function () {
			jQuery(this)
				.parent()
				.find("span.select2.select2-container.select2-container--sln")
				.remove();
		})
		.select2({
			containerCssClass:
				"sln-select-rendered " +
				(jQuery(this).attr("data-containerCssClass")
					? jQuery(this).attr("data-containerCssClass")
					: ""),
			dropdownCssClass: "sln-select-dropdown",
			theme: "sln",
			width: "100%",
			templateResult: function (state) {
				if (!state.id) return state.text;
				return jQuery(
					'<span data-value="' +
						state.id +
						'">' +
						state.text +
						"</span>"
				);
			},
			placeholder: jQuery(this).data("placeholder"),
		});
	jQuery(".sln_attendant_day_service select")
		.not("#_sln_attendant_availabilities___new___day_specific_service")
		.on("select2:open", function () {
			let available_services = jQuery("#_sln_attendant_services").val();
			available_services = available_services.map((val) =>
				val.split("_").pop()
			);
			let current_resource = Number(
				jQuery(this)
					.closest(".row.sln-select-specific-dates-calendar")
					.find(".sln_attendant_day_resource select")
					.val()
			);
			let has_curr_res = undefined;
			let li_el = null;
			jQuery(this)
				.find("option")
				.not('option[value="0"]')
				.each(function (index, val) {
					has_curr_res = jQuery(val)
						.data("resources")
						.split(",")
						.includes(String(current_resource));
					console.log(
						available_services.length &&
							available_services.includes(val.value) &&
							current_resource &&
							has_curr_res,
						available_services.length &&
							!current_resource &&
							available_services.includes(val.value),
						!available_services.length &&
							current_resource &&
							has_curr_res,
						!available_services.length && !current_resource
					);
					if (
						(available_services.length &&
							available_services.includes(val.value) &&
							current_resource &&
							has_curr_res) ||
						(available_services.length &&
							!current_resource &&
							available_services.includes(val.value)) ||
						(!available_services.length &&
							current_resource &&
							has_curr_res) ||
						(!available_services.length && !current_resource)
					) {
						val.removeAttribute("disabled");
						setTimeout(
							function (value, li_el) {
								li_el = jQuery(
									"#select2-_sln_attendant_availabilities_1_day_specific_service-results li"
								).filter(function () {
									return (
										jQuery(this).attr("id") &&
										value.value ==
											jQuery(this)
												.attr("id")
												.split("-")
												.pop()
									);
								});
								li_el = li_el.length
									? jQuery(li_el)
											.removeAttr("style")
											.removeAttr("aria-disabled")
											.attr("aria-selected", false)
									: li_el;
							},
							1,
							val,
							li_el
						);
						console.log("enable", val.value);
					} else {
						val.setAttribute("disabled", "disabled");
						setTimeout(
							function (value, li_el) {
								li_el = jQuery(
									"#select2-_sln_attendant_availabilities_1_day_specific_service-results li"
								).filter(function () {
									return (
										jQuery(this).attr("id") &&
										value.value ==
											jQuery(this)
												.attr("id")
												.split("-")
												.pop()
									);
								});
								li_el = li_el.length
									? jQuery(li_el)
											.css("display", "none")
											.attr("aria-disabled", true)
											.removeAttr("aria-selected")
									: li_el;
							},
							1,
							val,
							li_el
						);
						console.log("disable", val.value);
					}
				});
			jQuery(this).trigger("change");
		});
}
