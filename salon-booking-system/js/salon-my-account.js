// algolplus

"use strict";

var sln_myAccount = {
  cancelBooking: function (id) {
    if (!confirm(salon.confirm_cancellation_text)) {
      return;
    }
    sln_myAccount.showActionNotification(
      jQuery('button[onclick="sln_myAccount.cancelBooking(' + id + ');"]'),
      jQuery('button[onclick="sln_myAccount.cancelBooking(' + id + ');"]').attr(
        "data-message",
      ),
    );

    jQuery.ajax({
      url: salon.ajax_url,
      data: {
        action: "salon",
        method: "cancelBooking",
        id: id,
        security: salon.ajax_nonce, // Add nonce for CSRF protection
      },
      method: "POST",
      dataType: "json",
      success: function (data) {
        if (typeof data.redirect != "undefined") {
          window.location.href = data.redirect;
        } else if (data.success != 1) {
          alert("error");
          console.log(data);
        } else {
          sln_myAccount.loadContent("cancelled");
        }
      },
      error: function (data) {
        alert("error");
        console.log(data);
      },
    });
  },

  loadContent: function (option) {
    let self = this;
    jQuery.ajax({
      url: salon.ajax_url,
      data: {
        action: "salon",
        method: "myAccountDetails",
        option: option,
        customer_timezone: new window.Intl.DateTimeFormat().resolvedOptions()
          .timeZone,
      },
      method: "POST",
      dataType: "json",
      success: function (data) {
        if (typeof data.redirect != "undefined") {
          window.location.href = data.redirect;
        } else {
          jQuery("#sln-salon-my-account-content").html(data.content);
          sln_createSelect2Full(jQuery);
          sln_createRatings(true, "star");
          jQuery("[data-toggle='tooltip']").tooltip();

          if (sln_myAccount.feedback_id) {
            sln_myAccount.showRateForm(sln_myAccount.feedback_id);
          }
          sln_myAccount.setActiveTab();
          jQuery(".nav-tabs a").on("show.bs.tab", sln_myAccount.setActiveHash);
          jQuery('#salon-my-account-profile-form input[name="action"]').val(
            "salon",
          );
          jQuery("#salon-my-account-profile-form").on(
            "submit",
            sln_myAccount.updateProfile,
          );
          console.log("init");
          jQuery(
            "#discount .sln-discount-content.sln-discount-name span.sln-discount-btn",
          ).on("click", function () {
            jQuery(this)
              .closest(".discount-container")
              .find(".collaps")
              .toggleClass("hide");
            let parent = jQuery(this).parent();
            parent.find(".sln-discount-icon--up").toggleClass("hide");
            parent.find(".sln-discount-icon--down").toggleClass("hide");
          });

          var items = {
            intervals: {},
            booking_id: jQuery("#salon-step-date").data("booking_id"),
          };

          sln_initDatePickersReschedule(jQuery, items);
          sln_initTimePickersReschedule(jQuery, items);

          var doingFunc = null;

          var func = function () {
            clearTimeout(doingFunc);
            doingFunc = setTimeout(function () {
              sln_updateDatepickerTimepickerSlots(jQuery, items.intervals);
            }, 200);
          };

          self.init_input(jQuery);

          jQuery("body").off("sln_date", func).on("sln_date", func);

          jQuery("body").on("sln_date", function () {
            setTimeout(function () {
              jQuery(".datetimepicker-days table tr td.day").on(
                "click",
                function () {
                  if (jQuery(this).hasClass("disabled")) {
                    return;
                  }
                  const datetimepicker = jQuery(".sln_datepicker div").data(
                    "datetimepicker",
                  );

                  const date = jQuery(this).attr("data-ymd");

                  const dateObj = jQuery.fn.datetimepicker.DPGlobal.parseDate(
                    date,
                    datetimepicker.format,
                    datetimepicker.language,
                    datetimepicker.formatType,
                  );

                  const formattedDate =
                    jQuery.fn.datetimepicker.DPGlobal.formatDate(
                      dateObj,
                      datetimepicker.format,
                      datetimepicker.language,
                      datetimepicker.formatType,
                    );

                  jQuery("input[name='_sln_booking_date']").val(formattedDate);
                },
              );
            });
          });

          window.updateSelectedDateTimeDisplay = function(form) {
            var selectedDate = form.find('input[name="_sln_booking_date"]').val();
            var selectedTime = form.find('input[name="_sln_booking_time"]').val();
            
            if (selectedDate && selectedTime) {
              // Parse the date - handle multiple formats
              var dateObj;
              
              // Try DD/MM/YYYY format first
              if (selectedDate.indexOf('/') !== -1) {
                var dateParts = selectedDate.split('/');
                dateObj = new Date(dateParts[2], dateParts[1] - 1, dateParts[0]);
              } 
              // Try YYYY-MM-DD format
              else if (selectedDate.indexOf('-') !== -1) {
                dateObj = new Date(selectedDate);
              }
              // Try MM/DD/YYYY format
              else {
                dateObj = new Date(selectedDate);
              }
              
              // Check if date is valid
              if (isNaN(dateObj.getTime())) {
                console.log('Invalid date format:', selectedDate);
                return;
              }
              
              var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
              var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
              
              var dayName = days[dateObj.getDay()];
              var day = dateObj.getDate();
              var monthName = months[dateObj.getMonth()];
              
              var formattedDateTime = dayName + ' ' + day + ' ' + monthName + ' at ' + selectedTime;
              
              form.find('.sln-reschedule-selected-datetime').html(formattedDateTime);
            }
          };

          function validate(obj) {
            var form = jQuery(obj).closest("form");

            var validatingMessage =
              '<div class="sln-loader">' + salon.txt_validating + "</div>";

            form
              .find(".sln-notifications")
              .addClass("sln-notifications--active")
              .html(validatingMessage);

            form.find(".sln-reschedule-form--save-button").addClass("disabled");

            var data = form.serialize();

            data +=
              "&action=salon&method=rescheduleBookingCheckDate&security=" +
              salon.ajax_nonce;

            jQuery.ajax({
              url: salon.ajax_url,
              data: data,
              method: "POST",
              dataType: "json",
              success: function (data) {
                items.intervals = data.intervals;
                items.booking_id = data.booking_id;

                func();

                if (!data.success) {
                  /*var alertBox = jQuery('<div class="sln-alert sln-alert--problem"></div>');

                                    jQuery(data.errors).each(function (i, obj) {
                                        alertBox.append(jQuery('<p></p>').html(obj));
                                    });

                                    form.find('.sln-notifications').html('').append(alertBox);*/

                  form.find(".sln-notifications").html("");

                  form
                    .find('input[name="_sln_booking_date"]')
                    .val(data.intervals.suggestedDate);

                  var datetimepicker = form
                    .find(".sln_datepicker div")
                    .data("datetimepicker");

                  var suggestedDate =
                    jQuery.fn.datetimepicker.DPGlobal.parseDate(
                      data.intervals.suggestedDate,
                      datetimepicker.format,
                      datetimepicker.language,
                      datetimepicker.formatType,
                    );

                  datetimepicker.setUTCDate(suggestedDate);

                  var timeValue = Object.values(data.intervals.times)[0] || "";
                  var hours = parseInt(timeValue, 10) || 0;
                  var datetimepicker = form
                    .find(".sln_timepicker div")
                    .data("datetimepicker");
                  datetimepicker.viewDate.setUTCHours(hours);
                  var minutes =
                    parseInt(
                      timeValue.substr(timeValue.indexOf(":") + 1),
                      10,
                    ) || 0;
                  datetimepicker.viewDate.setUTCMinutes(minutes);
                  form.find('input[name="_sln_booking_time"]').val(timeValue);

                  sln_renderAvailableTimeslots(jQuery, data);
                  jQuery("body").trigger("sln_date");
                  updateSelectedDateTimeDisplay(form);
                } else {
                  form
                    .find('input[name="_sln_booking_date"]')
                    .val(data.intervals.suggestedDate);
                  form
                    .find('input[name="_sln_booking_time"]')
                    .val(data.intervals.suggestedTime);

                  form
                    .find(".sln-reschedule-form--save-button")
                    .removeClass("disabled");
                  form
                    .find(".sln-notifications")
                    .html("")
                    .removeClass("sln-notifications--active");

                  var timeValue = Object.values(data.intervals.times)[0] || "";
                  var hours = parseInt(timeValue, 10) || 0;
                  var datetimepicker = form
                    .find(".sln_timepicker div")
                    .data("datetimepicker");
                  datetimepicker.viewDate.setUTCHours(hours);
                  var minutes =
                    parseInt(
                      timeValue.substr(timeValue.indexOf(":") + 1),
                      10,
                    ) || 0;
                  datetimepicker.viewDate.setUTCMinutes(minutes);
                  sln_renderAvailableTimeslots(jQuery, data);
                  jQuery("body").trigger("sln_date");
                  form.find('input[name="_sln_booking_time"]').val(timeValue);
                  updateSelectedDateTimeDisplay(form);
                }
                form
                  .find(".sln-notifications")
                  .removeClass("sln-notifications--active");
              },
            });
          }

          jQuery(".sln_datepicker div").on("changeDay", function () {
            validate(this);
          });

          jQuery(".sln-reschedule-booking--button").on("click", function () {
            const bookingTime = jQuery(this)
              .parent()
              .find("form.sln-reschedule-form input.sln_booking_default_time")
              .val();
            const bookingDate = jQuery(this)
              .parent()
              .find("form.sln-reschedule-form input.sln_booking_default_date")
              .val();
            const cancelBookingButton = jQuery(this)
              .parent()
              .find(".sln-cancel-booking--button");

            var datetimepicker = jQuery(".sln_datepicker div").data(
              "datetimepicker",
            );

            var suggestedDate = jQuery.fn.datetimepicker.DPGlobal.parseDate(
              bookingDate,
              datetimepicker.format,
              datetimepicker.language,
              datetimepicker.formatType,
            );

            datetimepicker.setUTCDate(suggestedDate);

            jQuery(this)
              .parent()
              .find(".sln-reschedule-form")
              .removeClass("hide");
            jQuery(this).addClass("hide");
            cancelBookingButton.addClass("hide");
            jQuery(this).parent().parent().addClass("sln-account__card--open");

            jQuery(this)
              .parent()
              .find(".sln-reschedule-form")
              .find('input[name="_sln_booking_date"]')
              .val(bookingDate);
            jQuery(this)
              .parent()
              .find(".sln-reschedule-form")
              .find('input[name="_sln_booking_time"]')
              .val(bookingTime);

            validate(jQuery(this).parent().find(".sln-reschedule-form"));
            //jQuery("body").trigger("sln_date");
            //jQuery(this).closest('tr').find('.sln_datepicker div').trigger('changeDay');
          });

          jQuery(".sln-reschedule-form--cancel-button").on(
            "click",
            function () {
              jQuery(this)
                .closest("footer")
                .find(".sln-reschedule-form")
                .addClass("hide");
              jQuery(this)
                .closest("footer")
                .find(".sln-reschedule-booking--button")
                .removeClass("hide");
              jQuery(this)
                .closest("footer")
                .find(".sln-cancel-booking--button")
                .removeClass("hide");
              jQuery(this)
                .closest("footer")
                .find(".sln-notifications")
                .html("");
              jQuery(this)
                .closest("footer")
                .find(".sln-reschedule-form--save-button")
                .removeClass("disabled");
              jQuery(this).closest("footer").find("form").trigger("reset");
              jQuery(this)
                .closest(".sln-account__card")
                .removeClass("sln-account__card--open");
            },
          );

          jQuery(".sln-reschedule-form--save-button").on("click", function () {
            var self = this;

            if (jQuery(self).hasClass("disabled")) {
              return false;
            }

            var data = jQuery(self).closest(".sln-reschedule-form").serialize();

            data +=
              "&action=salon&method=rescheduleBooking&security=" +
              salon.ajax_nonce;

            jQuery.ajax({
              url: salon.ajax_url,
              data: data,
              method: "POST",
              dataType: "json",
              success: function (response) {
                if (typeof response.redirect != "undefined") {
                  window.location.href = response.redirect;
                }
                let date_string = response.booking_date;
                let date_time = response.booking_time;
                jQuery(self)
                  .closest("article.sln-account__booking")
                  .find("h3.sln-account__booking__date span.sln-booking-date")
                  .html(date_string);
                jQuery(self)
                  .closest("article.sln-account__booking")
                  .find("h3.sln-account__booking__date span.sln-booking-time")
                  .html(date_time);

                if (response.booking_status == "sln-b-pending") {
                  let statusIcon = jQuery(self)
                    .closest("tr")
                    .find(".status .glyphicon")
                    .removeAttr("class")
                    .addClass("glyphicon");
                  jQuery(statusIcon)
                    .parent()
                    .find(".glyphicon-class strong")
                    .text(response.booking_status_label.toUpperCase());
                  jQuery(statusIcon).addClass("glyphicon-clock");
                  jQuery(self)
                    .closest("tr")
                    .find(".sln-reschedule-booking--button")
                    .attr("style", "display: none !important;");
                  jQuery(document).scrollTop(0);
                  alert("Booking is updated");
                }

                jQuery(self)
                  .closest("form")
                  .find('input[name="_sln_booking_date"]')
                  .attr("value", response.booking_date);
                jQuery(self)
                  .closest("form")
                  .find('input[name="_sln_booking_time"]')
                  .attr("value", response.booking_time);

                jQuery(self)
                  .closest("form")
                  .find(".sln-reschedule-form--cancel-button")
                  .trigger("click");

                jQuery("html, body").animate(
                  {
                    scrollTop:
                      jQuery(self)
                        .closest("article.sln-account__booking")
                        .find(
                          "h3.sln-account__booking__date span.sln-booking-date",
                        )
                        .offset().top - 300,
                  },
                  1000,
                );

                sln_myAccount.showActionNotification(
                  self,
                  jQuery(self)
                    .closest("footer")
                    .find(".sln-reschedule-booking--button")
                    .attr("data-message"),
                );
              },
              error: function (data) {
                alert("error");
                console.log(data);
              },
            });
          });

          jQuery(
            ".sln-account__card__action_notification .sln_account__notification_action__close",
          ).on("click", function () {
            jQuery(this)
              .closest(".sln-account__card__action_notification")
              .addClass("sln-account__card__action_notification--hide")
              .removeClass("sln-account__card__action_notification--visible");
          });

          jQuery("#sln-salon-my-account-history-content").on(
            "mousewheel",
            function (e) {
              if (
                e.originalEvent.wheelDelta < 0 &&
                jQuery(document).height() <=
                  jQuery(window).scrollTop() + jQuery(window).height()
              ) {
                sln_myAccount.loadNextHistoryPage();
              }
            },
          );

          jQuery(".nav-tabs a").on("shown.bs.tab", function (e) {
            if (jQuery(e.target).attr("data-target") !== "#profile") {
              return true;
            }

            var input = document.querySelector("#sln_phone");

            if (
              input &&
              jQuery("#sln_sms_prefix").length &&
              !!!jQuery(input).closest(".iti").length
            ) {
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
                  (jQuery("#sln_sms_prefix").val() || "").replace("+", ""),
                ),
                separateDialCode: true,
                autoHideDialCode: true,
                nationalMode: false,
              });

              input.addEventListener("countrychange", function () {
                if (iti.getSelectedCountryData().dialCode) {
                  jQuery("#sln_sms_prefix").val(
                    "+" + iti.getSelectedCountryData().dialCode,
                  );
                }
              });

              input.addEventListener("blur", function () {
                if (iti.getSelectedCountryData().dialCode) {
                  jQuery(input).val(
                    jQuery(input)
                      .val()
                      .replace("+" + iti.getSelectedCountryData().dialCode, ""),
                  );
                }
              });
            }
          });
        }
      },
      error: function (data) {
        alert("error");
        console.log(data);
      },
    });
  },

  init_input: function ($) {
    var input = document.querySelector("#sln_phone");

    if (input && $("#sln_sms_prefix").length) {
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
          ($("#sln_sms_prefix").val() || "").replace("+", ""),
        ),
        separateDialCode: true,
        autoHideDialCode: true,
        nationalMode: false,
      });

      input.addEventListener("keydown", function (event) {
        if (
          /[^0-9]/.test(event.key) &&
          !/(Backspace)|(Enter)|(Tab)|(ArrowLeft)|(ArrowRight)|(Delete)/.test(
            event.key,
          )
        ) {
          event.preventDefault();
        }
      });

      input.addEventListener("countrychange", function () {
        if (iti.getSelectedCountryData().dialCode) {
          $("#sln_sms_prefix").val("+" + iti.getSelectedCountryData().dialCode);
        }
      });
      input.addEventListener("blur", function () {
        if (iti.getSelectedCountryData().dialCode) {
          $(input).val(
            $(input)
              .val()
              .replace("+" + iti.getSelectedCountryData().dialCode, ""),
          );
        }
      });
    }
    if ($(".sln-file__content").length) {
      const dropInputParents = document.querySelectorAll(".sln-file__content");
      dropInputParents.forEach((dropInputParent) => {
        window.addEventListener(
          "dragenter",
          function (e) {
            //if (e.target.id != dropzoneId) {
            if (!e.target.classList.contains("sln-input--file__act")) {
              e.preventDefault();
              e.dataTransfer.effectAllowed = "none";
              e.dataTransfer.dropEffect = "none";
            }
            dropInputParent.classList.add("sln-file__content--draghover");
            //console.log('dragenter');
          },
          false,
        );

        window.addEventListener("dragover", function (e) {
          if (!e.target.classList.contains("sln-file__act")) {
            e.preventDefault();
            e.dataTransfer.effectAllowed = "none";
            e.dataTransfer.dropEffect = "none";
          }
          dropInputParent.classList.add("sln-file__content--draghover");
          //console.log('dragover');
        });

        window.addEventListener("dragleave", function (e) {
          if (!e.target.classList.contains("sln-file__act")) {
            e.preventDefault();
            e.dataTransfer.effectAllowed = "none";
            e.dataTransfer.dropEffect = "none";
          }
          dropInputParent.classList.remove("sln-file__content--draghover");
          //console.log('drop');
        });

        window.addEventListener("drop", function (e) {
          if (!e.target.classList.contains("sln-file__act")) {
            e.preventDefault();
            e.dataTransfer.effectAllowed = "none";
            e.dataTransfer.dropEffect = "none";
          }
          dropInputParent.classList.remove("sln-file__content--draghover");
          //console.log('drop');
        });

        dropInputParent.addEventListener("dragenter", function (e) {
          this.classList.add("sln-file__content--draghover--fine");
          //console.log('dragover');
        });
        dropInputParent.addEventListener("dragover", function (e) {
          this.classList.add("sln-file__content--draghover--fine");
          //console.log('dragover');
        });
        dropInputParent.addEventListener("dragleave", function (e) {
          this.classList.remove("sln-file__content--draghover--fine");
          //console.log('dragover');
        });
        dropInputParent.addEventListener("drop", function (e) {
          this.classList.remove("sln-file__content--draghover--fine");
          //console.log('dragover');
        });
        // dropInputParents.forEach END
      });
    }
    $(".sln-file input[type=file]").on("change", function (e) {
      let file_list = $(this).parent().find(".sln-file__list");
      //if(!file_list.children().length){
      //    $(this).parent().find('label:last-child').text(' ').addClass('sln-input-file--select')
      //}
      $(".sln-file__errors").remove();
      $(".sln-file__progressbar__wrapper").remove();

      var formData = new FormData();
      formData.append("action", "salon");
      formData.append("method", "UploadFile");
      formData.append("security", salon.ajax_nonce);
      formData.append("file", this.files[0]);

      let file_name = this.files[0].name;
      this.files = undefined;
      this.value = "";

      var self = this;
      file_list.append(
        $(
          '<li class="sln-file__progressbar__wrapper"><div class="sln-file__progressbar"><div class="sln-file__progressbar__value"></div></div><div class="sln-file__progressbar__percentage"></div></li>',
        ),
      );

      $.ajax({
        xhr: function () {
          var xhr = new window.XMLHttpRequest();

          xhr.upload.addEventListener(
            "progress",
            function (evt) {
              if (evt.lengthComputable) {
                var percentComplete = evt.loaded / evt.total;
                percentComplete = parseInt(percentComplete * 100);
                //console.log(percentComplete);

                $(".sln-file__progressbar__value").css(
                  "width",
                  percentComplete + "%",
                );
                $(".sln-file__progressbar__percentage").text(
                  percentComplete + "%",
                );

                if (percentComplete === 100) {
                }
              }
            },
            false,
          );

          return xhr;
        },
        url: salon.ajax_url,
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        dataType: "json",
        success: function (result) {
          $(".sln-file__progressbar__wrapper").remove();
          if (result.success) {
            let input_file =
              '<input type="hidden" name="' +
              $(self).attr("name") +
              '" value="' +
              result.file +
              '">';
            file_list.append(
              $(
                '<li><i class="sr-only">delete</i><span class="sln-file__name">' +
                  file_name +
                  '</span><span class="sln-file__remove"></span></li>',
              ).append(input_file),
            );
            file_list
              .children()
              .last()
              .find(".sln-file__remove")
              .on("click", function (e) {
                e.stopPropagation();
                var self = this;
                $.post(
                  salon.ajax_url,
                  {
                    action: "salon",
                    method: "RemoveUploadedFile",
                    security: salon.ajax_nonce,
                    file: result.file,
                  },
                  function () {
                    $(self).closest("li").remove();
                  },
                );
              });
          } else {
            file_list.append(
              $(
                '<li class="sln-file__errors">' +
                  result.errors.join(",") +
                  "</li>",
              ),
            );
          }
        },
      });
    });
  },

  loadNextHistoryPage: function () {
    var page =
      parseInt(
        jQuery("#sln-salon-my-account-history-content table tr:last").attr(
          "data-page",
        ),
      ) + 1;
    jQuery.ajax({
      url: salon.ajax_url,
      data: {
        action: "salon",
        method: "myAccountDetails",
        args: {
          part: "history",
          page: page,
        },
      },
      method: "POST",
      dataType: "json",
      success: function (data) {
        if (typeof data.redirect != "undefined") {
          window.location.href = data.redirect;
        } else {
          jQuery("#sln-salon-my-account-history-content").html(data.content);
          if (
            jQuery("#sln-salon-my-account-history-content table tr:last").attr(
              "data-end",
            ) == 1
          ) {
            jQuery("#next_history_page_btn").remove();
          }
          sln_createRatings(true, "circle");
          jQuery("[data-toggle='tooltip']").tooltip();
        }
      },
      error: function (data) {
        alert("error");
        console.log(data);
      },
    });
  },

  showRateForm: function (id) {
    if (salon.feedback_url) {
      let feedback_url = "";
      if (salon.feedback_url.indexOf("?") == -1) {
        feedback_url =
          salon.feedback_url +
          "?id=" +
          id +
          "&return_url=" +
          encodeURIComponent(salon.ajax_url) +
          "&action=salon&method=setBookingRating";
      } else {
        feedback_url =
          salon.feedback_url +
          "&id=" +
          id +
          "&return=" +
          encodeURIComponent(salon.ajax_url) +
          "&action=salon&method=setBookingRating";
      }
      window.open(feedback_url, "_blank");
      return false;
    } else {
      sln_createRaty(jQuery("#ratingModal .rating"));
      jQuery("#ratingModal textarea").attr("id", id);
      jQuery("#ratingModal textarea").val("");

      jQuery("#ratingModal #step2").css("display", "none");
      jQuery("#ratingModal").modal("show");
      jQuery("#ratingModal #step1").css("display", "block");

      return false;
    }
  },

  sendRate: function () {
    if (
      jQuery("#ratingModal .rating").raty("score") == undefined ||
      jQuery("#ratingModal textarea").val() == ""
    )
      return false;
    let post_id = jQuery("#ratingModal textarea").attr("id");

    jQuery.ajax({
      url: salon.ajax_url,
      data: {
        action: "salon",
        method: "setBookingRating",
        id: post_id,
        score: jQuery("#ratingModal .rating").raty("score"),
        comment: jQuery("#ratingModal textarea").val(),
      },
      method: "POST",
      dataType: "json",
      success: function (data) {
        if (typeof data.redirect != "undefined") {
          window.location.href = data.redirect;
        } else if (data.success != 1) {
          alert("error");
          console.log(data);
        } else {
          jQuery("#ratingModal #step1").css("display", "none");
          jQuery("#ratingModal #step2").css("display", "block");

          jQuery("#ratingModal .close")
            .delay(2000)
            .queue(function () {
              jQuery(this).trigger("click");
              sln_myAccount.loadContent();
              jQuery(this).dequeue();
            });

          sln_myAccount.showActionNotification(
            jQuery(
              'button[onclick="sln_myAccount.showRateForm(' + post_id + ');"]',
            ),
            jQuery(
              'button[onclick="sln_myAccount.showRateForm(' + post_id + ');"]',
            ).attr("data-message"),
          );
          sln_myAccount.feedback_id = false;
        }
      },
      error: function (data) {
        alert("error");
        console.log(data);
      },
    });
    return false;
  },

  setActiveHash: function (e) {
    window.location.hash = e.target.hash;
  },

  setActiveTab: function (hash) {
    var hash = hash ? hash : window.location.hash;
    if (hash) {
      jQuery('.nav-tabs a[href="' + hash + '"]').tab("show");
      jQuery('#sln-account__nav a[data-target="' + hash + '"]').tab("show");
    }
  },

  showActionNotification: function (call_element, action_name) {
    if (!jQuery(call_element).hasClass("sln-account__card")) {
      call_element = jQuery(call_element).closest("article.sln-account__card");
      if (!call_element) {
        return false;
      }
    }
    let notification = call_element.find(
      ".sln-account__card__action_notification",
    );
    notification
      .find(
        ".sln-account__notification_action__text .sln-account__notification_action_name",
      )
      .text(action_name);
    notification
      .removeClass("sln-account__card__action_notification--hide")
      .addClass("sln-account__card__action_notification--visible");
    setTimeout(function () {
      notification
        .addClass("sln-account__card__action_notification--hide")
        .removeClass("sln-account__card__action_notification--visible");
    }, 3000);
  },

  updateProfile: function (e) {
    e.preventDefault();
    var form = e.target;
    var data = jQuery(form).serialize();
    var statusContainer = jQuery(
      "#salon-my-account-profile-form .statusContainer",
    );
    statusContainer.parent().hide();
    statusContainer.html("");
    data += "&method=UpdateProfile";
    jQuery.ajax({
      url: salon.ajax_url,
      data: data,
      method: "POST",
      dataType: "json",
      success: function (data) {
        statusContainer.parent().show();
        statusContainer.addClass("sln-notifications--active");
        if (data.status === "success") {
          statusContainer.append(
            '<div class="sln-alert alert-success">' +
              salonMyAccount_l10n.success +
              "</div>",
          );
          jQuery(
            "#salon-my-account-profile-form .sln-account--last-update",
          ).html(data.last_update);
          setTimeout(function () {
            statusContainer.removeClass("sln-notifications--active");
          }, 3000);
        } else {
          data.errors.forEach(function (error) {
            statusContainer.append(
              '<div class="sln-alert sln-alert--problem">' + error + "</div>",
            );
          });
        }
      },
      error: function (data) {
        alert("error");
        console.log(data);
      },
    });
  },

  init: function () {
    if (jQuery("#sln-salon-my-account-content").length) {
      this.loadContent();
    } else {
      sln_createRatings(true, "star");
    }
  },
};

function sln_addClassIfNarrow(element, narrowClass) {
  if (element.length > 0) {
    jQuery(window).on("load resize", function () {
      var elementWidth = element.width();
      if (elementWidth < 769) {
        element.addClass(narrowClass);
      } else {
        element.removeClass(narrowClass);
      }
    });
  }
}

function sln_initDatePickersReschedule($, data) {
  $(".sln_datepicker div").each(function () {
    $(this).attr("readonly", "readonly");
    if ($(this).hasClass("started")) {
      return;
    } else {
      $(this)
        .addClass("started")
        .datetimepicker({
          format: $(this).data("format"),
          weekStart: $(this).data("weekstart"),
          minuteStep: 60,
          minView: 2,
          maxView: 4,
          language: $(this).data("locale"),
        })
        .on("changeMonth", function () {
          $("body").trigger("sln_date");
        })
        .on("changeYear", function () {
          $("body").trigger("sln_date");
        })
        .on("hide", function () {
          if ($(this).is(":focus"));
          $(this).trigger("blur");
        });
      $("body").trigger("sln_date");
    }
  });
  var elementExists = document.getElementById("sln-salon");
  if (elementExists) {
    setTimeout(function () {
      $(".datetimepicker.sln-datetimepicker").wrap(
        "<div class='sln-salon-bs-wrap'></div>",
      );
    }, 50);
  }
}

function sln_initTimePickersReschedule($, data) {
  $(".sln_timepicker div").each(function () {
    $(this).attr("readonly", "readonly");
    if ($(this).hasClass("started")) {
      return;
    } else {
      var picker = $(this)
        .addClass("started")
        .datetimepicker({
          format: $(this).data("format"),
          minuteStep: $(this).data("interval"),
          minView: 0,
          maxView: 0,
          startView: 0,
          showMeridian: $(this).data("meridian") ? true : false,
        })
        .on("show", function () {
          $("body").trigger("sln_date");
        })
        .on("place", function () {
          sln_renderAvailableTimeslots($, data);

          $("body").trigger("sln_date");
        })
        .on("changeMinute", function () {
          setTimeout(function () {
            sln_renderAvailableTimeslots($, data, true);

            $("body").trigger("sln_date");

            $(".sln-reschedule-form--save-button").removeClass("disabled");
            $(".sln-notifications")
              .html("")
              .removeClass("sln-notifications--active");
          }, 5);
        })
        .on("hide", function () {
          if ($(this).is(":focus"));
          $(this).blur();
        })

        .data("datetimepicker").picker;
      picker.addClass("timepicker");

      picker
        .find(".datetimepicker-minutes")
        .prepend(
          $(
            '<div class="sln-datetimepicker-minutes-wrapper-table"></div>',
          ).append(picker.find(".datetimepicker-minutes table")),
        );

      sln_renderAvailableTimeslots($, data);
    }
  });
}

jQuery(function () {
  sln_myAccount.init();
  sln_addClassIfNarrow(
    jQuery("#sln-salon-my-account:not(.sln-account)"),
    "mobile-version",
  );
});
