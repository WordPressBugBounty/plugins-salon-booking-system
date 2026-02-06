"use strict";

jQuery(function ($) {
  sln_initSalonCalendarUserSelect2($);
});

function sln_calendar_getHourFunc() {
  return function (hour, part) {
    var time_start = this.options.time_start.split(":");
    var time_split = parseInt(this.options.time_split);
    var h =
      "" + (parseInt(time_start[0]) + hour * Math.max(time_split / 60, 1));
    var m =
      "" +
      (time_split * part + parseInt(hour == 0 ? parseInt(time_start[1]) : 0));
    var d = new Date();
    d.setHours(h);
    d.setMinutes(m);
    return moment(d).format(sln_calendarGetTimeFormat());
  };
}

function sln_calendar_getTimeFunc() {
  return function (part) {
    var time_start = this.options.time_start.split(":");
    var time_split = parseInt(this.options.time_split);
    var h = "" + parseInt(time_start[0]);
    var m = "" + (parseInt(time_start[1]) + time_split * part);
    var d = new Date();
    d.setHours(h);
    d.setMinutes(m);
    return moment(d).format(sln_calendarGetTimeFormat());
  };
}

function sln_calendar_getTransFunc() {
  return function (label) {
    return calendar_translations[label];
  };
}

function sln_calendarGetTimeFormat() {
  // http://momentjs.com/docs/#/displaying/format/
  // vs http://www.malot.fr/bootstrap-datetimepicker/#options
  if (!salon.moment_time_format)
    salon.moment_time_format = salon.time_format
      .replace("ii", "mm")
      .replace("hh", "{|}")
      .replace("H", "h")
      .replace("{|}", "HH")
      .replace("p", "a")
      .replace("P", "A");
  return salon.moment_time_format;
}

function sln_initSalonCalendar(
  $,
  ajaxUrl,
  ajaxDay,
  templatesUrl,
  defaultView,
  firstDay
) {
  var DayCalendarHolydays = {
    createButton: false,
    selection: [],
    blocked: false,
    rules: false,
    assistants_rules: false,
    selecting: false,
    startEl: false,
    mousedown: function (e) {
      if (!$(".sln-calendar-view").hasClass("sln-assistant-mode")) {
        if (!$(e.target).hasClass("cal-day-hour-part")) return;
        DayCalendarHolydays.clearSelection();
        DayCalendarHolydays.selectEl($(this));
      } else {
        if (!$(e.target).hasClass("att-time-slot")) return;
        DayCalendarHolydays.clearSelection();
        DayCalendarHolydays.selectEl($(this));
      }
    },
    bodyBlock: function (e) {
      if (!$(".sln-calendar-view").hasClass("sln-assistant-mode")) {
        var target = $(e.target);
        if (
          !(
            target.hasClass("cal-day-panel") ||
            target.parents("#cal-day-panel").length
          )
        ) {
          DayCalendarHolydays.blocked = true;
          var event = jQuery.Event("click");
          event.target = $("body").find(".cal-day-hour-part:not(.blocked)")[0];
          $("body").trigger(event);
          return false;
        }
      } else {
        var target = $(e.target);
        if (
          !(
            target.hasClass("cal-day-panel") ||
            target.parents("#cal-day-panel").length
          )
        ) {
          DayCalendarHolydays.blocked = true;
          var event = jQuery.Event("click");
          event.target = $("body").find(".att-time-slot:not(.blocked)")[0];
          $("body").trigger(event);
          return false;
        }
      }
    },
    mouseup: function (e) {
      if (!$(".sln-calendar-view").hasClass("sln-assistant-mode")) {
        DayCalendarHolydays.selecting = false;

        var firstEl = DayCalendarHolydays.startEl,
          lastEl = $(e);
        var firstI = firstEl.index(),
          lastI = lastEl.index(),
          selected;
        if (parseInt(firstI) > parseInt(lastI)) {
          var temp = firstEl;
          firstEl = lastEl;
          lastEl = temp;
        }
        selected =
          parseInt(firstI) === parseInt(lastI)
            ? lastEl
            : firstEl.nextUntil(lastEl).add(firstEl).add(lastEl);
        selected.each(function () {
          $(this).addClass("selected");
          DayCalendarHolydays.selection[parseInt($(this).index())] = $(this);
        });

        var button = DayCalendarHolydays.createPopUp(
          1,
          firstEl,
          lastEl,
          DayCalendarHolydays.selection,
        );
        button.on("click", DayCalendarHolydays.blockSelection);
        setTimeout(function () {
          $(" .cal-day-hour-part.selected").on(
            "click",
            DayCalendarHolydays.clearSelection,
          );
        }, 0);
        $(document).on("click", DayCalendarHolydays.clickOutside);
      } else {
        DayCalendarHolydays.selecting = false;

        var firstEl = DayCalendarHolydays.startEl,
          lastEl = $(e);
        var firstI = firstEl.attr("data-index"),
          lastI = lastEl.attr("data-index"),
          selected;
        if (parseInt(firstI) > parseInt(lastI)) {
          var temp = firstEl;
          firstEl = lastEl;
          lastEl = temp;
        }
        selected =
          parseInt(firstI) === parseInt(lastI)
            ? lastEl
            : firstEl.nextUntil(lastEl).add(firstEl).add(lastEl);
        selected.each(function () {
          $(this).addClass("selected");
          DayCalendarHolydays.selection[parseInt($(this).attr("data-index"))] =
            $(this);
        });

        var button = DayCalendarHolydays.createPopUp(
          1,
          firstEl,
          lastEl,
          DayCalendarHolydays.selection,
          false,
          firstEl.find(".sln-btn--cal-day--add").attr("data-att-id"),
          firstEl.find(".sln-btn--cal-day--add").attr("data-pos"),
        );
        button.on("click", DayCalendarHolydays.blockSelection);
        setTimeout(function () {
          $(" .att-time-slot.selected").on(
            "click",
            DayCalendarHolydays.clearSelection,
          );
        }, 0);
        $(document).on("click", DayCalendarHolydays.clickOutside);
      }
    },
    mouseover: function (e) {
      if (!$(".sln-calendar-view").hasClass("sln-assistant-mode")) {
        if (DayCalendarHolydays.blocked) return;
        if ($(this).hasClass("blocked")) {
          DayCalendarHolydays.blocked = true;
          var event = jQuery.Event("click");
          event.target = $("body").find(".cal-day-hour-part:not(.blocked)")[0];
          $("body").trigger(event);
          return false;
        } else DayCalendarHolydays.selectEl($(this));
      } else {
        if (DayCalendarHolydays.blocked) return;
        if ($(this).hasClass("blocked")) {
          DayCalendarHolydays.blocked = true;
          var event = jQuery.Event("click");
          event.target = $("body").find(".att-time-slot:not(.blocked)")[0];
          $("body").trigger(event);
          return false;
        } else DayCalendarHolydays.selectEl($(this));
      }
    },
    selectEl: function ($el) {
      if (!$(".sln-calendar-view").hasClass("sln-assistant-mode")) {
        $el.addClass("selected");
        this.selection[parseInt($el.index())] = $el;
      } else {
        $el.addClass("selected");
        this.selection[parseInt($el.attr("data-index"))] = $el;
      }
    },
    click: function (e) {
      if (!$(".sln-calendar-view").hasClass("sln-assistant-mode")) {
        if (!$(e.target).hasClass("cal-day-hour-part")) return;
        var attr = $(e.target).attr("data-action");
        if (
          $(e.target).hasClass("block_date") ||
          (typeof attr !== typeof undefined && attr !== false)
        )
          return;
        $(".cal-day-hour-part").removeClass("active");
        if (DayCalendarHolydays.selecting) {
          DayCalendarHolydays.mouseup(e.target);
        } else {
          $(e.target).addClass("active");
        }
      } else {
        if (!$(e.target).hasClass("att-time-slot")) return;
        var attr = $(e.target).attr("data-action");
        if (
          $(e.target).hasClass("block_date") ||
          (typeof attr !== typeof undefined && attr !== false)
        )
          return;
        $(".att-time-slot").removeClass("active");
        if (DayCalendarHolydays.selecting) {
          DayCalendarHolydays.mouseup(e.target);
        } else {
          $(e.target).addClass("active");
        }
      }
    },
    startSelection: function (e) {
      if (!$(".sln-calendar-view").hasClass("sln-assistant-mode")) {
        DayCalendarHolydays.clearSelection();
        DayCalendarHolydays.startEl = $(e.target).closest(".cal-day-hour-part");
        DayCalendarHolydays.selectEl($(e.target).closest(".cal-day-hour-part"));
        DayCalendarHolydays.selecting = true;
        $(".cal-day-hour-part").addClass("to-select");
        let $el = $(e.target).siblings(".cal-day-click-tip");
        $(e.target).siblings(".cal-day-click-tip").show();
      } else {
        DayCalendarHolydays.clearSelection();
        DayCalendarHolydays.startEl = $(e.target).closest(".att-time-slot");
        DayCalendarHolydays.selectEl($(e.target).closest(".att-time-slot"));
        DayCalendarHolydays.selecting = true;
        $(".att-time-slot").addClass("to-select");
        let $el = $(e.target).siblings(".cal-day-click-tip");
        $(e.target).siblings(".cal-day-click-tip").show();
      }
    },
    clearSelection: function () {
      if (!$(".sln-calendar-view").hasClass("sln-assistant-mode")) {
        if (DayCalendarHolydays.selection.length) {
          if (
            DayCalendarHolydays.createButton &&
            DayCalendarHolydays.createButton.hasClass("create-holydays")
          )
            DayCalendarHolydays.createButton.remove();
          DayCalendarHolydays.selection.forEach(function (e) {
            e.removeClass("selected");
          });
          DayCalendarHolydays.blocked = false;
        }
        $(".cal-day-hour-part").removeClass("to-select");
        $(".cal-day-click-tip").hide();
        DayCalendarHolydays.startEl = false;
        DayCalendarHolydays.selecting = false;
        DayCalendarHolydays.selection = [];
        $(" .cal-day-hour-part").off(
          "click",
          DayCalendarHolydays.clearSelection,
        );
        $(document).off("click", DayCalendarHolydays.clickOutside);
      } else {
        if (DayCalendarHolydays.selection.length) {
          if (
            DayCalendarHolydays.createButton &&
            DayCalendarHolydays.createButton.hasClass("create-holydays")
          )
            DayCalendarHolydays.createButton.remove();
          DayCalendarHolydays.selection.forEach(function (e) {
            e.removeClass("selected");
          });
          DayCalendarHolydays.blocked = false;
        }
        $(".att-time-slot").removeClass("to-select");
        $(".cal-day-click-tip").hide();
        DayCalendarHolydays.startEl = false;
        DayCalendarHolydays.selecting = false;
        DayCalendarHolydays.selection = [];
        $(" .att-time-slot").off("click", DayCalendarHolydays.clearSelection);
        $(document).off("click", DayCalendarHolydays.clickOutside);
      }
    },
    clickOutside: function (e) {
      if (!$(e.target).closest("#cal-day-panel").length) {
        DayCalendarHolydays.clearSelection();
      }
    },
    createPopUp: function (status, firstEl, lastEl, els, rule, attId, pos) {
      if (!$(".sln-calendar-view").hasClass("sln-assistant-mode")) {
        $(".cal-day-hour-part").removeClass("to-select");
        $(".cal-day-click-tip").hide();

        var firstB = firstEl.find('button[data-action="add-event-by-date"]');
        var lastB = lastEl.find('button[data-action="add-event-by-date"]');
        var firstD = firstB.attr("data-event-date"),
          firstT = firstB.attr("data-event-time"),
          endDay = !lastEl.next().length,
          lastD,
          lastT;
        
        if (endDay) {
          // When locking the last slot of the day, use same date with 24:00 as end time
          lastD = lastB.attr("data-event-date");
          lastT = "24:00";
        } else {
          lastD = lastB.attr("data-event-date");
          var final = lastEl.next().find('button[data-action="add-event-by-date"]');
          lastT = final.attr("data-event-time");
        }
        var single = firstD + firstT === lastD + lastB.attr("data-event-time");

        // Defensive checks: ensure elements exist and are positioned
        if (!$(".day-event-panel-border:last").length) {
          return;
        }

        var calDayPanel = $("#cal-day-panel");
        var firstPos = firstEl.position();
        var lastPos = lastEl.position();

        // Ensure positions are valid
        if (!firstPos || !lastPos || typeof firstPos.top !== 'number' || typeof lastPos.top !== 'number') {
          return;
        }

        var baseTop = single
          ? firstPos.top
          : firstPos.top + (lastPos.top + lastEl.height() - firstPos.top) / 2;

        var top = baseTop;

        var maxTop = calDayPanel.height() - 50;
        if (top > maxTop) {
          top = maxTop;
        }
        var button = $(
          '<button class="calendar-holydays-button sln-btn sln-btn--new sln-btn--textonly sln-icon--new sln-icon--left sln-calendar__row__button ' +
            (status
              ? " sln-icon--new--checkmark create-holydays sln-btn--calendar-view--pill"
              : " sln-icon--new--unlock remove-holydays sln-btn--calendar-view--pill") +
            ' "></button>',
        );
        button.text(
          status
            ? single
              ? holidays_rules_locale.block_confirm
              : holidays_rules_locale.block_confirm
            : single
              ? holidays_rules_locale.unblock_these_rows
              : holidays_rules_locale.unblock_these_rows,
        );
        button.css({
          top: top,
          left: 92,
          position: "absolute",
        });
        if (single) button.addClass("onlyone");
        $(button).insertAfter($(".day-event-panel-border:last"));

        var selection = rule
          ? rule
          : {
              from_date: firstD,
              from_time: firstT,
              to_date: lastD,
              to_time: lastT,
            };
        button.data("selection", selection);
        button.data("els", els);
        this.selection.data = selection;
        this.createButton = button;
        return button;
      } else {
        $(".att-time-slot").removeClass("to-select");
        $(".cal-day-click-tip").hide();

        var firstB = firstEl.find('button[data-action="add-event-by-date"]');
        var lastB = lastEl.find('button[data-action="add-event-by-date"]');
        var firstD = firstB.attr("data-event-date"),
          firstT = firstB.attr("data-event-time"),
          endDay =
            !lastEl.next().length ||
            lastEl.next().attr("data-att-id") !== firstEl.attr("data-att-id"),
          lastD,
          lastT;
        
        if (endDay) {
          // When locking the last slot of the day, use same date with 24:00 as end time
          lastD = lastB.attr("data-event-date");
          lastT = "24:00";
        } else {
          lastD = lastB.attr("data-event-date");
          var final = lastEl.next().find('button[data-action="add-event-by-date"]');
          lastT = final.attr("data-event-time");
        }
        var single = firstD + firstT === lastD + lastB.attr("data-event-time");

        // Defensive checks: ensure elements exist and are positioned
        if (!$(".day-event-panel-border:last").length) {
          return;
        }

        var calDayPanel = $("#cal-day-panel");
        var firstPos = firstEl.position();
        var lastPos = lastEl.position();

        // Ensure positions are valid
        if (!firstPos || !lastPos || typeof firstPos.top !== 'number' || typeof lastPos.top !== 'number') {
          return;
        }

        var baseTop = single
          ? firstPos.top
          : firstPos.top + (lastPos.top + lastEl.height() - firstPos.top) / 2;

        var top = baseTop;

        var maxTop = calDayPanel.height() - 50;
        if (top > maxTop) {
          top = maxTop;
        }
        var button = $(
          '<button class="calendar-holydays-button sln-btn sln-btn--new sln-btn--textonly sln-icon--new sln-icon--left sln-calendar__row__button ' +
            (status
              ? " sln-icon--new--checkmark create-holydays  sln-btn--calendar-view--pill"
              : " sln-icon--new--unlock remove-holydays  sln-btn--calendar-view--pill") +
            ' "></button>',
        );
        button.text(
          status
            ? single
              ? holidays_rules_locale.block_confirm
              : holidays_rules_locale.block_confirm
            : single
              ? holidays_rules_locale.unblock_these_rows
              : holidays_rules_locale.unblock_these_rows,
        );
        button.css({
          top: top,
          left: 92,
          position: "absolute",
          marginLeft: (+pos + 1) * 200 + 10,
        });
        if (single) button.addClass("onlyone");
        $(button).insertAfter($(".day-event-panel-border:last"));

        var selection = rule
          ? rule
          : {
              from_date: firstD,
              from_time: firstT,
              to_date: lastD,
              to_time: lastT,
            };
        button.data("selection", selection);
        button.data("els", els);
        button.data("att-id", attId);
        this.selection.data = selection;
        this.createButton = button;
        return button;
      }
    },
    unblockPop: function (e) {
      var target = $(this);
      DayCalendarHolydays.callAjax(
        "Remove",
        function (data) {
          if (data.rules === undefined) return;
          // Update both local cache and window global to keep in sync
          DayCalendarHolydays.rules = data.rules;
          DayCalendarHolydays.assistants_rules = data.assistants_rules;
          window.daily_rules = data.rules;
          window.daily_assistants_rules = data.assistants_rules;
          var els = target.data().els;
          Object.keys(els).forEach(function (key) {
            $(els[key]).removeClass("blocked");
          });
          target.remove();
          if (
            $(".cal-day-hour-part.blocked").length ||
            $(".att-time-slot.blocked").length
          ) {
            $(".sln-free-locked-slots").removeClass("hide");
          } else {
            $(".sln-free-locked-slots").addClass("hide");
          }
          checkUnlockIconsAndToggleButton();
        },
        target.data().selection,
        target.data().attId,
      );
    },
    blockSelection: function () {
      DayCalendarHolydays.callAjax(
        "Add",
        function (data) {
          if (data.rules === undefined) {
            if (data.rules !== errors)
              console.log(data.errors, DayCalendarHolydays.selection.data);
            DayCalendarHolydays.selection.forEach(function (e) {
              e.removeClass("selected");
            });
            DayCalendarHolydays.selection = [];
            DayCalendarHolydays.createButton.remove();
            DayCalendarHolydays.createButton = false;
            return;
          }
          // Update both local cache and window global to keep in sync
          DayCalendarHolydays.rules = data.rules;
          DayCalendarHolydays.assistants_rules = data.assistants_rules;
          window.daily_rules = data.rules;
          window.daily_assistants_rules = data.assistants_rules;
          
          // Capture current state BEFORE async operations
          var capturedRules = data.rules;
          
          DayCalendarHolydays.selection.forEach(function (e) {
            e.addClass("blocked").removeClass("selected");
          });
          var button = DayCalendarHolydays.createButton;
          var buttonSelection = button.data('selection');
          var lockedDay = buttonSelection ? buttonSelection.from_date : null;
          DayCalendarHolydays.createButton = false;
          button
            .toggleClass("create-holydays remove-holydays")
            .toggleClass("sln-icon--new--checkmark sln-icon--new--unlock")
            .text(holidays_rules_locale.unblock_these_rows)
            .off("click")
            .on("click", DayCalendarHolydays.unblockPop);
          $(".cal-day-hour-part, .att-time-slot").removeClass("to-select");
          if (
            $(".cal-day-hour-part.blocked").length ||
            $(".att-time-slot.blocked").length
          ) {
            $(".sln-free-locked-slots").removeClass("hide");
          } else {
            $(".sln-free-locked-slots").addClass("hide");
          }
          // Immediately check after transformation
          checkUnlockIconsAndToggleButton();
          // Double requestAnimationFrame ensures layout is complete before positioning buttons
          requestAnimationFrame(function() {
            requestAnimationFrame(function() {
              // Restore rules from closure in case they were cleared
              window.daily_rules = capturedRules;
              DayCalendarHolydays.rules = capturedRules;
              
              if (lockedDay) {
                DayCalendarHolydays.showRules({
                  options: { day: lockedDay },
                });
              }
              // Check unlock icons AFTER showRules recreates buttons
              checkUnlockIconsAndToggleButton();
            });
          });
        },
        false,
        DayCalendarHolydays.createButton.data().attId,
      );
    },
    callAjax: function (action, cb, target, attId) {
      var data = {
        action: "salon",
        method: action + "HolydayRule",
        rule: target ? target : DayCalendarHolydays.selection.data,
        attendant_id: attId,
      };

      data = Object.assign({}, window.dayCalendarHolydaysAjaxData, data);

      jQuery.ajax({
        url: ajaxurl,
        type: "POST",
        data: data,
        cache: false,
        dataType: "json",
        success: cb,
      });
    },
    showRules: function (calendar) {
      $(".calendar-holydays-button").remove();
      if (!$(".sln-calendar-view").hasClass("sln-assistant-mode")) {
        var p_rules = window.daily_rules;
        // Always refresh rules from window.daily_rules to pick up changes from AJAX
        DayCalendarHolydays.rules = Object.keys(p_rules).map(function (key) {
          return p_rules[key];
        });

        var rules = DayCalendarHolydays.rules.filter(function (e) {
          return !!e && e.from_date === calendar.options.day;
        });
        rules.forEach(function (rule) {
          if (rule.from_time === "") rule.from_time = "9:00";
          var endTomorrow = rule.to_date !== calendar.options.day;
          var firstEl = $('button[data-event-time="' + rule.from_time + '"]'),
            lastEl = $('button[data-event-time="' + rule.to_time + '"]');
          if (!firstEl.length) firstEl = $("button[data-event-time]").first();
          if (endTomorrow || !lastEl.length)
            lastEl = $("button[data-event-time]").last();
          firstEl = firstEl.parent().parent();
          lastEl = lastEl.parent().parent();
          if (firstEl.index() > lastEl.index()) {
            var temp = firstEl;
            firstEl = lastEl;
            lastEl = temp;
          }
          var els = firstEl.add(firstEl.nextUntil(lastEl));
          if (endTomorrow) {
            els = els.add(lastEl);
          }
          els.addClass("blocked");
          if (
            (firstEl.hasClass("blocked") ||
              firstEl.find(".att-time-slot.blocked").length) &&
            firstEl.length &&
            firstEl.position()
          ) {
            var button = DayCalendarHolydays.createPopUp(
              0,
              firstEl,
              lastEl,
              els,
              rule,
            );
            if (button) {
              button.off("click").on("click", DayCalendarHolydays.unblockPop);
            }
          }
        });
      } else {
        var p_rules = window.daily_assistants_rules;
        // Always refresh assistant rules from window.daily_assistants_rules to pick up changes from AJAX
        DayCalendarHolydays.assistants_rules = {};
        $.each(p_rules, function (attId, rules) {
          DayCalendarHolydays.assistants_rules[attId] = rules;
        });

        var assistants_rules = {};

        $.each(DayCalendarHolydays.assistants_rules, function (attId, rules) {
          rules = rules.filter(function (e) {
            if (e.from_date === calendar.options.day) {
              return !!e && e.from_date === calendar.options.day;
            } else {
              return !!e && e.to_date === calendar.options.day;
            }
          });
          assistants_rules[attId] = rules;
        });
        $.each(assistants_rules, function (attId, rules) {
          rules.forEach(function (rule) {
            if (rule.from_time === "") rule.from_time = "9:00";
            var endTomorrow = rule.to_date !== calendar.options.day;
            var firstEl = $(
                '.att-time-slot button[data-event-time="' +
                  rule.from_time +
                  '"][data-att-id="' +
                  attId +
                  '"]',
              ),
              lastEl = $(
                '.att-time-slot button[data-event-time="' +
                  rule.to_time +
                  '"][data-att-id="' +
                  attId +
                  '"]',
              );
            if (!firstEl.length)
              firstEl = $(
                ".att-time-slot button[data-event-time][data-att-id=" +
                  attId +
                  "]",
              ).first();
            if (endTomorrow || !lastEl.length)
              lastEl = $(
                ".att-time-slot button[data-event-time][data-att-id=" +
                  attId +
                  "]",
              ).last();
            firstEl = firstEl.parent().parent();
            lastEl = lastEl.parent().parent();
            if (+firstEl.attr("data-index") > +lastEl.attr("data-index")) {
              var temp = firstEl;
              firstEl = lastEl;
              lastEl = temp;
            }
            var els =
              +firstEl.attr("data-index") === +lastEl.attr("data-index")
                ? firstEl
                : firstEl.add(firstEl.nextUntil(lastEl));
            if (endTomorrow) {
              els = els.add(lastEl);
            }
            els.addClass("blocked");
            var button = DayCalendarHolydays.createPopUp(
              0,
              firstEl,
              lastEl,
              els,
              rule,
              attId,
              firstEl.find("button[data-event-time]").attr("data-pos"),
            );
            button.off("click").on("click", DayCalendarHolydays.unblockPop);
          });
        });
      }
      if (
        $(".cal-day-hour-part.blocked").length ||
        $(".att-time-slot.blocked").length
      ) {
        $(".sln-free-locked-slots").removeClass("hide");
      } else {
        $(".sln-free-locked-slots").addClass("hide");
      }
      checkUnlockIconsAndToggleButton();
    },
  };

  var options = {
    time_start: $("#calendar").data("timestart"),
    time_end: $("#calendar").data("timeend"),
    time_split: $("#calendar").data("timesplit"),
    first_day: firstDay,
    events_source: ajaxUrl,
    view: defaultView,
    tmpl_path: templatesUrl,
    tmpl_cache: false,
    format12: true,
    day: ajaxDay,
    onAfterEventsLoad: function (events) {
      if (!events) {
        return;
      }
      var list = $("#eventlist");
      list.html("");
      $.each(events, function (key, val) {
        $(document.createElement("li")).html(val.event_html).appendTo(list);
      });
    },
    onAfterViewLoad: function (view) {
      $(".current-view--title").text(this.getTitle());
      $(".btn-group button").removeClass("active");

      $(".sln-calendar--wrapper").removeClass(
        "sln-calendar--wrapper--day sln-calendar--wrapper--week sln-calendar--wrapper--month sln-calendar--wrapper--year",
      );
      $(".sln-calendar--wrapper").addClass("sln-calendar--wrapper--" + view);
      $('button[data-calendar-view="' + view + '"]').addClass("active");
      function today() {
        var today = new Date();
        var dd = today.getDate();
        var mm = today.getMonth() + 1; //January is 0!
        var yyyy = today.getFullYear();

        if (dd < 10) {
          dd = "0" + dd;
        }

        if (mm < 10) {
          mm = "0" + mm;
        }

        today = yyyy + "-" + mm + "-" + dd;
        return today;
      }
      var today = formatted_to_date(today());
      function formatted_to_date(fdate) {
        var parts = fdate.split("-");
        return new Date(parts[0], parts[1] - 1, parts[2]);
      }

      if (view === "day") {
        var self = this;
        // Double requestAnimationFrame ensures layout is complete before positioning buttons
        requestAnimationFrame(function() {
          requestAnimationFrame(function() {
            DayCalendarHolydays.showRules(self);
            // Check unlock icons AFTER showRules recreates buttons
            checkUnlockIconsAndToggleButton();
          });
        });
      }
    },
    classes: {
      months: {
        general: "label",
      },
    },
    cal_day_pagination:
      '<button type="button" class="btn %class" data-page="%page"></button>',
    on_page: dayCalendarColumns,
    _page: 0,
    language: window.salon_calendar.locale,
  };
  sln_initDatepickers($);
  // CALENDAR
  $(document).on("click", ".cal-month-day.cal-day-inmonth span", function (e) {
    e.preventDefault();
    $(".tooltip").hide();
  });

  var calendar = $("#calendar").calendar(options);
  
  // Make calendar globally accessible for navigation
  window.sln_calendar = calendar;

  $(document).on("keyup", "#sln-calendar-booking-search", function (e) {
    var code = e.which;
    if (code == 13) e.preventDefault();
    if (code == 32 || code == 13 || code == 188 || code == 186) {
      sln_search_bookings.call(this);
    }
  });

  $(document).on("input", "#sln-calendar-booking-search", sln_search_bookings);
  $(document).on("click", ".sln-calendar-booking-search-icon", function (e) {
    var input = $(this).parent().find("#sln-calendar-booking-search");
    if (input.length) sln_search_bookings.call(input.get());
  });
  function sln_search_bookings(e) {
    clearTimeout(this.delay);
    if (this.xhr) this.xhr.abort();
    this.delay = setTimeout(
      function () {
        var el = this;
        var search = $(el).val().trim();
        var canContinue = search.length > 2 || /^\d+$/.test(search);
        if (!canContinue) {
          return;
        }
        $("#search-results-list")
          .html(
            '<div class="sln-loader-wrapper"><div class="sln-loader"><span>Loading...</span></div></div>',
          )
          .addClass("opened");
        var data = {
          search: search,
          day: calendar.options.day,
          action: "salon",
          method: "SearchBookings",
        };
        this.xhr = $.ajax({
          url: salon.ajax_url,
          type: "POST",
          data: data,
          success: function (data) {
            $("#search-results-list").html("").append(data);
            calendar._update_day_prepare_sln_booking_editor();
          },
          error: function () {
            $("#search-results-list").removeClass("opened").html("");
          },
        });
      }.bind(this),
      500,
    );
  }
  function calendarTransition(e) {
    $(".sln-box.sln-calendar-view .sln-viewloading").removeClass(
      "sln-viewloading--inactive",
    );
  }
  // Select the target node.
  var target = document.getElementById("calendar");

  // Create an observer instance.
  var observer = new MutationObserver(function (mutations) {
    setTimeout(function () {
      $(".sln-box.sln-calendar-view .sln-viewloading").addClass(
        "sln-viewloading--inactive",
      );
      
      // After DOM mutations complete and view is loaded, show holiday rules for day view
      if (calendar.options.view === "day") {
        DayCalendarHolydays.showRules(calendar);
        // Check unlock icons AFTER showRules recreates buttons
        checkUnlockIconsAndToggleButton();
      }
    }, 100);
  });

  // Pass in the target node, as well as the observer options.
  observer.observe(target, {
    attributes: true,
    childList: true,
    characterData: true,
  });
  $("body").on("click", function (e) {
    var list = $("#search-results-list.opened");
    if (
      list.length &&
      !$(e.target).hasClass("search-result-link") &&
      !$(e.target).closest("#search-results-list,#sln-calendar-booking-search")
        .length
    )
      list.removeClass("opened");
  });

  $(".btn-group button[data-calendar-nav]").each(function () {
    var $this = $(this);
    $this.on("click", function () {
      calendarTransition();
      setTimeout(function () {
        if (
          !$(".sln-box.sln-calendar-view .sln-viewloading").hasClass(
            "sln-viewloading--inactive",
          )
        ) {
          var navAction = $this.data("calendar-nav");
          
          // When clicking Today button, switch to day view and navigate to today
          if (navAction === "today") {
            calendar.navigate("today");
            if (calendar.options.view !== "day") {
              calendar.view("day");
            }
          } else {
            calendar.navigate(navAction);
          }
        }
      }, 100);
    });
  });

  $(".btn-group button[data-calendar-view]").each(function () {
    var $this = $(this);
    $this.on("click", function () {
      calendarTransition();
      setTimeout(function () {
        if (
          !$(".sln-box.sln-calendar-view .sln-viewloading").hasClass(
            "sln-viewloading--inactive",
          )
        ) {
          calendar.view($this.data("calendar-view"));
        }
      }, 100);
    });
  });

  $("#sln-calendar-user-field").on("change", function () {
    calendar.options._customer = parseInt($(this).val());
    let loadEvent = calendar._loadEvents();
    loadEvent.then(function () {
      calendar.options.onAfterViewLoad.call(calendar, calendar.options.view);
    });
  });
  $("#sln-calendar-services-field").on("change", function () {
    var _events = $(this).val();
    if (Array.isArray(_events)) {
      _events = _events.map(parseInt);
    } else {
      _events = [];
    }

    calendar.options._services = _events;
    let loadEvent = calendar._loadEvents();
    loadEvent.then(function () {
      calendar.options.onAfterViewLoad.call(calendar, calendar.options.view);
    });
  });

  $("#sln-calendar-assistants-mode-switch")
    .on("change", function () {
      calendar.options._assistants_mode = $(this).is(":checked");
      $(".sln-calendar-view").toggleClass(
        "sln-assistant-mode",
        calendar.options._assistants_mode,
      );
      let loadEvent = calendar._loadEvents();
      loadEvent.then(function () {
        calendar.options.onAfterViewLoad.call(calendar, calendar.options.view);
      });
    })
    .trigger("change");

  $(document).on("keydown", function (e) {
    if (e.keyCode === 37) {
      //left
      //scroll calendar to the left
      $(".cal-day-panel__wrapper").scrollLeft(
        $(".cal-day-panel__wrapper").scrollLeft() - 20,
      );
    }
    if (e.keyCode === 39) {
      //right
      //scroll calendar to the right
      $(".cal-day-panel__wrapper").scrollLeft(
        $(".cal-day-panel__wrapper").scrollLeft() + 20,
      );
    }
  });

  // calendar.setLanguage(window.salon_calendar.locale);
  //calendar.view();

  $("body").on(
    "click",
    " .cal-day-hour-part:not(.blocked), .att-time-slot:not(.blocked)",
    DayCalendarHolydays.click,
  );
  $("body").on("click", " .block_date", DayCalendarHolydays.startSelection);

  $('.sln-btn[data-calendar-view="day"] button[data-calendar-nav]').on(
    "click",
    DayCalendarHolydays.clearSelection,
  );

  $(".sln-free-locked-slots").on("click", function () {
    let self = this;
    $.ajax({
      url:
        salon.ajax_url +
        "&action=salon&method=RemoveDailyHolydays&date=" +
        calendar.options.day +
        "&_assistants_mode=" +
        calendar.options._assistants_mode,
      type: "POST",
      success: function (data) {
        // Update both local cache and window global to keep in sync
        DayCalendarHolydays.rules = data.rules;
        DayCalendarHolydays.assistants_rules = data.assistants_rules;
        window.daily_rules = data.rules;
        window.daily_assistants_rules = data.assistants_rules;

        let holidayButtons = $(".calendar-holydays-button");
        holidayButtons.each(function () {
          let button = $(this);
          let els = button.data("els");
          if (els) {
            Object.keys(els).forEach(function (key) {
              $(els[key]).removeClass("blocked");
            });
          }
        });
        holidayButtons.remove();

        $(self).addClass("hide");
      },
    });
  });
}
const parent = document.getElementById("calendar");
const child = document.getElementById("sln-viewloading");
const clickElement = document.querySelectorAll("#calendar .cal-cell1 small");
const testElement = document.querySelector(".sln-help-button__block button");
//$('.sln-box.sln-calendar-view .sln-viewloading').removeClass('sln-viewloading--inactive');

//parent.addEventListener('click',function(event){
//    console.log('Click Detected in parent on ' + event.target);
//    //if(event.target === clickElement) {
//    if ( event.target.matches('#calendar .cal-cell1 small') ) {
//        event.stopPropagation();
//        child.classList.remove("sln-viewloading--inactive");
//        setTimeout(() => {
//            event.target.click();
//            console.log("Delayed for 1 second.");
//        }, 1000);
//    }
//    event.preventDefault();
//},true);
/*
for (let i = 0; i < clickElement.length; i++) {
    clickElement[i].addEventListener('click',function(event){
        console.log("Delayed for 1 second.");
    },true);
 }
 */
/*
const cbox = document.getElementById('calendar');
document.querySelectorAll(".cal-cell1 span").forEach(box =>
  box.addEventListener("click", () => box.classList.add("red"))
)
parent.addEventListener("click", (event) => {
    console.log(event.target.tagName);
    parent.classList.add("sln-viewloading--inactive");
  if (event.target.tagName === "SPAN") {
    console.log("event.target.tagName");
  }
},true);
*/
  function sln_initSalonCalendarUserSelect2($) {
  $("#sln-calendar-user-field").select2({
    allowClear: true,
    containerCssClass: "sln-select-rendered",
    dropdownCssClass: "sln-select-dropdown",
    theme: "sln",
    width: "100%",
    placeholder: $("#sln-calendar-user-field").data("placeholder"),
    language: {
      noResults: function () {
        return $("#sln-calendar-user-field").data("nomatches");
      },
    },
    ajax: {
      url:
        salon.ajax_url +
        "&action=salon&method=SearchUser&security=" +
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
        return {
          results: data.result,
        };
      },
    },
  });
}

function checkUnlockIconsAndToggleButton() {
  const unlockIcons = document.querySelectorAll(
    "#cal-day-panel .sln-icon--new--unlock, .cal-day-panel__wrapper .sln-icon--new--unlock",
  );
  const freeLockedSlotsButton = document.querySelector(
    ".sln-free-locked-slots",
  );
  if (unlockIcons.length === 0 && freeLockedSlotsButton) {
    freeLockedSlotsButton.classList.add("hide");
  } else if (unlockIcons.length > 0 && freeLockedSlotsButton) {
    freeLockedSlotsButton.classList.remove("hide");
  }
}

/**
 * Format time difference as relative time (e.g., "in 2 hours")
 * @param {string} dateTimeStr - DateTime string in format 'Y-m-d H:i:s'
 * @returns {string} Formatted relative time
 */
function formatRelativeTime(dateTimeStr) {
  const bookingTime = new Date(dateTimeStr.replace(' ', 'T'));
  const now = new Date();
  const diffMs = bookingTime - now;
  const diffMins = Math.round(diffMs / 60000);
  
  if (diffMins < 0) {
    return 'Now';
  } else if (diffMins < 60) {
    return 'in ' + diffMins + ' min' + (diffMins !== 1 ? 's' : '');
  } else if (diffMins < 1440) {
    const hours = Math.floor(diffMins / 60);
    return 'in ' + hours + ' hour' + (hours !== 1 ? 's' : '');
  } else {
    const days = Math.floor(diffMins / 1440);
    return 'in ' + days + ' day' + (days !== 1 ? 's' : '');
  }
}

/**
 * Update the Today button to show the count of upcoming bookings
 * @param {number} count - Number of upcoming bookings for today
 * @param {Array} bookings - Array of upcoming booking objects
 */
function updateTodayButton(count, bookings, isFreeVersion) {
  // Handle case where bookings is not passed (for backward compatibility)
  if (typeof bookings === 'undefined' || bookings === null) {
    bookings = [];
  }
  if (typeof isFreeVersion === 'undefined') {
    isFreeVersion = false;
  }
  
  var todayButton = document.querySelector('button[data-calendar-nav="today"]');
  if (!todayButton) {
    return;
  }
  
  // Check if count has changed for pulse animation
  var existingBadge = todayButton.querySelector('.sln-today-badge');
  var oldCount = existingBadge ? parseInt(existingBadge.textContent, 10) : -1;
  var shouldPulse = (oldCount !== -1) && (oldCount !== count) && (count > 0);
  
  // Remove existing badge and tooltip if any
  if (existingBadge) {
    existingBadge.remove();
  }
  var existingTooltip = document.querySelector('.sln-today-tooltip');
  if (existingTooltip) {
    existingTooltip.remove();
  }
  
  // Always show badge, even when count is 0
  var badge = document.createElement('span');
    badge.className = 'sln-today-badge';
    badge.textContent = count;
  
  // Add pulse animation if count changed
  if (shouldPulse) {
    badge.classList.add('sln-today-badge--pulse');
    // Remove pulse class after animation completes
    setTimeout(function() {
      badge.classList.remove('sln-today-badge--pulse');
    }, 600);
  }
  
    todayButton.appendChild(badge);
  
  // Create tooltip if there are bookings OR if it's FREE version with count > 0
  if ((bookings && bookings.length > 0) || (isFreeVersion && count > 0)) {
    
    var tooltip = document.createElement('div');
    tooltip.className = 'sln-today-tooltip';
    if (isFreeVersion) {
      tooltip.className += ' sln-today-tooltip--free';
    }
    tooltip.style.display = 'none';
    
    // Only show title for PRO version
    if (!isFreeVersion) {
      var tooltipTitle = document.createElement('div');
      tooltipTitle.className = 'sln-today-tooltip__title';
      tooltipTitle.textContent = 'Upcoming Reservations';
      tooltip.appendChild(tooltipTitle);
    }
    
    var bookingsList = document.createElement('ul');
    bookingsList.className = 'sln-today-tooltip__list';
    
    // If FREE version, use fake bookings data
    var displayBookings = bookings;
    if (isFreeVersion && count > 0) {
      displayBookings = [
        { firstName: 'Michael', lastName: 'Jackson', startsAt: new Date().toISOString(), id: 0 },
        { firstName: 'Frank', lastName: 'Sinatra', startsAt: new Date().toISOString(), id: 0 },
        { firstName: 'Curt', lastName: 'Kobain', startsAt: new Date().toISOString(), id: 0 }
      ];
    }
    
    displayBookings.forEach(function(booking) {
      var listItem = document.createElement('li');
      listItem.className = 'sln-today-tooltip__item';
      
      var customerInfoWrapper = document.createElement('div');
      customerInfoWrapper.className = 'sln-today-tooltip__customer-wrapper';
      
      var customerName = document.createElement('div');
      customerName.className = 'sln-today-tooltip__customer';
      customerName.textContent = booking.firstName + ' ' + booking.lastName;
      customerInfoWrapper.appendChild(customerName);
      
      // Add shop name if available
      if (booking.shopName && booking.shopName.trim() !== '') {
        var shopName = document.createElement('div');
        shopName.className = 'sln-today-tooltip__shop';
        shopName.textContent = booking.shopName;
        shopName.style.color = '#666';
        shopName.style.fontSize = '12px';
        shopName.style.marginTop = '2px';
        customerInfoWrapper.appendChild(shopName);
      }
      
      var bookingTime = document.createElement('div');
      bookingTime.className = 'sln-today-tooltip__time';
      
      // Add clock icon
      var clockIcon = document.createElement('i');
      clockIcon.className = 'sln-icon sln-icon--clock';
      bookingTime.appendChild(clockIcon);
      
      // Add time text
      var timeText = document.createTextNode(' ' + formatRelativeTime(booking.startsAt));
      bookingTime.appendChild(timeText);
      
      var viewButton = document.createElement('a');
      viewButton.href = '';
      viewButton.className = 'sln-today-tooltip__button';
      if (!isFreeVersion) {
        viewButton.className += ' sln-edit-icon-tooltip';
      }
      viewButton.textContent = 'Open';
      viewButton.setAttribute('data-bookingid', booking.id);
      viewButton.setAttribute('aria-label', 'Open booking');
      
      // Add click handler only for PRO version
      if (!isFreeVersion) {
        viewButton.addEventListener('click', function() {
          tooltip.style.display = 'none';
        });
      }
      
      listItem.appendChild(customerInfoWrapper);
      listItem.appendChild(bookingTime);
      listItem.appendChild(viewButton);
      bookingsList.appendChild(listItem);
    });
    
    tooltip.appendChild(bookingsList);
    
    // Add CTA banner for FREE version - triggers EXISTING dialog
    if (isFreeVersion) {
      // Create simple clickable banner
      var ctaBanner = document.createElement('div');
      ctaBanner.className = 'sln-today-tooltip__cta';
      
      var ctaText = document.createElement('div');
      ctaText.className = 'sln-today-tooltip__cta-text';
      
      var ctaLine1 = document.createElement('div');
      ctaLine1.textContent = 'Unlock this feature';
      ctaText.appendChild(ctaLine1);
      
      var ctaLine2 = document.createElement('div');
      ctaLine2.className = 'sln-today-tooltip__cta-text--strong';
      ctaLine2.textContent = 'Switch to PRO';
      ctaText.appendChild(ctaLine2);
      
      // Create crown icon using your uploaded PNG image
      var crownImg = document.createElement('img');
      // Get the plugin directory URL from this script's location
      var pluginUrl = '';
      var scripts = document.getElementsByTagName('script');
      for (var i = 0; i < scripts.length; i++) {
        var src = scripts[i].src || '';
        if (src.indexOf('calendar.js') !== -1 || src.indexOf('customCalendar.js') !== -1) {
          var jsIndex = src.lastIndexOf('/js/');
          if (jsIndex !== -1) {
            pluginUrl = src.substring(0, jsIndex);
            break;
          }
        }
      }
      crownImg.src = pluginUrl + '/img/crown-pro-icon.png';
      crownImg.className = 'sln-today-tooltip__crown-icon';
      crownImg.alt = 'PRO';
      crownImg.width = 32;
      crownImg.height = 32;
      
      ctaBanner.appendChild(ctaText);
      ctaBanner.appendChild(crownImg);
      
      // Add click handler to close tooltip and trigger EXISTING dialog
      ctaBanner.style.cursor = 'pointer';
      ctaBanner.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Hide the upcoming reservations tooltip
        tooltip.style.display = 'none';
        
        // Find and trigger the EXISTING PRO feature dialog from booking status summary
        var existingOpenButton = document.querySelector('#sln-booking-status-summary .sln-profeature__open-button');
        if (existingOpenButton) {
          existingOpenButton.click();
        }
      });
      
      tooltip.appendChild(ctaBanner);
    }
    
    document.body.appendChild(tooltip);
    
    // Show/hide tooltip on hover
    var hideTooltipTimeout;
    
    badge.addEventListener('mouseenter', function(e) {
      clearTimeout(hideTooltipTimeout);
      var badgeRect = badge.getBoundingClientRect();
      tooltip.style.display = 'block';
      tooltip.style.top = (badgeRect.bottom + 10) + 'px';
      tooltip.style.left = (badgeRect.left + badgeRect.width / 2) + 'px';
      tooltip.style.transform = 'translateX(-50%)';
    });
    
    badge.addEventListener('mouseleave', function(e) {
      // Delay hiding to allow moving to tooltip
      hideTooltipTimeout = setTimeout(function() {
        if (!tooltip.matches(':hover')) {
          tooltip.style.display = 'none';
        }
      }, 200);
    });
    
    tooltip.addEventListener('mouseenter', function() {
      clearTimeout(hideTooltipTimeout);
    });
    
    tooltip.addEventListener('mouseleave', function() {
      tooltip.style.display = 'none';
    });
  }
}

/**
 * Initialize a single calbar tooltip
 * Extracted function to allow re-initialization after toggle
 */
function initSingleCalbarTooltip(calbar, bookingDate, dataScript, isFreeVersion) {
  var bookings;
  try {
    bookings = JSON.parse(dataScript.textContent);
  } catch (e) {
    return;
  }
    
    if (!bookings || bookings.length === 0) {
      return;
    }
    
    // Create tooltip (reuse same structure as updateTodayButton)
    var tooltip = document.createElement('div');
    tooltip.className = 'sln-today-tooltip sln-calbar-tooltip';
    tooltip.setAttribute('data-date', bookingDate); // Add data attribute for easy identification
    if (isFreeVersion) {
      tooltip.className += ' sln-today-tooltip--free';
    }
    tooltip.style.display = 'none';
    
    var tooltipTitle = document.createElement('div');
    tooltipTitle.className = 'sln-today-tooltip__title';
    tooltipTitle.textContent = formatDateHeader(bookingDate) + ' - ' + bookings.length + (bookings.length === 1 ? ' Booking' : ' Bookings');
    tooltip.appendChild(tooltipTitle);
    
    var bookingsList = document.createElement('ul');
    bookingsList.className = 'sln-today-tooltip__list';
    
    // Show all bookings (including cancelled) - limited to 10
    var displayBookings = bookings.slice(0, 10);
    var hasMoreBookings = bookings.length > 10;
    
    displayBookings.forEach(function(booking) {
      var listItem = document.createElement('li');
      listItem.className = 'sln-today-tooltip__item';
      
      var customerInfoWrapper = document.createElement('div');
      customerInfoWrapper.className = 'sln-today-tooltip__customer-wrapper';
      
      var customerName = document.createElement('div');
      customerName.className = 'sln-today-tooltip__customer';
      customerName.textContent = booking.firstName + ' ' + booking.lastName;
      customerInfoWrapper.appendChild(customerName);
      
      // Add shop name if available
      if (booking.shopName && booking.shopName.trim() !== '') {
        var shopName = document.createElement('div');
        shopName.className = 'sln-today-tooltip__shop';
        shopName.textContent = booking.shopName;
        shopName.style.color = '#666';
        shopName.style.fontSize = '12px';
        shopName.style.marginTop = '2px';
        customerInfoWrapper.appendChild(shopName);
      }
      
      var bookingTime = document.createElement('div');
      bookingTime.className = 'sln-today-tooltip__time';
      
      // Add clock icon
      var clockIcon = document.createElement('i');
      clockIcon.className = 'sln-icon sln-icon--clock';
      bookingTime.appendChild(clockIcon);
      
      // Add time text
      var timeText = document.createTextNode(' ' + formatTime(booking.startsAt));
      bookingTime.appendChild(timeText);
      
      // Add status indicator (colored dot + status text)
      var statusWrapper = document.createElement('div');
      statusWrapper.className = 'sln-today-tooltip__status';
      
      var statusDot = document.createElement('span');
      statusDot.className = 'sln-today-tooltip__status-dot';
      
      var statusText = document.createElement('span');
      statusText.className = 'sln-today-tooltip__status-text';
      
      // Determine status and color based on booking status
      var status = booking.status || '';
      
      // Map booking statuses to display text and colors
      if (status === 'canceled' || status === 'sln-b-canceled') {
        // Cancelled
        statusDot.className += ' sln-today-tooltip__status-dot--cancelled';
        statusText.textContent = 'Cancelled';
        statusText.style.color = '#dc3545'; // Red
      } else if (status === 'sln-b-paid') {
        // Paid (PRO only)
        statusDot.className += ' sln-today-tooltip__status-dot--paid';
        statusText.textContent = 'Paid';
        statusText.style.color = '#28a745'; // Green
      } else if (status === 'sln-b-confirmed') {
        // Confirmed
        statusDot.className += ' sln-today-tooltip__status-dot--confirmed';
        statusText.textContent = 'Confirmed';
        statusText.style.color = '#28a745'; // Green
      } else if (status === 'sln-b-pending' || status === 'sln-b-pendingpayment') {
        // Pending / Pending Payment
        statusDot.className += ' sln-today-tooltip__status-dot--pending';
        statusText.textContent = status === 'sln-b-pendingpayment' ? 'Pending Payment' : 'Pending';
        statusText.style.color = '#ff9800'; // Orange
      } else if (status === 'sln-b-paylater') {
        // Pay Later (PRO only)
        statusDot.className += ' sln-today-tooltip__status-dot--paylater';
        statusText.textContent = 'Pay Later';
        statusText.style.color = '#ff9800'; // Orange
      } else {
        // Default fallback (should not happen)
        statusDot.className += ' sln-today-tooltip__status-dot--confirmed';
        statusText.textContent = 'Confirmed';
        statusText.style.color = '#28a745'; // Green
      }
      
      statusWrapper.appendChild(statusDot);
      statusWrapper.appendChild(statusText);
      
      var viewButton = document.createElement('a');
      viewButton.href = '';
      viewButton.className = 'sln-today-tooltip__button';
      if (!isFreeVersion) {
        viewButton.className += ' sln-edit-icon-tooltip';
      }
      viewButton.textContent = 'Open';
      viewButton.setAttribute('data-bookingid', booking.id);
      viewButton.setAttribute('aria-label', 'Open booking');
      
      // Add click handler only for PRO version
      if (!isFreeVersion) {
        viewButton.addEventListener('click', function() {
          tooltip.style.display = 'none';
        });
      }
      
      listItem.appendChild(customerInfoWrapper);
      listItem.appendChild(bookingTime);
      listItem.appendChild(statusWrapper);
      listItem.appendChild(viewButton);
      bookingsList.appendChild(listItem);
    });
    
    tooltip.appendChild(bookingsList);
    
    // Add "see all bookings" link if more than 10 bookings
    if (hasMoreBookings) {
      var seeAllLink = document.createElement('a');
      seeAllLink.href = '#';
      seeAllLink.className = 'sln-today-tooltip__see-all';
      seeAllLink.textContent = 'Click here to see all bookings';
      seeAllLink.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Hide tooltip
        tooltip.style.display = 'none';
        
        // Navigate to daily view for this date
        if (window.sln_calendar) {
          window.sln_calendar.options.day = bookingDate;
          window.sln_calendar.view('day');
        }
      });
      tooltip.appendChild(seeAllLink);
    }
    
    // Add CTA banner for FREE version - triggers EXISTING dialog
    if (isFreeVersion) {
      // Create simple clickable banner
      var ctaBanner = document.createElement('div');
      ctaBanner.className = 'sln-today-tooltip__cta';
      
      var ctaText = document.createElement('div');
      ctaText.className = 'sln-today-tooltip__cta-text';
      
      var ctaLine1 = document.createElement('div');
      ctaLine1.textContent = 'Unlock this feature';
      ctaText.appendChild(ctaLine1);
      
      var ctaLine2 = document.createElement('div');
      ctaLine2.className = 'sln-today-tooltip__cta-text--strong';
      ctaLine2.textContent = 'Switch to PRO';
      ctaText.appendChild(ctaLine2);
      
      // Create crown icon using your uploaded PNG image
      var crownImg = document.createElement('img');
      var pluginUrl = '';
      var scripts = document.getElementsByTagName('script');
      for (var i = 0; i < scripts.length; i++) {
        var src = scripts[i].src || '';
        if (src.indexOf('calendar.js') !== -1 || src.indexOf('customCalendar.js') !== -1) {
          var jsIndex = src.lastIndexOf('/js/');
          if (jsIndex !== -1) {
            pluginUrl = src.substring(0, jsIndex);
            break;
          }
        }
      }
      crownImg.src = pluginUrl + '/img/crown-pro-icon.png';
      crownImg.className = 'sln-today-tooltip__crown-icon';
      crownImg.alt = 'PRO';
      crownImg.width = 32;
      crownImg.height = 32;
      
      ctaBanner.appendChild(ctaText);
      ctaBanner.appendChild(crownImg);
      
      // Add click handler to close tooltip and trigger EXISTING dialog
      ctaBanner.style.cursor = 'pointer';
      ctaBanner.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Hide the calbar tooltip
        tooltip.style.display = 'none';
        
        // Find and trigger the EXISTING PRO feature dialog from booking status summary
        var existingOpenButton = document.querySelector('#sln-booking-status-summary .sln-profeature__open-button');
        if (existingOpenButton) {
          existingOpenButton.click();
        }
      });
      
      tooltip.appendChild(ctaBanner);
    }
    
    document.body.appendChild(tooltip);
    
    // Show/hide tooltip on hover
    var hideTooltipTimeout;
    
    calbar.addEventListener('mouseenter', function(e) {
      clearTimeout(hideTooltipTimeout);
      var calbarRect = calbar.getBoundingClientRect();
      tooltip.style.display = 'block';
      tooltip.style.visibility = 'hidden'; // Temporarily hide to get height
      
      // Wait for next frame to get accurate height
      requestAnimationFrame(function() {
        var tooltipHeight = tooltip.offsetHeight;
        tooltip.style.top = (calbarRect.top - tooltipHeight - 10) + 'px';
        tooltip.style.left = (calbarRect.left + calbarRect.width / 2) + 'px';
        tooltip.style.transform = 'translateX(-50%)';
        tooltip.style.visibility = 'visible';
      });
    });
    
    calbar.addEventListener('mouseleave', function(e) {
      hideTooltipTimeout = setTimeout(function() {
        if (!tooltip.matches(':hover')) {
          tooltip.style.display = 'none';
        }
      }, 200);
    });
    
    tooltip.addEventListener('mouseenter', function() {
      clearTimeout(hideTooltipTimeout);
    });
    
    tooltip.addEventListener('mouseleave', function() {
      tooltip.style.display = 'none';
    });
}

/**
 * Initialize tooltips for month calbar elements
 * Wrapper function that calls initSingleCalbarTooltip for each calbar
 * @param {boolean} isFreeVersion - Whether this is the FREE edition
 */
function initCalbarTooltips(isFreeVersion) {
  if (typeof isFreeVersion === 'undefined') {
    isFreeVersion = false;
  }
  
  // Get all month-calbar elements
  var calbars = document.querySelectorAll('.month-calbar');
  
  calbars.forEach(function(calbar) {
    var hasBookings = calbar.getAttribute('data-has-bookings') === '1';
    var bookingDate = calbar.getAttribute('data-booking-date');
    
    if (!hasBookings || !bookingDate) {
      return;
    }
    
    // Find the booking data script for this date
    var dataScript = document.querySelector('.calbar-booking-data[data-date="' + bookingDate + '"]');
    if (!dataScript) {
      return;
    }
    
    initSingleCalbarTooltip(calbar, bookingDate, dataScript, isFreeVersion);
  });
}

/**
 * Helper function to format time from datetime string
 */
function formatTime(datetimeStr) {
  var date = new Date(datetimeStr);
  var hours = date.getHours();
  var minutes = date.getMinutes();
  var ampm = hours >= 12 ? 'PM' : 'AM';
  hours = hours % 12;
  hours = hours ? hours : 12;
  minutes = minutes < 10 ? '0' + minutes : minutes;
  return hours + ':' + minutes + ' ' + ampm;
}

/**
 * Helper function to format date header (e.g., "Tuesday 26 July")
 */
function formatDateHeader(dateStr) {
  var date = new Date(dateStr);
  var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  
  var dayName = days[date.getDay()];
  var dayNum = date.getDate();
  var monthName = months[date.getMonth()];
  
  return dayName + ' ' + dayNum + ' ' + monthName;
}
