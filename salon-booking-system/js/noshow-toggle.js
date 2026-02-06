/**
 * No-Show Toggle Handler
 * Unified functionality for toggling no-show status from calendar tooltip and booking metabox
 */
(function ($) {
  "use strict";

  /**
   * Toggle no-show status for a booking
   * @param {Object} options Configuration object
   * @param {number} options.bookingId - Booking ID
   * @param {number} options.currentNoShow - Current no-show status (0 or 1)
   * @param {string} options.nonce - Security nonce
   * @param {jQuery} options.$button - Button element (optional)
   * @param {Function} options.onSuccess - Success callback (optional)
   * @param {Function} options.onError - Error callback (optional)
   */
  window.slnToggleNoShow = function (options) {
    var settings = $.extend(
      {
        bookingId: 0,
        currentNoShow: 0,
        nonce: "",
        $button: null,
        onSuccess: null,
        onError: null,
        debug: false,
      },
      options
    );

    // Validation
    if (!settings.bookingId) {
      console.error("❌ No booking ID provided");
      return;
    }

    if (!settings.nonce) {
      console.error("❌ No security nonce provided");
      return;
    }

    // Add processing state
    if (settings.$button) {
      if (settings.$button.hasClass("processing")) {
        return; // Already processing
      }
      settings.$button.addClass("processing");
    }

    if (settings.debug) {
      console.log("🚀 Toggle no-show:", {
        bookingId: settings.bookingId,
        currentNoShow: settings.currentNoShow,
        nonce: settings.nonce,
      });
    }

    // Make AJAX request
    $.ajax({
      url: window.ajaxurl || salon.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "sln_ajax_noshow",
        bookingId: settings.bookingId,
        noShow: settings.currentNoShow,
        security: settings.nonce,
      },
      success: function (response) {
        if (settings.debug) {
          console.log("📥 No-show response:", response);
        }

        // Check for error
        if (!response.success) {
          var errorMsg =
            (response.data && response.data.error) ||
            "Failed to update no-show status";
          console.error("❌ Backend error:", errorMsg);

          if (settings.onError) {
            settings.onError(errorMsg, response);
          } else {
            alert("Error: " + errorMsg);
          }
          return;
        }

        // Success - get data
        var data = response.data;

        if (settings.debug) {
          console.log("✅ No-show toggled:", data);
        }

        // Update button data attribute
        if (settings.$button) {
          settings.$button.data("no-show", data.noShow);

          // Toggle active class
          if (data.noShow === 1) {
            settings.$button.addClass("active");
          } else {
            settings.$button.removeClass("active");
          }
        }

        // Call success callback
        if (settings.onSuccess) {
          settings.onSuccess(data, response);
        }
      },
      error: function (xhr, status, error) {
        console.error("❌ AJAX error:", {
          xhr: xhr,
          status: status,
          error: error,
          responseText: xhr.responseText,
        });

        var errorMsg = "Network error. Please try again.";
        if (settings.onError) {
          settings.onError(errorMsg, xhr);
        } else {
          alert(errorMsg);
        }
      },
      complete: function () {
        // Remove processing state
        if (settings.$button) {
          settings.$button.removeClass("processing");
        }
      },
    });
  };
})(jQuery);

