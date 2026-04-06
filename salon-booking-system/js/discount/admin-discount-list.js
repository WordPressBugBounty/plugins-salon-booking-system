"use strict";

jQuery(function ($) {
	var l10n = window.slnDiscountListL10n || {};
	var copiedMsg = l10n.copied || "Copied!";
	var failedMsg = l10n.copyFailed || "Could not copy to clipboard.";

	function fallbackCopy(text) {
		var ta = document.createElement("textarea");
		ta.value = text;
		ta.setAttribute("readonly", "");
		ta.style.position = "fixed";
		ta.style.left = "-9999px";
		document.body.appendChild(ta);
		ta.select();
		var ok = false;
		try {
			ok = document.execCommand("copy");
		} catch (e) {
			ok = false;
		}
		document.body.removeChild(ta);
		return ok;
	}

	$(document).on("click", ".sln-copy-coupon-code", function (e) {
		e.preventDefault();
		var $btn = $(this);
		var code = $btn.attr("data-code");
		if (!code) {
			return;
		}

		var defaultTitle = $btn.attr("data-default-title") || "";

		var doneOk = function () {
			$btn.addClass("sln-is-copied").attr("title", copiedMsg);
			if (window.wp && wp.a11y && typeof wp.a11y.speak === "function") {
				wp.a11y.speak(copiedMsg);
			}
			setTimeout(function () {
				$btn.removeClass("sln-is-copied").attr("title", defaultTitle);
			}, 1500);
		};

		var doneFail = function () {
			window.alert(failedMsg);
		};

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(code).then(doneOk).catch(function () {
				if (fallbackCopy(code)) {
					doneOk();
				} else {
					doneFail();
				}
			});
		} else if (fallbackCopy(code)) {
			doneOk();
		} else {
			doneFail();
		}
	});
});
