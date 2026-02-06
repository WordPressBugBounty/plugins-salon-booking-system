/**
 * Bootstrap based calendar full view.
 *
 * https://github.com/Serhioromano/bootstrap-calendar
 *
 * User: Sergey Romanov <serg4172@mail.ru>
 */
"use strict";
Date.prototype.getWeek = function () {
  var onejan = new Date(this.getFullYear(), 0, 1);
  return Math.ceil(
    ((this.getTime() - onejan.getTime()) / 86400000 + onejan.getDay() + 1) / 7,
  );
};
Date.prototype.getMonthFormatted = function () {
  var month = this.getMonth() + 1;
  return month < 10 ? "0" + month : month;
};
Date.prototype.getDateFormatted = function () {
  var date = this.getDate();
  return date < 10 ? "0" + date : date;
};
if (!String.prototype.format) {
  String.prototype.format = function () {
    var args = arguments;
    return this.replace(/{(\d+)}/g, function (match, number) {
      return typeof args[number] != "undefined" ? args[number] : match;
    });
  };
}
if (!String.prototype.formatNum) {
  String.prototype.formatNum = function (decimal) {
    var r = "" + this;
    while (r.length < decimal) r = "0" + r;
    return r;
  };
}

(function ($) {
  function sln_ProFeatureTooltip() {
    const elements = document.getElementsByClassName("sln-profeature__cta");
    const elementsArr = Array.from(elements);
    elementsArr.forEach((el) => {
      const dialog = el.getElementsByClassName("sln-profeature__dialog");
      const openButton = el.getElementsByClassName(
        "sln-profeature__open-button",
      );
      const closeButton = el.getElementsByClassName(
        "sln-profeature__close-button",
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

  var defaults = {
    // Width of the calendar
    width: "100%",
    // Initial view (can be 'month', 'week', 'day')
    view: "month",
    // Initial date. No matter month, week or day this will be a starting point. Can be 'now' or a date in format 'yyyy-mm-dd'
    day: "now",
    // Day Start time and end time with time intervals. Time split 10, 15 or 30.
    time_start: "06:00",
    time_end: "22:00",
    time_split: "30",
    // Auto refresh behaviour for admin calendar views
    auto_refresh: true,
    auto_refresh_interval: 60000,
    // Source of events data. It can be one of the following:
    // - URL to return JSON list of events in special format.
    //   {success:1, result: [....]} or for error {success:0, error:'Something terrible happened'}
    //   events: [...] as described in events property description
    //   The start and end variables will be sent to this url
    // - A function that received the start and end date, and that
    //   returns an array of events (as described in events property description)
    // - An array containing the events
    events_source: "",
    // Path to templates should end with slash /. It can be as relative
    // /component/bootstrap-calendar/tmpls/
    // or absolute
    // http://.../component/bootstrap-calendar/tmpls/
    tmpl_path: "tmpls/",
    tmpl_cache: true,
    classes: {
      months: {
        inmonth: "cal-day-inmonth",
        outmonth: "cal-day-outmonth",
        saturday: "cal-day-weekend",
        sunday: "cal-day-weekend",
        holidays: "cal-day-holiday",
        today: "cal-day-today",
      },
      week: {
        workday: "cal-day-workday",
        saturday: "cal-day-weekend",
        sunday: "cal-day-weekend",
        holidays: "cal-day-holiday",
        today: "cal-day-today",
      },
    },
    // ID of the element of modal window. If set, events URLs will be opened in modal windows.
    modal: null,
    //	modal handling setting, one of "iframe", "ajax" or "template"
    modal_type: "iframe",
    //	function to set modal title, will be passed the event as a parameter
    modal_title: null,
    views: {
      year: {
        slide_events: 1,
        enable: 1,
      },
      month: {
        slide_events: 1,
        enable: 1,
      },
      week: {
        enable: 1,
      },
      day: {
        enable: 1,
      },
    },
    merge_holidays: false,
    // ------------------------------------------------------------
    // CALLBACKS. Events triggered by calendar class. You can use
    // those to affect you UI
    // ------------------------------------------------------------
    onAfterEventsLoad: function (events) {
      // Inside this function 'this' is the calendar instance
    },
    onBeforeEventsLoad: function (next) {
      // Inside this function 'this' is the calendar instance
      next();
    },
    onAfterViewLoad: function (view) {
      // Inside this function 'this' is the calendar instance
    },
    onAfterModalShown: function (events) {
      // Inside this function 'this' is the calendar instance
    },
    onAfterModalHidden: function (events) {
      // Inside this function 'this' is the calendar instance
    },
    // -------------------------------------------------------------
    // INTERNAL USE ONLY. DO NOT ASSIGN IT WILL BE OVERRIDDEN ANYWAY
    // -------------------------------------------------------------
    events: [],
    templates: {
      year: "",
      month: "",
      week: "",
      day: "",
    },
    stop_cycling: false,
  };

  var defaults_extended = {
    first_day: 2,
    holidays: {
      // January 1
      "01-01": "New Year's Day",
      // Third (+3*) Monday (1) in January (01)
      "01+3*1": "Birthday of Dr. Martin Luther King, Jr.",
      // Third (+3*) Monday (1) in February (02)
      "02+3*1": "Washington's Birthday",
      // Last (-1*) Monday (1) in May (05)
      "05-1*1": "Memorial Day",
      // July 4
      "04-07": "Independence Day",
      // First (+1*) Monday (1) in September (09)
      "09+1*1": "Labor Day",
      // Second (+2*) Monday (1) in October (10)
      "10+2*1": "Columbus Day",
      // November 11
      "11-11": "Veterans Day",
      // Fourth (+4*) Thursday (4) in November (11)
      "11+4*4": "Thanksgiving Day",
      // December 25
      "25-12": "Christmas",
    },
  };

  var strings = {
    error_noview: "Calendar: View {0} not found",
    error_dateformat:
      'Calendar: Wrong date format {0}. Should be either "now" or "yyyy-mm-dd"',
    error_loadurl: "Calendar: Event URL is not set",
    error_where:
      'Calendar: Wrong navigation direction {0}. Can be only "next" or "prev" or "today"',
    error_timedevide:
      "Calendar: Time split parameter should divide 60 without decimals. Something like 10, 15, 30",

    no_events_in_day: "No events in this day.",

    select: "SELECT",
    click_row_block: 'Click on the "ending time" row',

    add_event: "Add book",

    title_year: "{0}",
    title_month: "{0} {1}",
    title_week: "week {0} of {1}",
    title_day: "{0} {1} {2}, {3}",

    week: "Week {0}",
    all_day: "All day",
    time: "Time",
    events: "Events",
    before_time: "Ends before timeline",
    after_time: "Starts after timeline",

    total_amount: "Total amount",
    discount: "Discount",
    deposit: "Deposit",
    due: "Due",
    confirm: window.salon_calendar.confirm_title,
    delete: window.salon_calendar.delete_title,
    click_plus: "Click or tap on plus icon",

    m0: "January",
    m1: "February",
    m2: "March",
    m3: "April",
    m4: "May",
    m5: "June",
    m6: "July",
    m7: "August",
    m8: "September",
    m9: "October",
    m10: "November",
    m11: "December",

    ms0: "Jan",
    ms1: "Feb",
    ms2: "Mar",
    ms3: "Apr",
    ms4: "May",
    ms5: "Jun",
    ms6: "Jul",
    ms7: "Aug",
    ms8: "Sep",
    ms9: "Oct",
    ms10: "Nov",
    ms11: "Dec",

    d0: "Sunday",
    d1: "Monday",
    d2: "Tuesday",
    d3: "Wednesday",
    d4: "Thursday",
    d5: "Friday",
    d6: "Saturday",
  };

  var browser_timezone = "";
  try {
    if (
      window.jstz &&
      typeof window.jstz == "object" &&
      typeof jstz.determine == "function"
    ) {
      browser_timezone = jstz.determine().name();
      if (typeof browser_timezone !== "string") {
        browser_timezone = "";
      }
    }
  } catch (e) {}

  function buildEventsUrl(events_url, data) {
    var separator, key, url;
    url = events_url;
    separator = events_url.indexOf("?") < 0 ? "?" : "&";
    for (key in data) {
      url += separator + key + "=" + encodeURIComponent(data[key]);
      separator = "&";
    }
    return url;
  }

  function getExtentedOption(cal, option_name) {
    var fromOptions =
      cal.options[option_name] != null ? cal.options[option_name] : null;
    var fromLanguage =
      cal.locale[option_name] != null ? cal.locale[option_name] : null;
    if (option_name == "holidays" && cal.options.merge_holidays) {
      var holidays = {};
      $.extend(
        true,
        holidays,
        fromLanguage ? fromLanguage : defaults_extended.holidays,
      );
      if (fromOptions) {
        $.extend(true, holidays, fromOptions);
      }
      return holidays;
    } else if (option_name == "first_day" && fromOptions != null) {
      return fromOptions;
    } else {
      if (fromOptions != null) {
        return fromOptions;
      }
      if (fromLanguage != null) {
        return fromLanguage;
      }
      return defaults_extended[option_name];
    }
  }

  function getHolidays(cal, year) {
    var hash = [];
    var holidays_def = getExtentedOption(cal, "holidays");
    for (var k in holidays_def) {
      hash.push(k + ":" + holidays_def[k]);
    }
    hash.push(year);
    hash = hash.join("|");
    if (hash in getHolidays.cache) {
      return getHolidays.cache[hash];
    }
    var holidays = [];
    $.each(holidays_def, function (key, name) {
      var firstDay = null,
        lastDay = null,
        failed = false;
      $.each(key.split(">"), function (i, chunk) {
        var m,
          date = null;
        if ((m = /^(\d\d)-(\d\d)$/.exec(chunk))) {
          date = new Date(year, parseInt(m[2], 10) - 1, parseInt(m[1], 10));
        } else if ((m = /^(\d\d)-(\d\d)-(\d\d\d\d)$/.exec(chunk))) {
          if (parseInt(m[3], 10) == year) {
            date = new Date(year, parseInt(m[2], 10) - 1, parseInt(m[1], 10));
          }
        } else if ((m = /^easter(([+\-])(\d+))?$/.exec(chunk))) {
          date = getEasterDate(year, m[1] ? parseInt(m[1], 10) : 0);
        } else if ((m = /^(\d\d)([+\-])([1-5])\*([0-6])$/.exec(chunk))) {
          var month = parseInt(m[1], 10) - 1;
          var direction = m[2];
          var offset = parseInt(m[3]);
          var weekday = parseInt(m[4]);
          switch (direction) {
            case "+":
              var d = new Date(year, month, 1 - 7);
              while (d.getDay() != weekday) {
                d = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1);
              }
              date = new Date(
                d.getFullYear(),
                d.getMonth(),
                d.getDate() + 7 * offset,
              );
              break;
            case "-":
              var d = new Date(year, month + 1, 0 + 7);
              while (d.getDay() != weekday) {
                d = new Date(d.getFullYear(), d.getMonth(), d.getDate() - 1);
              }
              date = new Date(
                d.getFullYear(),
                d.getMonth(),
                d.getDate() - 7 * offset,
              );
              break;
          }
        }
        if (!date) {
          warn("Unknown holiday: " + key);
          failed = true;
          return false;
        }
        switch (i) {
          case 0:
            firstDay = date;
            break;
          case 1:
            if (date.getTime() <= firstDay.getTime()) {
              warn("Unknown holiday: " + key);
              failed = true;
              return false;
            }
            lastDay = date;
            break;
          default:
            warn("Unknown holiday: " + key);
            failed = true;
            return false;
        }
      });
      if (!failed) {
        var days = [];
        if (lastDay) {
          for (
            var date = new Date(firstDay.getTime());
            date.getTime() <= lastDay.getTime();
            date.setDate(date.getDate() + 1)
          ) {
            days.push(new Date(date.getTime()));
          }
        } else {
          days.push(firstDay);
        }
        holidays.push({ name: name, days: days });
      }
    });
    getHolidays.cache[hash] = holidays;
    return getHolidays.cache[hash];
  }

  getHolidays.cache = {};

  function warn(message) {
    if (
      window.console &&
      typeof window.console == "object" &&
      typeof window.console.warn == "function"
    ) {
      window.console.warn("[Bootstrap-Calendar] " + message);
    }
  }

  function eventOverlap(start, end, events) {
    var col = 0;

    var events_by_columns = {};

    events.forEach(function (event) {
      var event_col = event[2];

      if (typeof events_by_columns[event_col] === "undefined") {
        events_by_columns[event_col] = {};
      }

      var booking_id = event[3].id;

      if (typeof events_by_columns[event_col][booking_id] === "undefined") {
        events_by_columns[event_col][booking_id] = [];
      }

      events_by_columns[event_col][booking_id].push(event);
    });

    $.each(events_by_columns, function (_col, _events) {
      var available_col = true;

      $.each(_events, function (booking_id, _items) {
        //first service of booking
        var _eventStart = _items[0][0];
        //last service of booking
        var _eventEnd =
          _items[_items.length - 1][0] + _items[_items.length - 1][1];
        if (!(end <= _eventStart || start >= _eventEnd)) {
          available_col = false;
        }
      });

      if (available_col) {
        col = parseInt(_col);
        return false;
      }

      col = parseInt(_col) + 1;
    });

    return col;
  }

  function Calendar(params, context) {
    this.options = $.extend(
      true,
      { position: { start: new Date(), end: new Date() } },
      defaults,
      params,
    );
    this.setLanguage(this.options.language);
    this.context = context;
    this._pendingLoadCount = 0;
    this._loadingIndicatorTimer = null;
    this._loadingIndicatorStartedAt = null;
    this._autoRefreshTimer = null;
    this._autoRefreshPauseReasons = {};
    this._autoRefreshInFlight = false;
    this._visibilityListener = null;

    context.css("width", this.options.width).addClass("cal-context");

    this.view();
    this._setupAutoRefresh();
    return this;
  }

  Calendar.prototype.setOptions = function (object) {
    var shouldResetAutoRefresh =
      Object.prototype.hasOwnProperty.call(object, "auto_refresh") ||
      Object.prototype.hasOwnProperty.call(object, "auto_refresh_interval");

    $.extend(this.options, object);
    if ("language" in object) {
      this.setLanguage(object.language);
    }
    if (shouldResetAutoRefresh) {
      this._setupAutoRefresh();
    }
  };

  Calendar.prototype.setLanguage = function (lang) {
    if (window.calendar_languages && lang in window.calendar_languages) {
      this.locale = $.extend(
        true,
        {},
        strings,
        window.calendar_locale || {},
        calendar_languages[lang],
      );
      this.options.language = lang;
    } else {
      this.locale = $.extend(true, {}, strings, window.calendar_locale || {});
      delete this.options.language;
    }
  };

  Calendar.prototype._render = function () {
    this.context.html("");

    this.context.append(this.options.server_render);
    this._update();
    this._update_day_prepare_sln_booking_editor();
    let calendar = this;
    $(".cal-day-assistant").on("mousedown", function (e_down) {
      var start_pos = e_down.pageX;
      $(this).addClass("cal-day-assistants--move");
      $(this).css(
        "left",
        start_pos - $(this).parent().offset().left - $(this).width() / 2,
      );
      var $self = $(this);
      $(this)
        .parent()
        .on("mousemove", function (e_move) {
          $self.css(
            "left",
            e_move.pageX - $self.parent().offset().left - $self.width() / 2,
          );
        });
      $(".cal-day-assistant").on("mouseup", function (e_down) {
        $(this).parent().off("mousemove");
        let new_sort = "";
        let is_find = false;
        $(".cal-day-assistant").each(function (id, el) {
          if ($(el).data("assistant") == $self.data("assistant")) {
            return;
          }
          if ($(el).offset().left < $self.offset().left || is_find) {
            new_sort += el.outerHTML;
          } else {
            $(this).removeClass("cal-day-assistants--move");
            $(this).removeAttr("style");
            new_sort += $self[0].outerHTML + el.outerHTML;
            is_find = true;
          }
        });
        if (!is_find) {
          $(this).removeClass("cal-day-assistants--move");
          $(this).removeAttr("style");
          new_sort += $self[0].outerHTML;
        }
        if (new_sort) {
          $(this).parent().html(new_sort);
          calendar.view();
        }
      });
    });
  };

  Calendar.prototype._save_scroll_pos = function () {
    this._scroll_pos_x = this.context
      .find(".cal-day-panel__wrapper.clearfix")
      .scrollTop();
    this._scroll_pos_y = this.context
      .find(".cal-day-panel__wrapper.clearfix")
      .scrollLeft();
  };

  Calendar.prototype._recover_scroll_pos = function () {
    if (typeof this._new_post_id != "undefined" && this._new_post_id != null) {
      this._scroll_pos_x =
        this.context
          .find('div[data-tooltip-id="' + this._new_post_id + '"]')
          .offset().top -
        this.context.find(".cal-day-panel__wrapper.clearfix").offset().top -
        40;
      this._doBounce(
        this.context.find('div[data-tooltip-id="' + this._new_post_id + '"]'),
        5,
        "10px",
        100,
      );
    }
    this._new_post_id = null;
    this.context
      .find(".cal-day-panel__wrapper.clearfix")
      .scrollTop(this._scroll_pos_x);
    this.context
      .find(".cal-day-panel__wrapper.clearfix")
      .scrollLeft(this._scroll_pos_y);
  };

  Calendar.prototype._doBounce = function (element, times, distance, speed) {
    for (let i = 0; i < times; i++) {
      element
        .animate({ marginTop: "-=" + distance }, speed)
        .animate({ marginTop: "+=" + distance }, speed);
    }
  };

  Calendar.prototype.view = function (view) {
    this._save_scroll_pos();
    if (view) {
      if (!this.options.views[view].enable) {
        return;
      }
      this.options.view = view;
    }

    this._init_position();
    this._loadEvents();
  };

  Calendar.prototype.navigate = function (where, next) {
    var to = $.extend({}, this.options.position);
    if (where == "next") {
      switch (this.options.view) {
        case "year":
          to.start.setFullYear(this.options.position.start.getFullYear() + 1);
          break;
        case "month":
          to.start.setMonth(this.options.position.start.getMonth() + 1);
          break;
        case "week":
          to.start.setDate(this.options.position.start.getDate() + 7);
          break;
        case "day":
          to.start.setDate(this.options.position.start.getDate() + 1);
          break;
      }
    } else if (where == "prev") {
      switch (this.options.view) {
        case "year":
          to.start.setFullYear(this.options.position.start.getFullYear() - 1);
          break;
        case "month":
          to.start.setMonth(this.options.position.start.getMonth() - 1);
          break;
        case "week":
          to.start.setDate(this.options.position.start.getDate() - 7);
          break;
        case "day":
          to.start.setDate(this.options.position.start.getDate() - 1);
          break;
      }
    } else if (where == "today") {
      to.start.setTime(new Date().getTime());
    } else {
      $.error(this.locale.error_where.format(where));
    }
    this.options.day =
      to.start.getFullYear() +
      "-" +
      to.start.getMonthFormatted() +
      "-" +
      to.start.getDateFormatted();
    this.view();
    if (_.isFunction(next)) {
      next();
    }
  };

  Calendar.prototype._init_position = function () {
    var year, month, day;

    if (this.options.day == "now") {
      var date = new Date();
      year = date.getFullYear();
      month = date.getMonth();
      day = date.getDate();
    } else if (this.options.day.match(/^\d{4}-\d{2}-\d{2}$/g)) {
      var list = this.options.day.split("-");
      year = parseInt(list[0], 10);
      month = parseInt(list[1], 10) - 1;
      day = parseInt(list[2], 10);
    } else {
      $.error(this.locale.error_dateformat.format(this.options.day));
    }

    switch (this.options.view) {
      case "year":
        this.options.position.start.setTime(new Date(year, 0, 1).getTime());
        this.options.position.end.setTime(new Date(year + 1, 0, 1).getTime());
        break;
      case "month":
        this.options.position.start.setTime(new Date(year, month, 1).getTime());
        this.options.position.end.setTime(
          new Date(year, month + 1, 1).getTime(),
        );
        break;
      case "day":
        this.options.position.start.setTime(
          new Date(year, month, day).getTime(),
        );
        this.options.position.end.setTime(
          new Date(year, month, day + 1).getTime(),
        );
        break;
      case "week":
        var curr = new Date(year, month, day);
        var first;
        var firstday = curr.getDay();
        var weekday = parseInt(getExtentedOption(this, "first_day"));
        if (weekday === 0) weekday = 7;
        if (firstday - weekday === 0) {
          first = curr.getDate();
        } else if (firstday - weekday > 0) {
          first = curr.getDate() - (firstday - weekday);
        } else if (firstday - weekday < 0) {
          first = curr.getDate() - (7 - (weekday - firstday));
        }
        this.options.position.start.setTime(
          new Date(year, month, first).getTime(),
        );
        this.options.position.end.setTime(
          new Date(year, month, first + 7).getTime(),
        );
        break;
      default:
        $.error(this.locale.error_noview.format(this.options.view));
    }
    return this;
  };

  Calendar.prototype.getTitle = function () {
    var p = this.options.position.start;
    switch (this.options.view) {
      case "year":
        return this.locale.title_year.format(p.getFullYear());
      case "month":
        return this.locale.title_month.format(
          this.locale["m" + p.getMonth()],
          p.getFullYear(),
        );
      case "week":
        return this.locale.title_week.format(p.getWeek(), p.getFullYear());
      case "day":
        return this.locale.title_day.format(
          this.locale["d" + p.getDay()],
          p.getDate(),
          this.locale["m" + p.getMonth()],
          p.getFullYear(),
        );
    }
    return;
  };

  Calendar.prototype.isToday = function () {
    var now = new Date().getTime();

    return now > this.options.position.start && now < this.options.position.end;
  };

  Calendar.prototype.getStartDate = function () {
    return this.options.position.start;
  };

  Calendar.prototype.getEndDate = function () {
    return this.options.position.end;
  };

  Calendar.prototype.get_assistant_position = function () {
    let ret = [];
    $(".cal-day-assistant").each(function (id, el) {
      ret.push($(el).data("assistant"));
    });
    return ret;
  };

  Calendar.prototype._loadEvents = function () {
    var self = this;
    let loader = function () {
      var params = {
        from: self.options.position.start.getTime(),
        to: self.options.position.end.getTime(),
        offset: self.options.position.start.getTimezoneOffset(),
        offsetEnd: self.options.position.end.getTimezoneOffset(),
        assistant_position: self.get_assistant_position(),
        _assistants_mode: self.options._assistants_mode ? "true" : "false",
      };
      if (browser_timezone.length) {
        params.browser_timezone = browser_timezone;
      }
      self._pendingLoadCount = (self._pendingLoadCount || 0) + 1;
      var $title = $(".current-view--title");
      if ($title.length) {
        if (self._loadingIndicatorTimer) {
          window.clearTimeout(self._loadingIndicatorTimer);
          self._loadingIndicatorTimer = null;
        }
        $title.addClass("sln-box--loading");
        if (!self._loadingIndicatorStartedAt) {
          self._loadingIndicatorStartedAt = Date.now();
        }
      }
      var request = $.ajax({
        url: buildEventsUrl(self.options.events_source, params),
        dataType: "json",
        type: "GET",
      })
        .done(function (json) {
        if (!json.success) {
          $.error(json.error);
        }
        if (json.render) {
          self.options.server_render = json.render;
          self._render();
          self._recover_scroll_pos();

          if (json.rules && typeof window !== "undefined") {
            window.daily_rules = json.rules;
          }

          // Update booking status summary
          if (json.statusCounts) {
            updateBookingStatusSummary(json.statusCounts);
          }
          
          // Update Today button with upcoming count and bookings data
          if (json.upcomingToday !== undefined && typeof updateTodayButton === 'function') {
            updateTodayButton(json.upcomingToday, json.upcomingTodayBookings || [], json.isFreeVersion || false);
          }
          
          // Initialize calbar tooltips for month view
          if (typeof initCalbarTooltips === 'function') {
            initCalbarTooltips(json.isFreeVersion || false);
          }
          
          if (json.assistants_rules && typeof window !== "undefined") {
            window.daily_assistants_rules = json.assistants_rules;
          }

          if (typeof DayCalendarHolydays !== "undefined") {
            DayCalendarHolydays.rules = false;
            DayCalendarHolydays.assistants_rules = false;
            if (self.options.view === "day") {
              DayCalendarHolydays.showRules(self);
            }
          }

          self.options.onAfterViewLoad.call(self, self.options.view);
          setTimeout(() => {
            $("#sln-pageloading").addClass("sln-pageloading--inactive");
          }, 3000);
          setTimeout(() => {
            $("body").addClass("sln-body--scrolldef");
          }, 3200);
        }
      })
        .always(function () {
          self._pendingLoadCount = Math.max(
            0,
            (self._pendingLoadCount || 1) - 1,
          );
          if (!self._pendingLoadCount && $title.length) {
            var clearIndicator = function () {
              $title.removeClass("sln-box--loading");
              self._loadingIndicatorStartedAt = null;
              if (self._loadingIndicatorTimer) {
                window.clearTimeout(self._loadingIndicatorTimer);
                self._loadingIndicatorTimer = null;
              }
            };
            var minVisible = 2000;
            var startedAt = self._loadingIndicatorStartedAt || Date.now();
            var remaining = minVisible - (Date.now() - startedAt);
            if (remaining <= 0) {
              clearIndicator();
            } else {
              if (self._loadingIndicatorTimer) {
                window.clearTimeout(self._loadingIndicatorTimer);
              }
              self._loadingIndicatorTimer = window.setTimeout(function () {
                clearIndicator();
              }, remaining);
            }
          }
        });
      return request;
    };

    var loadevent = null;
    this.options.onBeforeEventsLoad.call(this, function () {
      loadevent = loader();
      self.options.events.sort(function (a, b) {
        var delta;
        delta = a.start - b.start;
        if (delta == 0) {
          delta = a.end - b.end;
        }
        return delta;
      });
      self.options.onAfterEventsLoad.call(self, self.options.events);
    });
    return loadevent;
  };

  Calendar.prototype._clearAutoRefresh = function () {
    if (this._autoRefreshTimer) {
      window.clearInterval(this._autoRefreshTimer);
      this._autoRefreshTimer = null;
    }
  };

  Calendar.prototype._setupAutoRefresh = function () {
    this._clearAutoRefresh();

    if (!this.options.auto_refresh) {
      if (this._visibilityListener) {
        document.removeEventListener(
          "visibilitychange",
          this._visibilityListener,
        );
        this._visibilityListener = null;
      }
      return;
    }

    var self = this;
    var interval = parseInt(this.options.auto_refresh_interval, 10);
    if (isNaN(interval) || interval < 15000) {
      interval = 60000;
    }

    if (!this._visibilityListener) {
      this._visibilityListener = function () {
        if (document.hidden) {
          self.pauseAutoRefresh("page-hidden");
        } else {
          self.resumeAutoRefresh("page-hidden");
        }
      };
      document.addEventListener(
        "visibilitychange",
        this._visibilityListener,
      );
      if (document.hidden) {
        this.pauseAutoRefresh("page-hidden");
      }
    }

    this._autoRefreshTimer = window.setInterval(function () {
      if (self._canAutoRefresh()) {
        self._performAutoRefresh();
      }
    }, interval);
  };

  Calendar.prototype.pauseAutoRefresh = function (reason) {
    if (!this._autoRefreshPauseReasons) {
      this._autoRefreshPauseReasons = {};
    }
    this._autoRefreshPauseReasons[reason || "manual"] = true;
  };

  Calendar.prototype.resumeAutoRefresh = function (reason) {
    if (!this._autoRefreshPauseReasons) {
      return;
    }
    if (reason) {
      delete this._autoRefreshPauseReasons[reason];
    } else {
      this._autoRefreshPauseReasons = {};
    }
  };

  Calendar.prototype._isAutoRefreshPaused = function () {
    var reasons = this._autoRefreshPauseReasons || {};
    for (var key in reasons) {
      if (
        Object.prototype.hasOwnProperty.call(reasons, key) &&
        reasons[key]
      ) {
        return true;
      }
    }
    return false;
  };

  Calendar.prototype._canAutoRefresh = function () {
    if (!this.options.auto_refresh) {
      return false;
    }
    if (this._autoRefreshInFlight) {
      return false;
    }
    if (this._pendingLoadCount > 0) {
      return false;
    }
    if (this._isAutoRefreshPaused()) {
      return false;
    }
    if (
      typeof this.is_tooltip_shown === "function" &&
      this.is_tooltip_shown()
    ) {
      return false;
    }
    if (this.activecell) {
      return false;
    }
    if (typeof document !== "undefined" && document.hidden) {
      return false;
    }
    if (typeof window !== "undefined" && window.jQuery) {
      if (jQuery("body").hasClass("modal-open")) {
        return false;
      }
    }
    return true;
  };

  Calendar.prototype._performAutoRefresh = function () {
    var self = this;
    if (this._autoRefreshInFlight) {
      return;
    }

    this._autoRefreshInFlight = true;
    var refresh = this._loadEvents();
    if (refresh && typeof refresh.always === "function") {
      refresh.always(function () {
        self._autoRefreshInFlight = false;
      });
    } else {
      this._autoRefreshInFlight = false;
    }
  };

  Calendar.prototype._update = function () {
    var self = this;
    // var is_touch_device = "ontouchstart" in document.documentElement;
    var tooltipShown = false;
    var currentTooltipId = null;

    if (this.options.view !== "day") {
      $(".sln-free-locked-slots").addClass("hide");
    }

    Calendar.prototype.is_tooltip_shown = function () {
      return tooltipShown;
    };

    // Modern tooltip system integration
    if ($("*[data-tooltip-trigger]").length > 0) {
      // Initialize modern tooltip system
      if (window.slnTooltipManager) {
        // Tooltip manager is already initialized
        console.log("Tooltip manager initialized");
      } else {
        // Try to initialize tooltip manager if it's not loaded yet
        if (typeof TooltipManager !== "undefined") {
          window.slnTooltipManager = new TooltipManager();
          console.log("Tooltip manager initialized from class");
        } else {
          console.warn(
            "Tooltip manager not found, falling back to legacy system",
          );
          // Fallback to legacy tooltip system for backward compatibility
          $('[data-toggle="tooltip"]').tooltip({
            container: "body",
          });
        }
      }

      $(document).on("click", ".sln-tooltip-dismiss", function () {
        $('*[data-option="day"]').tooltip("hide");

        $(
          '[data-tooltip-id="' +
            currentTooltipId +
            '"].sln-event-header-more-icon',
        )
          .removeClass("sln-event-header-more-icon-horizontal")
          .addClass("sln-event-header-more-icon-vertical");
        $(".tooltip-arrow").css("border-right-color", "#EFEFEF");
        tooltipShown = false;
        currentTooltipId = null;
      });
    } else {
      $('*[data-toggle="tooltip"]').tooltip({
        container: "body",
      });
    }
    // }

    $(".sln-btn--icon.sln-icon--close.custom").on("click", function () {
      $.ajax({
        url: salon.ajax_url + "&action=salon&method=RemoveNotice",
        type: "POST",
        success: function ($data) {
          $(".row.notice_custom").remove();
        },
      });
    });
    $(".sln-event-header-more-icon").on("click", function () {
      var tooltipId = $(this).data("tooltip-id");

      if (tooltipShown) {
        $('*[data-option="day"]').tooltip("hide");
        $(
          '[data-tooltip-id="' +
            currentTooltipId +
            '"].sln-event-header-more-icon',
        )
          .removeClass("sln-event-header-more-icon-horizontal")
          .addClass("sln-event-header-more-icon-vertical");
        $(".tooltip-arrow").css("border-right-color", "#EFEFEF");
        tooltipShown = false;
        currentTooltipId = null;
      } else {
        if (currentTooltipId != tooltipId && currentTooltipId != null) {
          $('[data-tooltip-id="' + currentTooltipId + '"]').tooltip("hide");
          $(
            '[data-tooltip-id="' +
              currentTooltipId +
              '"].sln-event-header-more-icon',
          );
          // .removeClass("sln-event-header-more-icon-horizontal")
          // .addClass("sln-event-header-more-icon-vertical");
          tooltipShown = false;
        }

        if (!tooltipShown) {
          $(this)
            .closest('div[data-tooltip-id="' + tooltipId + '"]')
            .tooltip("show");
          $(".tooltip-arrow").css("border-right-color", "#EFEFEF");

          tooltipShown = true;
          currentTooltipId = tooltipId;

          $(
            '[data-tooltip-id="' +
              currentTooltipId +
              '"].sln-event-header-more-icon',
          );
          // .removeClass("sln-event-header-more-icon-vertical")
          // .addClass("sln-event-header-more-icon-horizontal");
        }

        if ($("div[data-dup-icon]").attr("data-dup-icon") == "false") {
          $('[data-bookingid="' + currentTooltipId + '"].sln-dup-icon-tooltip')
            .removeClass("sln-dup-icon-tooltip")
            .addClass("sln-dup-close-icon-tooltip");
        }

        if (!($("#data-disc-sys").length > 0)) {
          $("#due-tooltip").hide();
          $("#discount-tooltip").hide();
          $("#deposit-tooltip").hide();
        }
        if ($(this).parents(".day-highlight").hasClass("no-show")) {
          $(".sln-no-show-icon-tooltip").addClass("no-show");
        } else {
          $(".sln-no-show-icon-tooltip").removeClass("no-show");
        }
        sln_ProFeatureTooltip();
      }
    });

    $("*[data-cal-date]").on("click", function () {
      document
        .querySelectorAll('div[role="tooltip"]')
        .forEach(function (tooltip) {
          tooltip.style.display = "none";
        });
      //loading transition 06.2024
      $(".sln-box.sln-calendar-view .sln-viewloading").removeClass(
        "sln-viewloading--inactive",
      );
      var view = $(this).data("cal-view");
      self.options.day = $(this).data("cal-date");
      //loading transition 06.2024
      setTimeout(function () {
        self.view(view);
      }, 100);
    });
    $(".cal-cell").on("dblclick", function () {
      var view = $("[data-cal-date]", this).data("cal-view");
      self.options.day = $("[data-cal-date]", this).data("cal-date");
      self.view(view);
    });

    this["_update_" + this.options.view]();
    if (
      this.options.view !== "day" &&
      this.options.view !== "week" &&
      this.options.view !== "month"
    ) {
      $(".cal-day-filter").addClass("hide");
      $(".sln-free-locked-slots").addClass("hide");
    }
  };

  Calendar.prototype._update_day = function () {
    $("#cal-day-panel").height($("#cal-day-panel-hour").height());

    $("#cal-day-panel").width($(".cal-day-panel__wrapper")[0].scrollWidth - 60);

    $(".cal-row-head").width($(".cal-day-panel__wrapper")[0].scrollWidth);

    var self = this;

    var pagination = "";

    var page = this.options._page;
    var max = this.options._max_page;
    for (var i = 0; i <= max; i++) {
      pagination += this.options.cal_day_pagination
        .replace(/%class/, i === page ? "active" : "")
        .replace(/%page/, i);
    }
    $(".cal-day-pagination").html(pagination);
    $(".cal-day-filter").removeClass("hide");
    $(".sln-free-locked-slots").removeClass("hide");

    sln_ProFeatureTooltip();
  };

  Calendar.prototype._update_day_prepare_sln_booking_editor = function () {
    var calendar = this;

    var bookingId;
    var bookingDate;
    var bookingTime;
    var bookingCopy;

    // Modern tooltip action handlers
    $(document).on("click", ".sln-tooltip-edit", function (event) {
      event.preventDefault();
      if (window.slnTooltipManager) {
        window.slnTooltipManager.hideTooltip();
      }
      $("[data-action=clone-edited-booking]").text(
        $("[data-action=clone-edited-booking]").data("clone"),
      );
      $("[data-action=clone-edited-booking]").removeClass("confirm");
      $('[data-dismiss="modal"]').removeClass("hide-important");
      $('[data-action="delete-edited-booking"]').removeClass("hide-important");
      $(".clone-info").hide();
      event.preventDefault();

      $("[data-action=duplicate-edited-booking]").show();

      var date = $(this).closest("#cal-slide-box").data("cal-date");
      //open active cell
      $("body").one("sln.calendar.after-view-load", function () {
        setTimeout(function () {
          $('[data-cal-date="' + date + '"]')
            .closest(".cal-month-day")
            .trigger("mouseenter")
            .trigger("click");
        }, 500);
      });

      bookingDate = bookingTime = undefined;
      bookingId = $(this).data("bookingid");
      show_modal_booking_editor();
    });

    $(document).on("click", ".sln-details-search", function (event) {
      $('*[data-toggle="tooltip"]').tooltip("hide");
      event.preventDefault();
      $("[data-action=duplicate-edited-booking]").show();
      var date = $(this).closest("#cal-slide-box").data("cal-date");
      //open active cell
      $("body").one("sln.calendar.after-view-load", function () {
        setTimeout(function () {
          $('[data-cal-date="' + date + '"]')
            .closest(".cal-month-day")
            .trigger("mouseenter")
            .trigger("click");
        }, 500);
      });
      bookingDate = bookingTime = undefined;
      bookingId = $(this).data("bookingid");
      show_modal_booking_editor();
    });

    $(".sln-icon--pen")
      .off("click")
      .on("click", function (e) {
        e.preventDefault();
        $("[data-action=duplicate-edited-booking]").show();

        var date = $(this).closest("#cal-slide-box").data("cal-date");
        //open active cell
        $("body").one("sln.calendar.after-view-load", function () {
          setTimeout(function () {
            $('[data-cal-date="' + date + '"]')
              .closest(".cal-month-day")
              .trigger("mouseenter")
              .trigger("click");
          }, 500);
        });

        bookingDate = bookingTime = undefined;
        bookingId = $(this).closest(".event-item").data("event-id");
        show_modal_booking_editor();
      });

    $(
      ".events-list .event, .cal-cell1 .cal-event-week, .events-list .event, .cal-cell0 .cal-event-week",
    )
      .off("click")
      .on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        $("[data-action=duplicate-edited-booking]").show();

        bookingDate = bookingTime = undefined;
        bookingId = $(this).data("event-id");
        show_modal_booking_editor();
      });
    // HOVER TOOLTIPS DISABLED - Only click tooltips are used now
    // $(
    //   ".events-list .event, .cal-cell1 .cal-event-week, .events-list .event, .cal-cell0 .cal-event-week, .day-event",
    // )
    //   .off("mouseenter")
    //   .on("mouseenter", function (e) {
    //     $(this)
    //       .closest(".cal-month-day, .day-event")
    //       .find(
    //         '.sln-event-popup[data-event-id="' +
    //           $(this).data("event-id") +
    //           '"]',
    //       )
    //       .show();
    //   })
    //   .off("mouseleave")
    //   .on("mouseleave", function (e) {
    //     let event = $(this);
    //     setTimeout(function () {
    //       event
    //         .closest(".cal-month-day, .day-event")
    //         .find(
    //           '.sln-event-popup[data-event-id="' +
    //             event.data("event-id") +
    //             '"]',
    //         )
    //         .removeAttr("style");
    //     }, 300);
    //   });
    $(".events-list .sln-booking-title-phone")
      .off("click")
      .on("click", function (e) {
        // e.preventDefault();
        e.stopPropagation();
      });

    $("[data-action=add-event-by-date]")
      .off("click")
      .on("click", function () {
        bookingDate = $(this).data("event-date");
        bookingTime = $(this).data("event-time");
        console.log(
          "bookingDate=" + bookingDate + " bookingTime=" + bookingTime,
        );
        bookingId = undefined;
        $("#sln-booking-editor-modal").addClass("modal--new");
        show_modal_booking_editor();
      });

    function show_modal_booking_editor() {
      if (replaceBookingModalWithPopup) {
        show_booking_editor_popup();
      } else {
        show_booking_editor();
      }
    }

    function show_booking_editor_popup() {
      var srcTemplate =
        bookingCopy === "copy"
          ? "src-template-duplicate-booking"
          : bookingId === undefined
            ? "src-template-new-booking"
            : "src-template-edit-booking";
      bookingCopy = undefined;
      var $editor = $(".booking-editor");
      var editorLink = $editor
        .data(srcTemplate)
        .replace("%id", bookingId)
        .replace("%date", bookingDate)
        .replace("%time", bookingTime);

      editorLink = editorLink + "&sln_editor_popup=1";

      let params = `scrollbars=no,resizable=no,status=no,location=no,toolbar=no,menubar=no,width=1200,height=600,left=100,top=100`;
      var newWin = window.open(editorLink, "window", params);
    }

    function show_booking_editor() {
      $("#wpwrap").css("z-index", "auto");
      $("#sln-booking-editor-modal")
        .off("show.bs.modal")
        .on("show.bs.modal", onShowModal)
        .off("hide.bs.modal")
        .on("hide.bs.modal", onHideModal)
        .modal();

      const button = document.querySelector(
        'button[data-action="save-edited-booking"]',
      );
      if (button) {
        button.classList.add("sln-btn-disabled");
      }
    }

    function onShowModal() {
      calendar.pauseAutoRefresh("modal");
      launchLoadingSpinner();
      $("[data-action=clone-edited-booking]").text("Clone");
      $("[data-action=clone-edited-booking]").removeClass("confirm");
      $('[data-dismiss="modal"]').removeClass("hide-important");
      $('[data-action="delete-edited-booking"]').removeClass("hide-important");
      $(".clone-info").hide();
      var $editor = $(".booking-editor");
      $editor
        .off("load.dismiss_spinner")
        .on("load.dismiss_spinner", onLoadDismissSpinner);
      $editor.off("load.hide_modal");

      var srcTemplate =
        bookingCopy === "copy"
          ? "src-template-duplicate-booking"
          : bookingId === undefined
            ? "src-template-new-booking"
            : "src-template-edit-booking";
      bookingCopy = undefined;
      var editorLink = $editor
        .data(srcTemplate)
        .replace("%id", bookingId)
        .replace("%date", bookingDate)
        .replace("%time", bookingTime);
      $(function () {
        $(document).trigger("sln.iframeEditor.ready", [
          bookingId,
          bookingDate,
          srcTemplate,
          editorLink,
        ]);
      });
      $editor.attr("src", editorLink);

      $("[data-action=save-edited-booking]")
        .off("click")
        .on("click", onClickSaveEditedBooking);
      $("[data-action=delete-edited-booking]")
        .off("click")
        .on("click", onClickDeleteEditedBooking);
      $("[data-action=duplicate-edited-booking]")
        .off("click")
        .on("click", onClickDuplicateEditedBooking);
      $("[data-action=clone-edited-booking]")
        .off("click")
        .on("click", onClickCloneEditedBooking);
      $("[name=unit_times_input]").off("click").on("click", onChangeTimes);
    }

    function onHideModal() {
      $(".booking-editor").off("load");
      $(".booking-editor").attr("src", "");
      cancelLoadingSpinner();
      $("#sln-booking-editor-modal .booking-last-edit-div").html("");
      $("#sln-booking-editor-modal").removeClass("modal--new");
      calendar.view();
      calendar.resumeAutoRefresh("modal");
    }

    function onClickSaveEditedBooking() {
      var $editor = $(".booking-editor");
      calendar._new_post_id = $editor.contents().find("#post_ID").val();

      //$editor
      //	.off("load.dismiss_spinner")
      //	.on("load.dismiss_spinner", cancelLoadingSpinner);

      try {
        var validateBooking = window.frames[0].sln_validateBooking;
      } catch (e) {
        var validateBooking = window.frames[1].sln_validateBooking;
      }

      if (validateBooking()) {
        setTimeout(function () {
          $editor
            .off("load.hide_modal")
            .on("load.hide_modal", onLoadAfterSubmit);
          $("#sln-booking-editor-modal").modal("hide");
        }, 2000);

        if (!$editor.contents().find("#save-post").attr("disabled")) {
          launchLoadingSpinnerSaving();
        } else {
          $editor
            .contents()
            .scrollTop(
              $editor.contents().find("#sln_booking_services").offset().top,
            );
          $editor.contents().find("#sln-alert-noservices").fadeIn();
        }
        $editor.contents().find("#save-post").trigger("click");
      }
    }

    function onClickDeleteEditedBooking() {
      var $editor = $(".booking-editor");
      $editor.off("load.hide_modal").on("load.hide_modal", onLoadAfterSubmit);
      $editor
        .off("load.dismiss_spinner")
        .on("load.dismiss_spinner", onLoadDismissSpinner);

      try {
        var validateBooking = window.frames[0].sln_validateBooking;
      } catch (e) {
        var validateBooking = window.frames[1].sln_validateBooking;
      }

      if (validateBooking()) {
        launchLoadingSpinner();
        var href = $editor.contents().find(".submitdelete").attr("href");
        $.get(href);
        $("#sln-booking-editor-modal").modal("hide");
      }
    }

    function onClickDuplicateEditedBooking() {
      if ($(this).closest(".sln-duplicate-booking--disabled").length > 0) {
        return false;
      }

      var $editor = $(".booking-editor");
      bookingCopy = "clone";
      bookingId = $("#post_ID", window.frames[0].document).val();
      $editor.off("load.hide_modal").on("load.hide_modal", onLoadAfterSubmit);
      $editor
        .off("load.dismiss_spinner")
        .on("load.dismiss_spinner", onLoadDismissSpinner);

      try {
        var validateBooking = window.frames[0].sln_validateBooking;
      } catch (e) {
        var validateBooking = window.frames[1].sln_validateBooking;
      }

      if (validateBooking()) {
        launchLoadingSpinner();
        var href = $editor.contents().find(".submitduplicate").attr("href");
        $.get(href).success(function (data) {
          $("#sln-booking-editor-modal").modal("hide");
          setTimeout(function () {
            show_modal_booking_editor();
            $("[data-action=duplicate-edited-booking]").hide();
          }, 1000);
        });
      }
    }
    function onChangeTimes(event) {
      var times = parseInt($(this).val());

      let dateStr = $("#_sln_booking_date", window.frames[0].document)
        .data("value")
        .replace("00:00:00", "")
        .trim(); // '08/07/2025'

      function parseFlexibleDate(dateStr) {
        let parts;

        if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
          parts = dateStr.split("/");
          return new Date(parts[2], parts[1] - 1, parts[0]); // yyyy, mm, dd
        }

        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
          parts = dateStr.split("-");
          return new Date(parts[0], parts[1] - 1, parts[2]); // yyyy, mm, dd
        }

        if (/^\d{2}-\d{2}-\d{4}$/.test(dateStr)) {
          parts = dateStr.split("-");
          return new Date(parts[2], parts[0] - 1, parts[1]); // yyyy, mm, dd
        }
        if (/^\d{2} [A-Za-z]{3} \d{4}$/.test(dateStr)) {
          parts = dateStr.split(" ");
          const monthMap = {
            Jan: 0,
            Feb: 1,
            Mar: 2,
            Apr: 3,
            May: 4,
            Jun: 5,
            Jul: 6,
            Aug: 7,
            Sep: 8,
            Oct: 9,
            Nov: 10,
            Dec: 11,
          };
          let day = parseInt(parts[0]);
          let month = monthMap[parts[1]];
          let year = parseInt(parts[2]);
          return new Date(year, month, day);
        }
        return null;
      }

      let date = parseFlexibleDate(dateStr);

      if (date && !isNaN(date)) {
        date.setDate(date.getDate() + 7 * times);

        var newDateStr =
          String(date.getDate()).padStart(2, "0") +
          "/" +
          String(date.getMonth() + 1).padStart(2, "0") +
          "/" +
          date.getFullYear();

        $(".time_until .time_date").text(newDateStr);
      } else {
        console.error("wrong date: " + dateStr);
      }
    }
    function onClickCloneEditedBooking() {
      if ($(this).closest(".sln-duplicate-booking--disabled").length > 0) {
        return false;
      }
      if ($("[data-action=clone-edited-booking].confirm").length == 0) {
        let dateStr = $("#_sln_booking_date", window.frames[0].document)
          .data("value")
          .replace("00:00:00", "")
          .trim(); // '08/07/2025'

        function parseFlexibleDate(dateStr) {
          let parts;

          if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
            parts = dateStr.split("/");
            return new Date(parts[2], parts[1] - 1, parts[0]); // yyyy, mm, dd
          }

          if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
            parts = dateStr.split("-");
            return new Date(parts[0], parts[1] - 1, parts[2]); // yyyy, mm, dd
          }

          if (/^\d{2}-\d{2}-\d{4}$/.test(dateStr)) {
            parts = dateStr.split("-");
            return new Date(parts[2], parts[0] - 1, parts[1]); // yyyy, mm, dd
          }
          if (/^\d{2} [A-Za-z]{3} \d{4}$/.test(dateStr)) {
            parts = dateStr.split(" ");
            const monthMap = {
              Jan: 0,
              Feb: 1,
              Mar: 2,
              Apr: 3,
              May: 4,
              Jun: 5,
              Jul: 6,
              Aug: 7,
              Sep: 8,
              Oct: 9,
              Nov: 10,
              Dec: 11,
            };
            let day = parseInt(parts[0]);
            let month = monthMap[parts[1]];
            let year = parseInt(parts[2]);
            return new Date(year, month, day);
          }
          return null;
        }

        let date = parseFlexibleDate(dateStr);

        if (date && !isNaN(date)) {
          date.setDate(date.getDate() + 7);

          var newDateStr =
            String(date.getDate()).padStart(2, "0") +
            "/" +
            String(date.getMonth() + 1).padStart(2, "0") +
            "/" +
            date.getFullYear();

          $(".time_until .time_date").text(newDateStr);
        } else {
          console.error("wrong date: " + dateStr);
        }

        $("[data-action=clone-edited-booking]").text(
          $("[data-action=clone-edited-booking]").data("confirm"),
        );
        $("[data-action=clone-edited-booking]").addClass("confirm");
        $('[data-dismiss="modal"]').addClass("hide-important");
        $('[data-action="delete-edited-booking"]').addClass("hide-important");
        $(".clone-info").show();
        return false;
      }

      var $editor = $(".booking-editor");
      bookingCopy = "duplicate";
      bookingId = $("#post_ID", window.frames[0].document).val();
      var unit_times = $(".clone-info input").val();
      $editor.off("load.hide_modal").on("load.hide_modal", onLoadAfterSubmit);
      $editor
        .off("load.dismiss_spinner")
        .on("load.dismiss_spinner", onLoadDismissSpinner);

      try {
        var validateBooking = window.frames[0].sln_validateBooking;
      } catch (e) {
        var validateBooking = window.frames[1].sln_validateBooking;
      }

      if (validateBooking()) {
        var data =
          "&action=salon&method=DuplicateClone&bookingId=" +
          bookingId +
          "&unit=" +
          unit_times +
          "&security=" +
          salon.ajax_nonce;
        launchLoadingSpinner();
        $.ajax({
          url: salon.ajax_url,
          data: data,
          method: "POST",
          dataType: "json",
          success: function (data) {
            location.reload();
          },
        });
      }
    }

    function onLoadDismissSpinner() {
      cancelLoadingSpinner();
    }

    function onLoadAfterSubmit() {
      $("#sln-booking-editor-modal").modal("hide");
    }

    function launchLoadingSpinner() {
      var $modal = $("#sln-booking-editor-modal");
      if ($modal.find(".sln-booking-editor--wrapper").length) {
        $modal.find(".sln-booking-editor--wrapper--sub").css("opacity", "0");
        //$modal
        //	.find(".sln-booking-editor--wrapper")
        //	.addClass("sln-booking-editor--wrapper--loading");
        $modal
          .find("#sln-modalloading")
          .removeClass("sln-modalloading--inactive");
      }
    }

    function launchLoadingSpinnerSaving() {
      var $modal = $("#sln-booking-editor-modal");
      if ($modal.find(".sln-booking-editor--wrapper").length) {
        $modal.find(".sln-booking-editor--wrapper--sub").css("opacity", "0");
        //$modal
        //	.find(".sln-booking-editor--wrapper")
        //	.addClass("sln-booking-editor--wrapper--loading");
        $modal
          .find("#sln-modalloading")
          .removeClass("sln-modalloading--inactive")
          .addClass("sln-modalloading--saving");
        setTimeout(function () {
          $modal
            .find("#sln-modalloading__inner")
            .addClass("sln-modalloading--saved");
        }, 500);
      }
    }
    function cancelLoadingSpinner() {
      var $modal = $("#sln-booking-editor-modal");
      if ($modal.find(".sln-booking-editor--wrapper").length) {
        $modal.find(".sln-booking-editor--wrapper--sub").css("opacity", "1");
        //$modal
        //	.find(".sln-booking-editor--wrapper")
        //	.removeClass("sln-booking-editor--wrapper--loading");
        $modal.find("#sln-modalloading").addClass("sln-modalloading--inactive");
        setTimeout(function () {
          $modal
            .find("#sln-modalloading")
            .removeClass("sln-modalloading--saving");
          $modal
            .find("#sln-modalloading__inner")
            .removeClass("sln-modalloading--saved");
        }, 300);
      }
    }

    const calendarDays = document.querySelectorAll(
      ".day-event-item__calendar-day",
    );

    calendarDays.forEach((calendarDay) => {
      const cardId = calendarDay.getAttribute("data-card-id");

      const header = calendarDay.querySelector(
        ".day-event-item__calendar-day__header",
      );

      if (header) {
        const checkServId = header.getAttribute("data-checkserv");

        if (cardId === checkServId) {
          calendarDay.style.borderBottom = "none";
        }
      }
    });

    $(".day-event-item__calendar-day .sln-icon--plus-circle")
      .off("click")
      .on("click", function (event, triggered) {
        var dayEvent = $(event.target).closest(".day-event");
        var md = $(event.target).siblings(".more_details");
        var duration15 = $(this).parent().find(".duration-15");
        var dayContent = $(this).parent().find(".service_wrapper");
        if (!triggered) {
          $(".more_details").each(function () {
            if ($(this).css("display") != "none" && $(this)[0] !== md[0]) {
              $(this).siblings(".sln-icon--plus-circle").trigger("click", true);
            }
          });
        }
        if (duration15.length) {
          $(duration15).each(function () {
            if ($(this).css("display") != "none") {
              $(this).hide();
              $(this).parent().addClass("duration-15__wrapper--closed");
            } else {
              $(this).show();
              $(this).parent().removeClass("duration-15__wrapper--closed");
            }
          });
        }
        if (md.css("display") == "none") {
          var neededHeight =
            230 +
            ($(event.target).siblings(".service_wrapper").outerHeight(true) ||
              0);
          if (dayEvent.height() < neededHeight) {
            dayEvent.data("height", dayEvent.height());
            dayEvent.height(neededHeight);
          }
          dayEvent.css("z-index", 1001);
        } else {
          dayEvent.height(dayEvent.data("height"));
          dayEvent.removeData("height");
          dayEvent.css(
            "z-index",
            dayEvent.hasClass("day-event-main-block") ? 1000 : 999,
          );
        }
        md.toggle();
        $(this).toggleClass("rotate");
      });
    if ($(".duration-15").length) {
      $(".duration-15").each(function () {
        3;
        $(this)
          .parent()
          .addClass("duration-15__wrapper duration-15__wrapper--closed");
      });
    }

    // Modern tooltip delete confirmation
    $(document)
      .off("click", ".sln-tooltip-confirm-yes")
      .on("click", ".sln-tooltip-confirm-yes", function (event) {
        event.preventDefault();
        var bookingId = $(this).data("booking-id");
        var url =
          salon.ajax_url + "&action=salon&method=deleteBooking&id=" + bookingId;
        $.get(url).done(function () {
          if (window.slnTooltipManager) {
            window.slnTooltipManager.hideTooltip();
          }
          // Refresh calendar
          if (typeof calendar !== "undefined" && calendar.view) {
            calendar.view();
          }
        });
      });

    // Delete confirmation "Yes, delete" button handler
    $(document)
      .off("click", ".sln-dtn-danger-tooltip")
      .on("click", ".sln-dtn-danger-tooltip", function (event) {
        event.preventDefault();

        // Get the URL from the clicked link (not event.target which might be child element)
        var url = $(this).attr("href");

        if (!url || url === "#") {
          console.error("❌ No delete URL found!");
          return;
        }

        $.get(url)
          .done(function () {
            // Hide tooltip if TooltipManager exists
            if (window.slnTooltipManager) {
              window.slnTooltipManager.hideTooltip();
            }

            // Refresh calendar
            if (typeof calendar !== "undefined" && calendar.view) {
              calendar.view();
            }
          })
          .fail(function (xhr, status, error) {
            console.error("❌ Delete failed:", error);
          });
      });

    $(document)
      .off("click", ".sln-btn-duplicate")
      .on("click", ".sln-btn-duplicate", function (event) {
        $('*[data-toggle="tooltip"]').tooltip("hide");
        event.preventDefault();
        bookingCopy = "copy";
        bookingId = $(this).data("bookingid");
        show_modal_booking_editor();

        $("[data-action=duplicate-edited-booking]").hide();
      });

    $(document)
      .off("click", ".sln-dtn-close-tooltip")
      .on("click", ".sln-dtn-close-tooltip", function (event) {
        event.preventDefault();
        $(".sln-confirm-delete-tooltip").css("display", "none");
      });

    $(document)
      .off("click", ".sln-trash-icon-tooltip")
      .on("click", ".sln-trash-icon-tooltip", function (event) {
        event.preventDefault();
        $("[data-action=duplicate-edited-booking]").show();

        if ($(this).closest(".sln-free-version").length) {
          return false;
        }

        $(".sln-confirm-delete-tooltip").css("display", "block");
        $(".sln-confirm-delete-tooltip").css("color", "black");
        $(".sln-confirm-delete-tooltip").css("font-size", "1rem");
      });

    // Handler for Edit/Pen button in new tooltip system
    $(document)
      .off("click", ".sln-edit-icon-tooltip")
      .on("click", ".sln-edit-icon-tooltip", function (event) {
        event.preventDefault();

        // Hide tooltip (if TooltipManager exists)
        if (window.slnTooltipManager) {
          window.slnTooltipManager.hideTooltip();
        }

        // Get booking ID from data attribute
        var bookingid = $(this).data("bookingid");

        // Reset modal state
        $("[data-action=clone-edited-booking]").text(
          $("[data-action=clone-edited-booking]").data("clone"),
        );
        $("[data-action=clone-edited-booking]").removeClass("confirm");
        $('[data-action="save-edited-booking"]').removeClass("hide-important");
        $('[data-action="delete-edited-booking"]').removeClass(
          "hide-important",
        );
        $(".clone-info").hide();
        $("[data-action=duplicate-edited-booking]").show();

        // Set booking ID and open modal
        bookingDate = bookingTime = undefined;
        bookingId = bookingid;
        show_modal_booking_editor();
      });

    // Duplicate button handler removed per client feedback
    // Feature has been completely removed from the system

    $(document)
      .off("click", ".sln-no-show-icon-tooltip")
      .on("click", ".sln-no-show-icon-tooltip", function (event) {
        event.preventDefault();

        // Check if button is disabled (free version)
        if ($(this).hasClass("sln-tooltip-action--disabled")) {
          return false;
        }

        var bookingid = $(this).data("bookingid");
        var elem = $(this);
        var currentNoShow = $(this).data("no-show");

        // Target the booking card element using data-event-id attribute
        var targetSelector =
          '.day-highlight[data-event-id="' + bookingid + '"]';

        // Use unified no-show toggle function
        window.slnToggleNoShow({
          bookingId: bookingid,
          currentNoShow: currentNoShow,
          nonce: salon.ajax_nonce,
          $button: elem,
          onSuccess: function (data) {
            // Toggle no-show class on booking card
            if (data.noShow === 1) {
              $(targetSelector).addClass("no-show");
            } else {
              $(targetSelector).removeClass("no-show");
            }

            // Update no-show count in booking status summary
            var currentCount = parseInt($("#status-noshow").text()) || 0;
            if (data.noShow === 1) {
              $("#status-noshow").text(currentCount + 1);
            } else {
              $("#status-noshow").text(Math.max(0, currentCount - 1));
            }

            // Update the chart with the new no-show count
            updateChartFromCurrentSummary();
          },
        });
      });

    $(".booking_tool_item .sln-icon--user-check")
      .off("click")
      .on("click", function (event) {
        event.preventDefault();
        if ($(this).closest(".sln-free-version").length) {
          return false;
        }
        var eventItem = $(event.target).closest(".event-item");
        var bookingId = eventItem.data("event-id");
        $.ajax({
          url:
            salon.ajax_url +
            "&action=salon&method=SetBookingOnProcess&id=" +
            bookingId,
          type: "POST",
          success: function ($data) {
            var iconCheckmark = $(
              '.event-item[data-event-id="' +
                bookingId +
                '"] .sln-icon--checkmark',
            );
            if (!$data["on_process"]) {
              if (!iconCheckmark.hasClass("hide")) {
                iconCheckmark.addClass("hide");
              }
            } else {
              iconCheckmark.removeClass("hide");
            }
          },
        });
      });
  };

  Calendar.prototype._update_week = function () {
    var self = this;
    $(".cal-day-filter").removeClass("hide");
    setTimeout(function () {
      self._update_day_prepare_sln_booking_editor();
    }, 500);
    sln_ProFeatureTooltip();
  };

  Calendar.prototype._update_year = function () {
    this._update_month_year();
  };

  Calendar.prototype._update_month = function () {
    this._update_month_year();

    $(".cal-day-filter").addClass("hide");
    $(".sln-free-locked-slots").addClass("hide");

    var self = this;

    var week = $(document.createElement("div")).attr("id", "cal-week-box");
    var start =
      this.options.position.start.getFullYear() +
      "-" +
      this.options.position.start.getMonthFormatted() +
      "-";
    $(".cal-month-box .cal-row-fluid")
      .on("mouseenter", function () {
        var p = new Date(self.options.position.start);
        var child = $(".cal-cell1:first-child .cal-month-day", this);
        var day = child.hasClass("cal-month-first-row")
          ? 1
          : parseInt($("[data-cal-date]", child).text());
        p.setDate(day);
        day = day < 10 ? "0" + day : day;
        week.html(self.locale.week.format(p.getWeek()));
        week
          .attr("data-cal-week", start + day)
          .show()
          .appendTo(child);
      })
      .on("mouseleave", function () {
        week.hide();
      });

    week.on("click", function () {
      self.options.day = $(this).data("cal-week");
      self.view("week");
    });

    $("a.event").on("mouseenter", function () {
      $('a[data-event-id="' + $(this).data("event-id") + '"]')
        .closest(".cal-cell1")
        .addClass("day-highlight dh-" + $(this).data("event-class"));
    });
    $("a.event").on("mouseleave", function () {
      $("div.cal-cell1").removeClass(
        "day-highlight dh-" + $(this).data("event-class"),
      );
    });
  };

  Calendar.prototype._update_month_year = function () {
    if (!this.options.views[this.options.view].slide_events) {
      return;
    }
    var self = this;
    var activecell = 0;
    var downbox = $(document.createElement("div"))
      .attr("id", "cal-day-tick")
      .html(
        '<i class="icon-chevron-down glyphicon glyphicon-chevron-down"></i>',
      );

    $(".cal-month-day, .cal-year-box .span3")
      .on("mouseenter", function () {
        if ($(".events-list", this).length == 0) return;
        downbox.show().appendTo(this);
      })
      .on("mouseleave", function () {
        downbox.hide();
      })
      .on("click", function (event) {
        if ($(".events-list", this).length == 0) return;
        if ($(this).children("[data-cal-date]").text() == self.activecell)
          return;
        showEventsList(event, downbox, slider, self);
      });

    var slider = $(document.createElement("div")).attr("id", "cal-slide-box");
    slider.hide().on("click", function (event) {
      event.stopPropagation();
    });

    downbox.on("click", function (event) {
      showEventsList(event, $(this), slider, self);
    });

    if (self.activecell) {
      $(".cal-month-day.cal-day-inmonth, .cal-year-box .span3").each(
        function () {
          if ($(this).find("[data-cal-date]").text() === self.activecell) {
            downbox.show().appendTo(this);
            downbox.trigger("click");
          }
        },
      );
    }

    setTimeout(function () {
      self._update_day_prepare_sln_booking_editor();
    }, 500);
  };

  Calendar.prototype.getEventsBetween = function (start, end) {
    var events = [];

    $.each(this.options.events, function () {
      var s = this.start + new Date().getTimezoneOffset() * 60 * 1000;
      var e = this.end + new Date().getTimezoneOffset() * 60 * 1000;

      if (this.start == null) {
        return true;
      }
      var event_end = e || s;
      if (parseInt(s) < end && parseInt(event_end) >= start) {
        events.push(this);
      }
    });
    return events;
  };

  Calendar.prototype.applyAdvancedFilters = function (events) {
    var _events = [];

    var _customer = parseInt(this.options._customer);
    var _services = Array.isArray(this.options._services)
      ? this.options._services
      : [];

    if (_customer || _services.length) {
      $.each(events, function () {
        var add = true;
        if (_customer && _customer !== this.customer_id) {
          add = false;
        }
        if (add && _services.length) {
          var intersect = this.services.filter(function (n) {
            return _services.indexOf(n) !== -1;
          });
          if (!intersect.length) {
            add = false;
          }
        }

        if (add) {
          _events.push(this);
        }
      });
    } else {
      _events = events;
    }

    return _events;
  };

  function showEventsList(event, that, slider, self) {
    event.stopPropagation();

    // NICO
    $(".selected").removeClass("selected");

    var that = $(that);
    var cell = that.closest(".cal-cell");
    var row = cell.closest(".cal-before-eventlist");
    var tick_position = cell.data("cal-row");
    var selectedDay = cell.children(".cal-month-day");

    slider.slideUp("fast", function () {
      // NICO
      cell.addClass("selected");
      slider.html(that.parent().find(".event-list--sliders").html());
      row.after(slider);
      self.activecell = $("[data-cal-date]", cell).text();
      slider.attr("data-cal-date", $("[data-cal-date]", cell).data("cal-date"));

      slider.slideDown("fast", function () {
        $("body").one("click", function () {
          slider.slideUp("fast");
          // NICO
          cell.removeClass("selected");
          self.activecell = 0;
        });
      });
    });

    $("a.event-item").on("mouseenter", function () {
      $('a[data-event-id="' + $(this).data("event-id") + '"]')
        .closest(".cal-cell1")
        .addClass("day-highlight dh-" + $(this).data("event-class"));
    });
    $("a.event-item").on("mouseleave", function () {
      $("div.cal-cell1").removeClass(
        "day-highlight dh-" + $(this).data("event-class"),
      );
    });
  }

  function getEasterDate(year, offsetDays) {
    var a = year % 19;
    var b = Math.floor(year / 100);
    var c = year % 100;
    var d = Math.floor(b / 4);
    var e = b % 4;
    var f = Math.floor((b + 8) / 25);
    var g = Math.floor((b - f + 1) / 3);
    var h = (19 * a + b - d - g + 15) % 30;
    var i = Math.floor(c / 4);
    var k = c % 4;
    var l = (32 + 2 * e + 2 * i - h - k) % 7;
    var m = Math.floor((a + 11 * h + 22 * l) / 451);
    var n0 = h + l + 7 * m + 114;
    var n = Math.floor(n0 / 31) - 1;
    var p = (n0 % 31) + 1;
    return new Date(year, n, p + (offsetDays ? offsetDays : 0), 0, 0, 0);
  }

  $.fn.calendar = function (params) {
    return new Calendar(params, this);
  };
  Beacon("once", "ready", () => {
    $("#beacon-container .BeaconContainer").prepend(
      '<a href="#nogo" class="sln-helpchat__close"><span class="sr-only">Close help chat</span></a>',
    );
  });
  $("#sln-note-phone-device .sln-popup--close").on("click", function () {
    $(this).closest("#sln-note-phone-device").hide();
  });
  $(document).on("click", ".sln-helpchat__close", function () {
    Beacon("close");
  });

  // Function to update booking status summary
  function updateBookingStatusSummary(statusCounts) {
    var $summary = $("#sln-booking-status-summary");

    if (!statusCounts || !$summary.length) {
      return;
    }

    // Skip updates for FREE version - keep zeros and static SVG chart
    // Check if this is a disabled PRO feature
    if ($summary.hasClass("sln-profeature--disabled")) {
      return;
    }

    // Update the counts
    $("#status-paid-confirmed").text(statusCounts.paid_confirmed || 0);
    $("#status-pay-later").text(statusCounts.pay_later || 0);
    $("#status-pending").text(statusCounts.pending || 0);
    $("#status-cancelled").text(statusCounts.cancelled || 0);
    $("#status-noshow").text(statusCounts.noshow || 0);

    // Show the summary if it's hidden
    $summary.show();

    // Render the doughnut chart
    renderBookingStatusChart(statusCounts);
  }

  // Track Google Charts loading state
  var googleChartsLoaded = false;
  var googleChartsLoading = false;
  var pendingChartData = null;

  // Helper function to update chart from current DOM values
  function updateChartFromCurrentSummary() {
    // Skip for FREE version
    if ($("#sln-booking-status-summary").hasClass("sln-profeature--disabled")) {
      return;
    }

    var statusCounts = {
      paid_confirmed: parseInt($("#status-paid-confirmed").text()) || 0,
      pay_later: parseInt($("#status-pay-later").text()) || 0,
      pending: parseInt($("#status-pending").text()) || 0,
      cancelled: parseInt($("#status-cancelled").text()) || 0,
      noshow: parseInt($("#status-noshow").text()) || 0,
    };
    renderBookingStatusChart(statusCounts);
  }

  // Function to render booking status doughnut chart
  function renderBookingStatusChart(statusCounts) {
    if (!statusCounts || typeof google === "undefined") {
      return;
    }

    // Helper function to extract translated label from DOM
    function getTranslatedLabel(selector, fallbackLabel) {
      var element = $(selector);
      if (element.length) {
        // Get the text content and remove the number (strong tag content)
        var fullText = element.text().trim();
        var numberText = element.find("strong").text().trim();
        // Remove the number from the beginning and trim any extra whitespace
        var label = fullText.replace(numberText, "").trim();
        return label || fallbackLabel;
      }
      return fallbackLabel;
    }

    // Helper function to draw the chart
    function drawChart() {
      var chartElement = document.getElementById("sln-booking-status-chart");
      if (!chartElement) {
        return;
      }

      // Define colors and labels for consistency
      // Labels are dynamically extracted from the DOM to use server-side translations
      var statusData = [
        {
          label: getTranslatedLabel(
            ".sln-status-summary__item--paid-confirmed",
            "Paid/Confirmed",
          ),
          value: statusCounts.paid_confirmed || 0,
          color: "#6AA84F",
          selector: ".sln-status-summary__item--paid-confirmed",
        },
        {
          label: getTranslatedLabel(
            ".sln-status-summary__item--pay-later",
            "Pay Later",
          ),
          value: statusCounts.pay_later || 0,
          color: "#6D9EEB",
          selector: ".sln-status-summary__item--pay-later",
        },
        {
          label: getTranslatedLabel(
            ".sln-status-summary__item--pending",
            "Pending",
          ),
          value: statusCounts.pending || 0,
          color: "#F58120",
          selector: ".sln-status-summary__item--pending",
        },
        {
          label: getTranslatedLabel(
            ".sln-status-summary__item--cancelled",
            "Cancelled",
          ),
          value: statusCounts.cancelled || 0,
          color: "#E54747",
          selector: ".sln-status-summary__item--cancelled",
        },
        {
          label: getTranslatedLabel(
            ".sln-status-summary__item--noshow",
            "No Show",
          ),
          value: statusCounts.noshow || 0,
          color: "#1B1B21",
          selector: ".sln-status-summary__item--noshow",
        },
      ];

      // Create simple DataTable without HTML tooltips
      var data = google.visualization.arrayToDataTable([
        ["Status", "Count"],
        [statusData[0].label, statusData[0].value],
        [statusData[1].label, statusData[1].value],
        [statusData[2].label, statusData[2].value],
        [statusData[3].label, statusData[3].value],
        [statusData[4].label, statusData[4].value],
      ]);

      var options = {
        pieHole: 0.45,
        pieSliceText: "none",
        colors: [
          statusData[0].color,
          statusData[1].color,
          statusData[2].color,
          statusData[3].color,
          statusData[4].color,
        ],
        legend: { position: "none" },
        chartArea: { width: "75%", height: "75%" },
        backgroundColor: "transparent",
        // fontSize: 14,
        tooltip: { trigger: "none" }, // Disable Google's tooltip
      };

      var chart = new google.visualization.PieChart(chartElement);
      chart.draw(data, options);

      // Create or get custom fixed tooltip element
      var tooltipId = "sln-chart-custom-tooltip";
      var customTooltip = document.getElementById(tooltipId);

      if (!customTooltip) {
        customTooltip = document.createElement("div");
        customTooltip.id = tooltipId;
        chartElement.parentNode.style.position = "relative"; // Ensure parent is positioned
        chartElement.parentNode.appendChild(customTooltip);
      }

      // Function to update tooltip content
      function updateTooltip(statusInfo) {
        if (!statusInfo) {
          customTooltip.style.display = "none";
          return;
        }

        // Calculate total and percentage
        var total = statusData.reduce(function (sum, item) {
          return sum + item.value;
        }, 0);
        var percentage =
          total > 0 ? Math.round((statusInfo.value / total) * 100) : 0;

        // Use CSS classes with dynamic color values
        customTooltip.innerHTML =
          '<div class="sln-chart-custom-tooltip__inner" style="border-color: ' +
          statusInfo.color +
          "; color: " +
          statusInfo.color +
          ';">' +
          '<div class="sln-chart-custom-tooltip__label" style="color: ' +
          statusInfo.color +
          ';">' +
          statusInfo.label +
          "</div>" +
          "<div>" +
          '<strong class="sln-chart-custom-tooltip__value">' +
          percentage +
          "%</strong> " +
          '<span class="sln-chart-custom-tooltip__count">(' +
          statusInfo.value +
          ")</span>" +
          "</div>" +
          "</div>";

        customTooltip.style.display = "block";
      }

      // Add event listeners for hover
      google.visualization.events.addListener(
        chart,
        "onmouseover",
        function (e) {
          if (e.row !== null && e.row !== undefined) {
            var statusInfo = statusData[e.row];
            updateTooltip(statusInfo);

            // Add active class to corresponding status summary item
            if (statusInfo && statusInfo.selector) {
              $(statusInfo.selector).addClass("active");
            }

            // Add tooltip visible class to container
            $(".sln-booking-status-summary").addClass("chart-tooltip-visible");
          }
        },
      );

      google.visualization.events.addListener(
        chart,
        "onmouseout",
        function (e) {
          updateTooltip(null);

          // Remove active class from all status summary items
          $(".sln-status-summary__item").removeClass("active");

          // Remove tooltip visible class from container
          $(".sln-booking-status-summary").removeClass("chart-tooltip-visible");
        },
      );
    }

    // Check if Google Charts visualization library is already available
    if (google.visualization && google.visualization.PieChart) {
      googleChartsLoaded = true;
      drawChart();
    } else if (googleChartsLoading) {
      // Currently loading, just update pending data
      pendingChartData = statusCounts;
    } else {
      // Need to load Google Charts for the first time
      googleChartsLoading = true;
      pendingChartData = statusCounts;
      google.charts.load("current", { packages: ["corechart"] });
      google.charts.setOnLoadCallback(function () {
        googleChartsLoaded = true;
        googleChartsLoading = false;
        if (pendingChartData) {
          statusCounts = pendingChartData;
          pendingChartData = null;
        }
        drawChart();
      });
    }
  }
})(jQuery);
