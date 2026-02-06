<template>
  <div class="calendar">
    <Datepicker
        v-model="selectedDate"
        inline
        autoApply
        noSwipe
        :locale="this.getLabel('calendarLocale')"
        :enableTimePicker="false"
        :monthChangeOnScroll="false"
        @updateMonthYear="handleMonthYear"
    >
      <template #day="{ day, date }">
        <template v-if="isDayWithBookings(date)">
          <div class="day day-with-bookings">{{ day }}</div>
        </template>
        <template v-else-if="isDayFullBooked(date)">
          <div class="day day-full-booked">{{ day }}</div>
        </template>
        <template v-else-if="isAvailableBookings(date)">
          <div class="day day-available-book">{{ day }}</div>
        </template>
        <template v-else-if="isHoliday(date)">
          <div class="day day-holiday">{{ day }}</div>
        </template>
        <template v-else>
          <div class="day day-disable-book">{{ day }}</div>
        </template>
      </template>
    </Datepicker>
    <div v-if="isLoading" class="spinner-wrapper"/>
    <b-spinner v-if="isLoading" variant="primary"/>
  </div>
</template>

<script>
export default {
  name: 'BookingCalendar',

  props: {
    modelValue: {
      type: Date,
      required: true
    },
    availabilityStats: {
      type: Array,
      default: () => []
    },
    isLoading: {
      type: Boolean,
      default: false
    }
  },

  emits: ['update:modelValue', 'month-year-update'],

  computed: {
    selectedDate: {
      get() {
        return this.modelValue;
      },
      set(value) {
        this.$emit('update:modelValue', value);
      }
    }
  },

  mounted() {
    this.$nextTick(() => {
      const calendar = document.querySelector(".dp__calendar");
      const spinnerWrapper = document.querySelector(".spinner-wrapper");
      const spinner = document.querySelector(".calendar .spinner-border");

      if (calendar && spinnerWrapper && spinner) {
        calendar.appendChild(spinnerWrapper);
        calendar.appendChild(spinner);
      }
    });
  },

  methods: {
    handleMonthYear({year, month}) {
      this.$emit('month-year-update', {year, month});
    },

    isDayWithBookings(date) {
      return this.availabilityStats.some(
          stat =>
              stat.date === this.dateFormat(date, "YYYY-MM-DD") &&
              stat.data?.bookings > 0
      );
    },

    isAvailableBookings(date) {
      return this.availabilityStats.some(
          stat =>
              stat.date === this.dateFormat(date, "YYYY-MM-DD") &&
              stat.available &&
              !stat.full_booked
      );
    },

    isDayFullBooked(date) {
      return this.availabilityStats.some(
          stat =>
              stat.date === this.dateFormat(date, "YYYY-MM-DD") &&
              stat.full_booked
      );
    },

    isHoliday(date) {
      return this.availabilityStats.some(
          stat =>
              stat.date === this.dateFormat(date, "YYYY-MM-DD") &&
              stat.error?.type === "holiday_rules"
      );
    }
  }
};
</script>

<style scoped>
.calendar {
  margin-top: 1.5rem;
}

.calendar :deep(.dp__menu) {
  margin: 0 auto;
}

:deep(.dp__cell_inner) {
  --dp-hover-color: #6983862B;
  height: auto;
  width: auto;
  padding: 0;
  border-radius: 50%;
}

:deep(.dp__calendar_row) {
  margin: 10px 0;
  gap: 10px;
}

:deep(.dp__calendar_header) {
  gap: 9px;
}

:deep(.dp__calendar_header_item) {
  height: 30px;
  width: 45px;
}

:deep(.dp__month_year_select) {
  width: 100%;
  pointer-events: none;
}

:deep(.dp__month_year_select + .dp__month_year_select) {
  display: none;
}

:deep(.dp__cell_inner), :deep(.dp__today), :deep(.dp__menu), :deep(.dp__menu:focus) {
  border: none;
}

:deep(.dp__today:not(.dp__active_date)) .day {
  border-color: green;
  color: green;
}

:deep(.dp__calendar_header_separator) {
  height: 0;
}

:deep(.dp__active_date) {
  background: none;
}

:deep(.dp__active_date) .day {
  background: #04409f;
  border-color: #fff;
  color: #fff;
}

:deep(.dp__active_date) .day.day-holiday {
  background: #a78a8a;
  border-color: #9f04048f;
}

:deep(.dp__active_date) .day.day-with-bookings::before {
  background-color: #fff;
}

.day {
  display: flex;
  align-items: center;
  text-align: center;
  justify-content: center;
  border-radius: 30px;
  font-weight: 500;
  font-size: 16px;
  line-height: 1;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 2px solid #C7CED9;
  box-sizing: border-box;
  position: relative;
}

.day-available-book, .day-with-bookings {
  color: #04409f;
  border-color: #04409f;
}

.day-with-bookings::before {
  content: '';
  position: absolute;
  left: 50%;
  bottom: 4px;
  transform: translateX(-50%);
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #04409f;
}

.day-disable-book, .day-full-booked {
  border-color: #C7CED9;
  color: #c7ced9;
}

.day-holiday {
  color: #9F04048E;
  border-color: #9F04048F;
}

.spinner-wrapper {
  width: 100%;
  height: 100%;
  position: absolute;
  background-color: #e0e0e0d1;
  opacity: 0.5;
  inset: 0;
  border-radius: 12px;
}

.calendar .spinner-border {
  position: absolute;
  top: 45%;
  left: 45%;
}

@media screen and (max-width: 450px) {
  :deep(.dp__calendar_row) {
    margin: 5px 0;
    gap: 5px;
  }

  :deep(.dp__calendar_header) {
    gap: 0;
  }

  .day {
    width: 38px;
    height: 38px;
  }
}

@media screen and (max-width: 361px) {
  :deep(.dp__calendar_header_item) {
    width: 37px;
  }

  .day {
    width: 33px;
    height: 33px;
  }
}
</style>