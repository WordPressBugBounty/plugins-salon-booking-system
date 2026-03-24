<template>
  <div class="week-strip">
    <div class="week-nav">
      <button class="nav-arrow" @click="prevWeek">
        <font-awesome-icon icon="fa-solid fa-chevron-left" />
      </button>
      <span class="month-label">{{ monthLabel }}</span>
      <button class="nav-arrow" @click="nextWeek">
        <font-awesome-icon icon="fa-solid fa-chevron-right" />
      </button>
      <button class="expand-btn" @click="$emit('expand')" :title="expandLabel">
        <font-awesome-icon icon="fa-solid fa-calendar-days" />
      </button>
    </div>

    <div class="days-row">
      <div
        v-for="day in weekDays"
        :key="day.iso"
        class="day-cell"
        :class="{
          'day-cell--selected': isSelected(day.date),
          'day-cell--today': isToday(day.date) && !isSelected(day.date)
        }"
        @click="selectDay(day.date)"
      >
        <span class="day-name">{{ day.name }}</span>
        <span class="day-number">{{ day.number }}</span>
        <span
          v-if="hasDot(day.date)"
          class="day-dot"
          :class="{ 'day-dot--white': isSelected(day.date) }"
        ></span>
        <span v-else class="day-dot day-dot--placeholder"></span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'WeekStrip',

  props: {
    modelValue: {
      type: Date,
      required: true,
    },
    availabilityStats: {
      type: Array,
      default: () => [],
    },
  },

  emits: ['update:modelValue', 'month-year-update', 'expand'],

  data() {
    return {
      weekStart: this.getWeekStart(this.modelValue),
    };
  },

  computed: {
    weekDays() {
      const days = [];
      const dayNames = this.getDayNames();
      for (let i = 0; i < 7; i++) {
        const d = new Date(this.weekStart);
        d.setDate(d.getDate() + i);
        days.push({
          date: d,
          iso: d.toISOString().slice(0, 10),
          name: dayNames[i],
          number: d.getDate(),
        });
      }
      return days;
    },

    monthLabel() {
      const locale = this.dayjsLocale(this.getLabel('calendarLocale'));
      return this.moment(this.weekStart).locale(locale).format('MMMM YYYY');
    },

    expandLabel() {
      return this.getLabel('calendarExpandLabel') || 'Open calendar';
    },
  },

  watch: {
    modelValue(newVal) {
      if (!this.weekContains(newVal)) {
        this.weekStart = this.getWeekStart(newVal);
        this.emitMonthYear();
      }
    },
  },

  methods: {
    getWeekStart(date) {
      const d = new Date(date);
      const day = d.getDay();
      const diff = (day === 0 ? -6 : 1 - day);
      d.setDate(d.getDate() + diff);
      d.setHours(0, 0, 0, 0);
      return d;
    },

    getDayNames() {
      const locale = this.dayjsLocale(this.getLabel('calendarLocale'));
      const names = [];
      for (let i = 0; i < 7; i++) {
        const d = new Date(this.weekStart);
        d.setDate(d.getDate() + i);
        names.push(this.moment(d).locale(locale).format('ddd').toUpperCase().slice(0, 3));
      }
      return names;
    },

    isSelected(date) {
      return this.isSameDay(date, this.modelValue);
    },

    isToday(date) {
      return this.isSameDay(date, new Date());
    },

    isSameDay(a, b) {
      return a.getFullYear() === b.getFullYear()
        && a.getMonth() === b.getMonth()
        && a.getDate() === b.getDate();
    },

    weekContains(date) {
      const start = this.weekStart.getTime();
      const end = start + 7 * 24 * 60 * 60 * 1000;
      return date.getTime() >= start && date.getTime() < end;
    },

    hasDot(date) {
      const iso = this.moment(date).format('YYYY-MM-DD');
      return this.availabilityStats.some(
        stat => stat.date === iso && stat.data?.bookings > 0
      );
    },

    selectDay(date) {
      this.$emit('update:modelValue', new Date(date));
      this.emitMonthYear();
    },

    prevWeek() {
      const d = new Date(this.weekStart);
      d.setDate(d.getDate() - 7);
      this.weekStart = d;
      this.emitMonthYear();
    },

    nextWeek() {
      const d = new Date(this.weekStart);
      d.setDate(d.getDate() + 7);
      this.weekStart = d;
      this.emitMonthYear();
    },

    emitMonthYear() {
      this.$emit('month-year-update', {
        year: this.weekStart.getFullYear(),
        month: this.weekStart.getMonth(),
      });
    },

    getLabel(key) {
      return this.$root.labels ? this.$root.labels[key] : key;
    },
  },
};
</script>

<style scoped>
.week-strip {
  background: #FFFFFF;
  border-radius: 16px;
  border: 1px solid #E2E8F0;
  padding: 12px 12px 8px;
  margin-top: 16px;
}

.week-nav {
  display: flex;
  align-items: center;
  margin-bottom: 12px;
  gap: 4px;
}

.month-label {
  flex: 1;
  text-align: center;
  font-size: 15px;
  font-weight: 600;
  color: #0F172A;
  letter-spacing: -0.1px;
}

.nav-arrow {
  background: none;
  border: none;
  color: #64748B;
  padding: 6px 8px;
  cursor: pointer;
  border-radius: 8px;
  font-size: 13px;
  line-height: 1;
  transition: background 0.15s, color 0.15s;
}

.nav-arrow:hover {
  background: #F4F6FA;
  color: #0F172A;
}

.expand-btn {
  background: none;
  border: none;
  color: #64748B;
  padding: 6px 8px;
  cursor: pointer;
  border-radius: 8px;
  font-size: 15px;
  line-height: 1;
  margin-left: 4px;
  transition: background 0.15s, color 0.15s;
}

.expand-btn:hover {
  background: #EFF6FF;
  color: #2563EB;
}

.days-row {
  display: flex;
  justify-content: space-between;
  gap: 2px;
}

.day-cell {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 6px 2px 4px;
  border-radius: 12px;
  cursor: pointer;
  transition: background 0.15s;
  min-width: 0;
  position: relative;
}

.day-cell:hover:not(.day-cell--selected) {
  background: #F4F6FA;
}

.day-cell--selected {
  background: #2563EB;
}

.day-cell--today .day-name,
.day-cell--today .day-number {
  color: #2563EB;
}

.day-cell--selected .day-name,
.day-cell--selected .day-number {
  color: #FFFFFF;
}

.day-name {
  font-size: 10px;
  font-weight: 500;
  color: #94A3B8;
  line-height: 1;
  margin-bottom: 5px;
  letter-spacing: 0.3px;
}

.day-number {
  font-size: 17px;
  font-weight: 600;
  color: #0F172A;
  line-height: 1;
}

.day-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #2563EB;
  margin-top: 4px;
}

.day-dot--white {
  background: #FFFFFF;
}

.day-dot--placeholder {
  background: transparent;
  margin-top: 4px;
}
</style>
