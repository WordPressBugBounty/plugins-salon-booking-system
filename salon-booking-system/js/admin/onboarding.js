(function ($) {
	"use strict";
	var currentStep = 1;
	var totalSteps = 5;
	var cfg = window.slnOnboarding || {};
	var i18n = cfg.i18n || {};

	function setStep(step) {
		step = Math.max(1, Math.min(totalSteps, step));
		currentStep = step;
		$(".sln-onboarding-wizard__panel").removeClass("sln-onboarding-wizard__panel--active");
		$(".sln-onboarding-wizard__panel[data-step=" + step + "]").addClass("sln-onboarding-wizard__panel--active");
		$(".sln-onboarding-wizard__step").each(function () {
			var $step = $(this);
			var n = parseInt($step.data("step"), 10);
			$step
				.removeClass("sln-onboarding-wizard__step--active sln-onboarding-wizard__step--done")
				.addClass(n === step ? "sln-onboarding-wizard__step--active" : n < step ? "sln-onboarding-wizard__step--done" : "");
			if (n < step) {
				$step.find(".sln-onboarding-wizard__step-num").text("✓");
			} else {
				$step.find(".sln-onboarding-wizard__step-num").text(n);
			}
		});
		$(".sln-onboarding-wizard__step-line").each(function () {
			var n = parseInt($(this).data("step"), 10);
			$(this).toggleClass("sln-onboarding-wizard__step-line--done", n < step);
		});
	}

	function saveStep(step, data, done) {
		var payload = {
			action: "sln_onboarding_save_step",
			nonce: cfg.nonce,
			step: step,
			data: data
		};
		if (step === 1 && data.usage_goal) {
			payload.usage_goal = data.usage_goal;
		}
		$.post(cfg.ajaxUrl, payload)
			.done(function (r) {
				if (r && r.success) {
					if (typeof done === "function") done();
				} else {
					if (typeof done === "function") done(new Error(r && r.data && r.data.message ? r.data.message : i18n.error));
				}
			})
			.fail(function (xhr) {
				var msg = i18n.error;
				if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					msg = xhr.responseJSON.data.message;
				}
				if (typeof done === "function") done(new Error(msg));
			});
	}

	function collectStep1Data() {
		return {
			usage_goal: $("input[name='sln_usage_goal']:checked").val() || "",
			gen_name: $("#sln_onboarding_gen_name").val() || "",
			attendant_enabled: $("input[name='attendant_enabled']").val() || "1",
			parallels_hour: $("#sln_onboarding_parallels_hour").val() || "1",
			interval: $("#sln_onboarding_interval").val() || "15"
		};
	}

	function collectStep2Data() {
		var availDays = {};
		$("input[name^='avail_days']").each(function () {
			var name = $(this).attr("name");
			var k = name.replace("avail_days[", "").replace("]", "");
			availDays[k] = $(this).prop("checked") ? 1 : 0;
		});
		return {
			avail_days: availDays,
			avail_from_0: $("input[name='avail_from_0']").val() || "09:00",
			avail_to_0: $("input[name='avail_to_0']").val() || "13:00",
			avail_from_1: $("input[name='avail_from_1']").val() || "14:00",
			avail_to_1: $("input[name='avail_to_1']").val() || "18:00"
		};
	}

	$(function () {
		$(".sln-onboarding-wizard__goal input").on("change", function () {
			$(".sln-onboarding-wizard__goal").removeClass("sln-onboarding-wizard__goal--selected");
			$(this).closest(".sln-onboarding-wizard__goal").addClass("sln-onboarding-wizard__goal--selected");
		});

		$(".sln-onboarding-wizard__day input").on("change", function () {
			var $day = $(this).closest(".sln-onboarding-wizard__day");
			$day.toggleClass("sln-onboarding-wizard__day--selected", $(this).prop("checked"));
		});

		$(".sln-onboarding-wizard__btn-next").on("click", function () {
			var step = parseInt($(this).data("step"), 10);
			if (step === 1) {
				var data = collectStep1Data();
				saveStep(1, data, function (err) {
					if (!err) setStep(2);
					else alert(err.message || i18n.error);
				});
			} else if (step === 2) {
				var data = collectStep2Data();
				saveStep(2, data, function (err) {
					if (!err) setStep(3);
					else alert(err.message || i18n.error);
				});
			} else if (step >= 3 && step <= 4) {
				setStep(step + 1);
			}
		});

		$(".sln-onboarding-wizard__btn-back").on("click", function () {
			var step = parseInt($(this).data("step"), 10);
			setStep(step - 1);
		});

		$(".sln-onboarding-wizard__btn-skip").on("click", function () {
			var step = parseInt($(this).data("step"), 10);
			setStep(step + 1);
		});

		$(".sln-onboarding-wizard__btn-complete").on("click", function (e) {
			e.preventDefault();
			var $btn = $(this);
			$btn.prop("disabled", true).text(i18n.saving || "Saving...");
			$.post(cfg.ajaxUrl, {
				action: "sln_onboarding_complete",
				nonce: cfg.nonce
			})
				.done(function (r) {
					if (r && r.success && r.data && r.data.redirect) {
						window.location.href = r.data.redirect;
					} else {
						window.location.href = (cfg.calendarUrl || "/wp-admin/admin.php?page=salon");
					}
				})
				.fail(function () {
					window.location.href = (cfg.calendarUrl || "/wp-admin/admin.php?page=salon");
				});
		});

		$(".sln-onboarding-wizard__step").on("click", function () {
			var step = parseInt($(this).data("step"), 10);
			if (step < currentStep) setStep(step);
		});
	});
})(jQuery);
