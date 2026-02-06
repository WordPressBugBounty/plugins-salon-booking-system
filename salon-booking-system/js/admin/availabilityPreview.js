"use strict";

class SalonAvailabilityPreview {
  constructor(config) {
    this.settings = config.settings;
    this.$wrapper = jQuery(config.wrapper);
    this.$table = this.$wrapper.find('.sln-availability-preview-table');
    this.$loading = this.$wrapper.find('.sln-availability-preview-loading');
    this.updateTimeout = null;
    this.init();
  }

  // initializes the preview if DOM is ready
  init() {
    if (!this.$table.length) return;
    this.bindEvents();
    this.updatePreview();
  }

  // binds DOM input events to trigger preview updates
  bindEvents() {
    const self = this;

    const $rulesWrapper = this.$wrapper.find('.sln-booking-rules-wrapper');
    if (!$rulesWrapper.length) return;

    $rulesWrapper.off('change.preview input.preview keyup.preview')
      .on('change.preview input.preview keyup.preview',
        'input[type="checkbox"], input[type="text"], select, .slider-time-input-from, .slider-time-input-to',
        function () {
          clearTimeout(self.updateTimeout);
          self.updateTimeout = setTimeout(() => {
            self.updatePreview();
          }, 300);
        }
      );

    $rulesWrapper.find('.slider-range').off('slide.preview slidechange.preview slidestop.preview')
      .on('slide.preview slidechange.preview slidestop.preview', function () {
        clearTimeout(self.updateTimeout);
        self.updateTimeout = setTimeout(() => {
          self.updatePreview();
        }, 100);
      });

    $rulesWrapper.find('button[data-collection="remove"]').off('click.preview').on('click.preview', function () {
      setTimeout(() => {
        self.updatePreview();
      }, 100);
    });

    $rulesWrapper.find('button[data-collection="addnew"]').off('click.preview').on('click.preview', function () {
      setTimeout(() => {
        if (typeof sln_customSliderRange === 'function') {
          sln_customSliderRange(jQuery, $rulesWrapper.find('.slider-range'));
        }
        self.bindEvents();
        self.updatePreview();
      }, 500);
    });

    $rulesWrapper.find('.sln-disable-second-shift input').off('change.preview').on('change.preview', function () {
      setTimeout(() => {
        self.updatePreview();
      }, 100);
    });
  }

  // performs a full refresh of the opening hours preview
  updatePreview() {
    if (!this.$table.length) return;

    this.showLoading();
    const sessionDuration = this.settings.interval;
    const rules = this.extractRules();

    if (!rules.length) {
      this.$table.html('<div class="preview-message">No rules defined. Add booking rules to see opening hours preview.</div>');
      this.hideLoading();
      return;
    }

    setTimeout(() => {
      const previewData = this.generateAvailabilityData(rules, sessionDuration);
      this.renderTable(previewData);
      this.hideLoading();
    }, 100);
  }

  // displays the loading spinner
  showLoading() {
    this.$loading.show();
    this.$table.hide();
  }

  // hides the loading spinner and shows the table
  hideLoading() {
    this.$loading.hide();
    this.$table.show();
  }

  // reads rules from the DOM input fields and builds rule data
  extractRules() {
    const rules = [];

    const $rulesWrapper = this.$wrapper.find('.sln-booking-rules-wrapper');
    if (!$rulesWrapper.length) return rules;

    $rulesWrapper.find('.sln-booking-rule').each(function () {
      const $rule = jQuery(this);
      const ruleData = {
        days: {},
        shifts: []
      };

      $rule.find('.sln-checkbutton input[type="checkbox"]').each(function () {
        const name = jQuery(this).attr('name');
        if (name && name.includes('[days]')) {
          const dayMatch = name.match(/\[days\]\[(\d+)\]/);
          if (dayMatch && jQuery(this).is(':checked')) {
            ruleData.days[dayMatch[1]] = true;
          }
        }
      });

      const fromInputs = $rule.find('input.slider-time-input-from');
      const toInputs = $rule.find('input.slider-time-input-to');

      fromInputs.each(function (index) {
        const $fromInput = jQuery(this);
        const $toInput = toInputs.eq(index);
        const fromTime = $fromInput.val();
        const toTime = $toInput.val();

        // Only include shifts that are enabled (not disabled) and have valid times
        // Second shift can be disabled via the disable_second_shift checkbox
        if (fromTime && toTime && fromTime !== '00:00' && !$fromInput.is(':disabled') && !$toInput.is(':disabled')) {
          ruleData.shifts.push({from: fromTime, to: toTime});
        }
      });

      if (Object.keys(ruleData.days).length > 0 && ruleData.shifts.length > 0) {
        rules.push(ruleData);
      }
    });

    return rules;
  }

  // builds availability matrix from rules and time slots
  generateAvailabilityData(rules, sessionDuration) {
    const range = this.calculateTimeRange(rules);
    const timeSlots = [];

    // Generate time slots starting from 00:00 (midnight) to match backend behavior
    // Backend uses SLN_Func::getMinutesIntervals() which always starts from 00:00
    // This ensures slots align correctly with opening hours like 9:20, 9:40, etc.
    let currentMinutes = 0;
    const maxMinutes = Math.ceil(range.end) * 60;
    const rangeStartMinutes = Math.floor(range.start * 60);
    const rangeEndMinutes = Math.ceil(range.end * 60);

    while (currentMinutes < maxMinutes) {
      // Only include slots within the visible range
      if (currentMinutes >= rangeStartMinutes && currentMinutes < rangeEndMinutes) {
        const hours = Math.floor(currentMinutes / 60);
        const minutes = currentMinutes % 60;
        timeSlots.push(this.formatTime(hours, minutes));
      }
      currentMinutes += sessionDuration;
    }

    const daysMapping = this.settings.days_mapping || {};
    const weekStart = this.settings.week_start || 1;
    const {orderedDays, orderedDayNumbers} = this.reorderDays(daysMapping, weekStart);
    const availability = {};

    orderedDayNumbers.forEach(dayNumber => {
      availability[dayNumber] = {};
      timeSlots.forEach(time => {
        availability[dayNumber][time] = false;
      });
    });

    rules.forEach(rule => {
      Object.keys(rule.days).forEach(dayNumber => {
        const dayNum = parseInt(dayNumber);
        if (availability[dayNum]) {
          rule.shifts.forEach(shift => {
            const shiftStart = this.timeToMinutes(shift.from);
            const shiftEnd = this.timeToMinutes(shift.to);

            timeSlots.forEach(timeSlot => {
              const slotTime = this.timeToMinutes(timeSlot);
              const slotEnd = slotTime + sessionDuration;

              // A slot is available if the entire session (slot + duration) fits within the shift
              // This matches the backend logic in SLN_Helper_AvailabilityItem::isValidTimeInterval
              if (slotTime >= shiftStart && slotEnd <= shiftEnd) {
                availability[dayNum][timeSlot] = true;
              }
            });
          });
        }
      });
    });

    return {
      days: orderedDays,
      dayNumbers: orderedDayNumbers,
      timeSlots: timeSlots,
      availability: availability,
      timeRange: range
    };
  }

  // calculates the time range
  calculateTimeRange(rules) {
    let earliestStart = null;
    let latestEnd = null;

    rules.forEach(rule => {
      rule.shifts.forEach(shift => {
        const startMinutes = this.timeToMinutes(shift.from);
        const endMinutes = this.timeToMinutes(shift.to);

        if (earliestStart === null || startMinutes < earliestStart) {
          earliestStart = startMinutes;
        }

        if (latestEnd === null || endMinutes > latestEnd) {
          latestEnd = endMinutes;
        }
      });
    });

    if (earliestStart === null || latestEnd === null) {
      return {start: 8, end: 18};
    }

    const startHour = Math.floor(earliestStart / 60);
    const endHour = Math.ceil(latestEnd / 60);
    const bufferHours = 0.5;
    const finalStart = Math.max(0, startHour - bufferHours);
    const finalEnd = Math.min(24, endHour + bufferHours);

    return {
      start: finalStart,
      end: finalEnd
    };
  }

  // reorders days array based on week start setting
  reorderDays(daysMapping, weekStart) {
    const defaultDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const defaultNumbers = [1, 2, 3, 4, 5, 6, 7];

    if (Object.keys(daysMapping).length === 0) {
      return {orderedDays: defaultDays, orderedDayNumbers: defaultNumbers};
    }

    const allDays = [];
    const allNumbers = [];

    for (let i = 1; i <= 7; i++) {
      if (daysMapping[i]) {
        allDays.push(daysMapping[i]);
        allNumbers.push(i);
      }
    }

    // weekStart comes as 0-6 (0=Sunday, 1=Monday, etc.) from SLN_Enum_DaysOfWeek
    // Internal system uses 1-7 (1=Sunday, 2=Monday, etc.) from SLN_Func::getDays()
    // allDays array is 0-indexed: [Sunday, Monday, Tuesday, ...]
    // So weekStart=0 (Sunday) should use startIndex=0, weekStart=1 (Monday) should use startIndex=1, etc.
    const startIndex = weekStart;

    return {
      orderedDays: [...allDays.slice(startIndex), ...allDays.slice(0, startIndex)],
      orderedDayNumbers: [...allNumbers.slice(startIndex), ...allNumbers.slice(0, startIndex)]
    };
  }

  // renders the availability table into the DOM
  renderTable(data) {
    if (!data.timeSlots.length) return;

    let html = '<div class="preview-grid">';
    data.days.forEach(day => {
      html += `<div class="preview-cell header">${day}</div>`;
    });

    data.timeSlots.forEach(timeSlot => {
      data.dayNumbers.forEach((dayNum, index) => {
        const isAvailable = data.availability[dayNum] && data.availability[dayNum][timeSlot];
        const cellClass = isAvailable ? 'available' : 'unavailable';
        const title = `${data.days[index]} ${timeSlot} - ${isAvailable ? 'Available' : 'Not Available'}`;
        html += `<div class="preview-cell ${cellClass}" title="${title}">${timeSlot}</div>`;
      });
    });

    html += '</div>';
    this.$table.html(html);
  }

  // converts "HH:MM" to total minutes
  timeToMinutes(timeStr) {
    if (!timeStr) return 0;
    const parts = timeStr.split(':');
    return parseInt(parts[0]) * 60 + parseInt(parts[1] || 0);
  }

  // converts numeric hours/minutes to "HH:MM" string
  formatTime(hours, minutes) {
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
  }
}

// initializes the preview module once DOM is ready
jQuery(function ($) {
  if (window.sln_availability_preview_config) {
    $('.booking-wrapper').each(function () {
      new SalonAvailabilityPreview({
        wrapper: this,
        settings: window.sln_availability_preview_config
      });
    });
  }
});
