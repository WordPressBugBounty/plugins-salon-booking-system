<template>
  <div 
    ref="bookingCard"
    class="booking-wrapper"
    :class="{ 
      'is-resizing': isResizing,
      'is-saving': isSaving
    }"
  >
    <!-- Loading overlay -->
    <div v-if="isSaving" class="saving-overlay">
      <b-spinner variant="light" small></b-spinner>
    </div>
    
    <div class="booking">
      <div class="customer-info">
        <div class="customer-info-header">
          <span class="customer-name" @click="showDetails">{{ customer }}</span>
          <span class="booking-id">{{ id }}</span>
        </div>
      </div>
      <div class="services-list">
        <div
            class="service-item"
            v-for="(service, index) in booking.services"
            :key="index"
        >
          <span class="service-name">{{ service.service_name }}</span>
          <span class="assistant-name">{{ service.assistant_name }}</span>
        </div>
      </div>

      <div class="booking-actions-bottom">
        <!-- Walk-In Badge -->
        <div v-if="booking.is_walkin" class="walkin-badge" title="Walk-In">
          🚶
        </div>
        
        <button class="booking-actions-menu-dots" @click.stop="toggleActionsMenu">
          &bull;&bull;&bull;
        </button>
      </div>

      <div class="booking-status">
        <span class="status-label">{{ statusLabel }}</span>
      </div>
    </div>
    
    <!-- MOBILE-OPTIMIZED RESIZE HANDLE -->
    <div class="resize-handle" ref="resizeHandle">
      <div class="resize-handle-visual">
        <span class="handle-icon">⋮⋮⋮</span>
      </div>
    </div>
    
    <!-- DURATION LABEL (shown during drag) -->
    <div v-if="isResizing" class="duration-label">
      {{ displayDuration }}
    </div>
    
    <CustomerActionsMenu
        :booking="booking"
        :show="showActionsMenu"
        @close="showActionsMenu = false"
        @edit="onEdit"
        @delete="onDelete"
        @view-profile="onViewProfile"
    />
  </div>
</template>

<script>
import CustomerActionsMenu from './CustomerActionsMenu.vue';

// Debug flag - set to false in production
const DEBUG = process.env.NODE_ENV === 'development';

export default {
  name: 'BookingCard',
  components: {
    CustomerActionsMenu,
  },
  props: {
    booking: {
      default: function () {
        return {};
      },
    },
    isSaving: {
      type: Boolean,
      default: false,
    },
    maxDurationMinutes: {
      type: Number,
      default: null,
    },
    pxPerMinute: {
      type: Number,
      default: null, // Will calculate from slotHeight/slotInterval if not provided
    },
  },
  data() {
    return {
      isDelete: false,
      showActionsMenu: false,
      isResizing: false,
      displayDuration: '',
      originalHeight: null,
      originalDuration: null,
      currentHeight: null,
      currentDuration: null,
      isValidResize: true,
      resizeHandlers: null,
    }
  },
  computed: {
    customer() {
      return `${this.booking.customer_first_name} ${this.booking.customer_last_name}`
    },
    id() {
      return this.booking.id
    },
    assistants() {
      return (this.booking.services || [])
          .map(service => ({
            id: service.assistant_id,
            name: service.assistant_name,
          }))
          .filter(i => +i.id)
    },
    statusLabel() {
      const statusKey = this.booking.status
      if (this.$root.statusesList && this.$root.statusesList[statusKey]) {
        return this.$root.statusesList[statusKey].label
      }
      return statusKey
    },
    bookingStartTime() {
      // Use _serviceTime.start for attendant view, fallback to booking.time
      return this.booking._serviceTime?.start || this.booking.time || this.booking.start;
    },
  },
  watch: {
    // Reinitialize resize when booking prop changes
    booking: {
      handler() {
        this.destroyNativeResize();
        this.$nextTick(() => {
          this.initializeNativeResize();
        });
      },
      deep: true
    }
  },
  mounted() {
    console.log('🟢 BookingCard mounted(), booking ID:', this.booking.id);
    // Use $nextTick to ensure refs are available
    this.$nextTick(() => {
      console.log('🟢 $nextTick, refs:', {
        bookingCard: !!this.$refs.bookingCard,
        resizeHandle: !!this.$refs.resizeHandle
      });
      if (this.$refs.bookingCard && this.$refs.resizeHandle) {
        this.initializeNativeResize();
      } else {
        console.warn('⚠️ BookingCard refs not available in mounted()');
      }
    });
  },
  beforeUnmount() {
    this.destroyNativeResize();
  },
  methods: {
    toggleActionsMenu() {
      this.showActionsMenu = !this.showActionsMenu;
    },
    onEdit() {
      this.$emit('showDetails', this.booking);
      this.$emit('edit', this.booking);
    },
    onDelete() {
      this.$emit('deleteItem', this.booking.id);
    },
    onViewProfile(customer) {
      this.$emit('viewCustomerProfile', customer);
    },
    showDetails() {
      this.$emit('showDetails', this.booking);
    },
    getLabel(labelKey) {
      return this.$root.labels ? this.$root.labels[labelKey] : labelKey
    },
    getBookingDuration() {
      const service = this.booking.services && this.booking.services[0];
      if (!service) return 30;
      
      // Try to get duration from service.duration first (if set during resize)
      if (service.duration) {
        const [hours, mins] = service.duration.split(':').map(Number);
        return hours * 60 + mins;
      }
      
      // Calculate duration from start_at and end_at (backend response format)
      if (service.start_at && service.end_at) {
        const [startH, startM] = service.start_at.split(':').map(Number);
        const [endH, endM] = service.end_at.split(':').map(Number);
        const startMinutes = startH * 60 + startM;
        const endMinutes = endH * 60 + endM;
        return endMinutes - startMinutes;
      }
      
      // Fallback: use booking.duration if available (top-level field)
      if (this.booking.duration) {
        const [hours, mins] = this.booking.duration.split(':').map(Number);
        return hours * 60 + mins;
      }
      
      // Default fallback
      return 30;
    },
    calculateEndTime(startTime, durationMinutes) {
      const [startH, startM] = startTime.split(':').map(Number);
      const startMinutes = startH * 60 + startM;
      const endMinutes = startMinutes + durationMinutes;
      const endH = Math.floor(endMinutes / 60);
      const endM = endMinutes % 60;
      return `${String(endH).padStart(2, '0')}:${String(endM).padStart(2, '0')}`;
    },
    formatTimeRange(startTime, durationMinutes) {
      const endTime = this.calculateEndTime(startTime, durationMinutes);
      // Format times according to user settings
      const formattedStart = this.formatDisplayTime(startTime);
      const formattedEnd = this.formatDisplayTime(endTime);
      return `${formattedStart} – ${formattedEnd}`;
    },
    formatDisplayTime(time) {
      // Use timeFormat from mixin if available, otherwise return as-is
      if (this.timeFormat && typeof this.timeFormat === 'function') {
        return this.timeFormat(time);
      }
      return time;
    },
    initializeNativeResize() {
      console.log('🔵 initializeNativeResize() called for booking ID:', this.booking.id);
      const bookingCard = this.$refs.bookingCard;
      const resizeHandle = this.$refs.resizeHandle;
      
      if (!bookingCard || !resizeHandle) {
        console.warn('⚠️ BookingCard or resize handle ref not available');
        return;
      }

      const slotHeight = 110; // Match parent's slot height
      
      // ✅ Use pxPerMinute prop from parent (single source of truth)
      // Parent calculates this from plugin settings via calcSlotStep()
      const pxPerMin = this.pxPerMinute;
      
      // ✅ Derive slot interval from parent's calculation (no hardcoded fallbacks)
      const slotInterval = slotHeight / pxPerMin;
      
      console.log('🔵 Slot config:', { slotHeight, slotInterval, pxPerMin });
      
      // Validate configuration
      if (!slotHeight || !slotInterval || slotInterval <= 0 || !pxPerMin || pxPerMin <= 0) {
        console.error('❌ Invalid slot configuration:', { slotHeight, slotInterval, pxPerMin });
        return;
      }

      let startY = 0;
      let startHeight = 0;
      let startDuration = 0;
      let currentY = 0;
      
      const handleStart = (e) => {
        // Prevent default to avoid text selection and scrolling
        e.preventDefault();
        e.stopPropagation();
        
        // Get actual booking duration in minutes (this is the source of truth)
        startDuration = this.getBookingDuration();
        
        // Calculate expected height from duration (not from DOM, which might be wrong)
        // This ensures we're working with the correct baseline
        startHeight = startDuration * pxPerMin;
        
        // Get initial Y position (works for both mouse and touch)
        startY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;
        
        // Set the card to the correct height if it's not already
        bookingCard.style.height = `${startHeight}px`;
        
        this.isResizing = true;
        this.originalHeight = startHeight;
        this.originalDuration = startDuration;
        this.currentHeight = startHeight;
        this.currentDuration = startDuration;
        
        // HAPTIC FEEDBACK (mobile only)
        if ('vibrate' in navigator) {
          navigator.vibrate(10);
        }
        
        // Prevent text selection
        document.body.style.userSelect = 'none';
        document.body.style.webkitUserSelect = 'none';

        this.$emit('resize-start', {
          bookingId: this.booking.id,
          originalHeight: this.originalHeight,
          originalDuration: this.originalDuration,
        });
        
        console.log('🟡 RESIZE START:', {
          bookingId: this.booking.id,
          startHeight,
          startY,
          startDuration,
          slotInterval,
          pxPerMin
        });

        // Attach move and end listeners to document for better tracking
        document.addEventListener('mousemove', handleMove);
        document.addEventListener('mouseup', handleEnd);
        document.addEventListener('touchmove', handleMove, { passive: false });
        document.addEventListener('touchend', handleEnd);
        document.addEventListener('touchcancel', handleEnd);
      };

      const handleMove = (e) => {
        if (!this.isResizing) return;
        
        e.preventDefault();
        
        // Get current Y position
        currentY = e.type.includes('touch') ? e.touches[0].clientY : e.clientY;
        const deltaY = currentY - startY;
        
        // Calculate new height
        let newHeight = startHeight + deltaY;
        
        // Convert height to duration in minutes
        let newDurationMinutes = newHeight / pxPerMin;
        
        // Apply min/max duration constraints
        // ✅ Use derived slotInterval (no hardcoded fallbacks)
        const minDuration = slotInterval;
        const maxDuration = this.maxDurationMinutes || (24 * 60);
        
        newDurationMinutes = Math.max(minDuration, Math.min(maxDuration, newDurationMinutes));
        
        // SNAP TO SLOT BOUNDARIES: Round to nearest slot interval
        const snappedMinutes = Math.round(newDurationMinutes / slotInterval) * slotInterval;
        
        // Ensure snapped duration is within bounds
        const finalMinutes = Math.max(minDuration, Math.min(maxDuration, snappedMinutes));
        
        // Recalculate height from snapped duration (ensures pixel-perfect alignment)
        const snappedHeight = finalMinutes * pxPerMin;
        
        // Update element height
        bookingCard.style.height = `${snappedHeight}px`;
        this.currentHeight = snappedHeight;
        this.currentDuration = finalMinutes;
        
        // Calculate new end time
        const newEndTime = this.calculateEndTime(this.bookingStartTime, finalMinutes);
        
        // Update display duration
        this.displayDuration = this.formatTimeRange(this.bookingStartTime, finalMinutes);
        
        // Check if resize is valid
        this.isValidResize = finalMinutes >= minDuration && finalMinutes <= maxDuration;
        
        console.log('🔵 RESIZE MOVE:', {
          deltaY,
          newHeight,
          newDurationMinutes,
          snappedMinutes: finalMinutes,
          snappedHeight,
          slotInterval,
          pxPerMin,
          bookingStartTime: this.bookingStartTime,
          displayDuration: this.displayDuration
        });

        // Emit to parent for live preview
        this.$emit('resize-update', {
          bookingId: this.booking.id,
          newDuration: finalMinutes,
          heightPx: snappedHeight,
          newEndTime: newEndTime,
          isValid: this.isValidResize,
        });
      };

      const handleEnd = (e) => {
        if (!this.isResizing) return;
        
        e.preventDefault();
        
        // Remove event listeners
        document.removeEventListener('mousemove', handleMove);
        document.removeEventListener('mouseup', handleEnd);
        document.removeEventListener('touchmove', handleMove);
        document.removeEventListener('touchend', handleEnd);
        document.removeEventListener('touchcancel', handleEnd);
        
        this.isResizing = false;
        
        // HAPTIC FEEDBACK on release
        if ('vibrate' in navigator) {
          navigator.vibrate([10, 20, 10]);
        }
        
        // Restore text selection
        document.body.style.userSelect = '';
        document.body.style.webkitUserSelect = '';
        
        // Use the currentHeight we set during drag (this is the snapped height)
        // Don't use bookingCard.offsetHeight as it might be wrong or reset
        const finalHeight = this.currentHeight || bookingCard.offsetHeight;
        const finalDurationMinutes = finalHeight / pxPerMin;
        
        // Snap to slot boundaries one final time
        const snappedFinalMinutes = Math.round(finalDurationMinutes / slotInterval) * slotInterval;
        
        // Ensure within bounds
        // ✅ Use derived slotInterval (no hardcoded fallbacks)
        const minDuration = slotInterval;
        const maxDuration = this.maxDurationMinutes || (24 * 60);
        const finalDuration = Math.max(minDuration, Math.min(maxDuration, snappedFinalMinutes));
        
        // Update the card height to match the final snapped duration (ensure consistency)
        const finalSnappedHeight = finalDuration * pxPerMin;
        bookingCard.style.height = `${finalSnappedHeight}px`;
        this.currentHeight = finalSnappedHeight;
        
        console.log('🟢 RESIZE END:', {
          bookingId: this.booking.id,
          finalHeight,
          finalDurationMinutes,
          snappedFinalMinutes,
          finalDuration,
          slotInterval,
          pxPerMin,
          bookingStartTime: this.bookingStartTime,
          calculation: `(${finalHeight} / ${pxPerMin}) = ${finalDurationMinutes} → snapped to ${finalDuration}`
        });

        // Emit final duration to parent for save
        this.$emit('resize-end', {
          bookingId: this.booking.id,
          finalDuration: finalDuration,
        });
      };

      // Store handlers for cleanup
      this.resizeHandlers = { handleStart, handleMove, handleEnd };
      
      // Attach start event listeners to resize handle
      resizeHandle.addEventListener('mousedown', handleStart);
      resizeHandle.addEventListener('touchstart', handleStart, { passive: false });
      
      console.log('✅ Native resize initialized for booking ID:', this.booking.id);
    },
    destroyNativeResize() {
      const resizeHandle = this.$refs.resizeHandle;
      if (!resizeHandle || !this.resizeHandlers) return;

      const { handleStart, handleMove, handleEnd } = this.resizeHandlers;
      
      // Remove all event listeners
      resizeHandle.removeEventListener('mousedown', handleStart);
      resizeHandle.removeEventListener('touchstart', handleStart);
      document.removeEventListener('mousemove', handleMove);
      document.removeEventListener('mouseup', handleEnd);
      document.removeEventListener('touchmove', handleMove);
      document.removeEventListener('touchend', handleEnd);
      document.removeEventListener('touchcancel', handleEnd);
      
      this.resizeHandlers = null;
      
      if (DEBUG) {
        console.log('🧹 Native resize cleanup completed');
      }
    },
    revertResize() {
      // Called by parent to revert visual state on error
      if (this.originalHeight && this.$refs.bookingCard) {
        this.$refs.bookingCard.style.height = `${this.originalHeight}px`;
        this.displayDuration = '';
        this.isResizing = false;
        
        if (DEBUG) {
          console.log('🔄 Reverted resize to original height:', this.originalHeight);
        }
      }
    },
  },
  emits: ['deleteItem', 'showDetails', 'edit', 'viewCustomerProfile', 'resize-start', 'resize-update', 'resize-end'],
}
</script>

<style scoped>
.booking-wrapper {
  position: relative;
  width: 100%;
  z-index: 20;
  padding: 0; /* FIXED: Removed padding to prevent visual slip */
  touch-action: none; /* CRITICAL: Prevent scroll during touch drag */
  transition: box-shadow 0.2s, transform 0.2s;
}

/* Visual feedback during drag */
.booking-wrapper.is-resizing {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
  transform: scale(1.02);
  z-index: 100;
}

/* Loading state */
.booking-wrapper.is-saving {
  opacity: 0.7;
  pointer-events: none;
}

.saving-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  border-radius: 12px;
}

.booking {
  margin: 10px; /* FIXED: Moved padding here as margin */
  background-color: rgb(225 230 239 / 70%);
  border-radius: 12px;
  width: calc(100% - 20px);
  height: calc(100% - 20px);
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  border: 1px solid #e1e6ef;
  backdrop-filter: blur(5px);
  pointer-events: none;
  box-shadow: 0 0 10px 1px rgb(0 0 0 / 10%);
  position: relative;
  transition: background-color 0.2s;
}

.booking-wrapper.is-resizing .booking {
  background-color: rgb(225 230 239 / 95%); /* More opaque during drag */
  border-color: #04409F;
}

.booking-status {
  position: absolute;
  bottom: 12px;
  right: 12px;
  text-transform: uppercase;
  font-size: 10px;
  letter-spacing: -0.1px;
  color: #637491;
}

.booking-actions {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 5;
}

.booking-actions-button {
  background: none;
  border: none;
  color: #04409F;
  font-size: 20px;
  padding: 5px 10px;
  cursor: pointer;
  pointer-events: auto;
}

.customer-name {
  white-space: nowrap;
  overflow: hidden;
  color: #04409F;
  font-size: 16px;
  font-weight: 700;
  text-overflow: ellipsis;
  margin-right: 10px;
  cursor: pointer;
  pointer-events: auto;
}

.customer-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.customer-info-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
}

.booking-id {
  font-size: 12px;
  color: #637491;
  font-weight: bold;
}

.services-list {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
}

.services-list:has(.service-item:nth-child(2)) {
  margin-top: 24px;
  gap: 8px;
}

.services-list .service-item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.services-list .service-item .service-name {
  color: #637491;
  font-size: 13px;
  text-align: left;
}

.services-list .service-item .assistant-name {
  color: #637491;
  font-size: 11px;
}

.booking-actions-bottom {
  position: absolute;
  left: 12px;
  bottom: 8px;
  z-index: 5;
}

.booking-actions-menu-dots {
  background: none;
  border: none;
  color: #000;
  font-size: 20px;
  line-height: 5px;
  letter-spacing: 1px;
  padding: 5px;
  cursor: pointer;
  pointer-events: auto;
}

/* MOBILE-OPTIMIZED RESIZE HANDLE */
.resize-handle {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 31px; /* 30% smaller: 44px * 0.7 = 31px */
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: ns-resize;
  pointer-events: auto;
  
  /* MOBILE: Always show subtle visual indicator */
  background: linear-gradient(
    to bottom,
    transparent 0%,
    rgba(4, 64, 159, 0.05) 100%
  );
  border-radius: 0 0 8px 8px; /* 30% smaller: 12px * 0.7 = 8px */
}

.resize-handle-visual {
  background: rgba(4, 64, 159, 0.15);
  border-radius: 8px; /* 30% smaller: 12px * 0.7 = 8px */
  padding: 4px 17px; /* 30% smaller: 6px 24px * 0.7 = 4px 17px */
  opacity: 1; /* Always visible on mobile */
  transition: all 0.2s;
  pointer-events: none;
}

.booking-wrapper.is-resizing .resize-handle {
  cursor: grabbing !important; /* Active drag cursor */
  background: linear-gradient(
    to bottom,
    transparent 0%,
    rgba(4, 64, 159, 0.15) 100%
  );
}

.booking-wrapper.is-resizing .resize-handle-visual {
  background: rgba(4, 64, 159, 0.35);
  padding: 6px 22px; /* 30% smaller: 8px 32px * 0.7 = 6px 22px */
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.handle-icon {
  font-size: 13px; /* 30% smaller: 18px * 0.7 = 13px */
  color: #04409F;
  letter-spacing: 1.4px; /* 30% smaller: 2px * 0.7 = 1.4px */
  user-select: none;
  font-weight: bold;
}

/* DURATION LABEL (shown during drag) */
.duration-label {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: #04409F;
  color: white;
  padding: 10px 20px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 18px;
  pointer-events: none;
  z-index: 1000;
  box-shadow: 0 4px 16px rgba(4, 64, 159, 0.5);
  animation: fadeIn 0.2s ease-in;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translate(-50%, -50%) scale(0.8);
  }
  to {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
  }
}

/* Walk-In Badge */
.walkin-badge {
  font-size: 18px;
  opacity: 0.7;
  line-height: 1;
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.walkin-badge:hover {
  opacity: 1;
  transform: scale(1.2);
}

/* DESKTOP: Add hover states (using media query) */
@media (hover: hover) {
  .resize-handle {
    background: transparent; /* Hide gradient on desktop */
  }
  
  .resize-handle-visual {
    opacity: 0; /* FIXED: Completely hidden until hover */
  }
  
  .resize-handle:hover .resize-handle-visual {
    opacity: 1;
    background: rgba(4, 64, 159, 0.2);
  }
  
  .booking-wrapper.is-resizing * {
    cursor: grabbing !important; /* Override all child cursors */
  }
}

/* MOBILE: Larger touch targets on small screens */
@media (max-width: 768px) {
  .resize-handle {
    height: 34px; /* 30% smaller: 48px * 0.7 = 34px */
  }
  
  .handle-icon {
    font-size: 14px; /* 30% smaller: 20px * 0.7 = 14px */
  }
  
  .duration-label {
    font-size: 20px;
    padding: 12px 24px;
  }
}
</style>
