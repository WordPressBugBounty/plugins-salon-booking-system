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
    
    <div class="booking" :class="{ 'no-show': booking.no_show }" @click="showDetails">
      <div class="customer-info">
        <div class="customer-info-header">
          <span class="customer-name">{{ customer }}</span>
          <span class="status-badge" :style="statusBadgeStyle">{{ statusLabel }}</span>
        </div>
        <span class="booking-id">{{ id }}</span>
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

      <!-- Customer Note -->
      <div class="customer-note" v-if="booking.note">
        <span class="note-icon">💬</span>
        <span class="note-text">{{ booking.note }}</span>
      </div>

      <div class="booking-actions-bottom">
        <!-- Walk-In Badge -->
        <div v-if="booking.is_walkin" class="walkin-badge" title="Walk-In">
          🚶
        </div>
        
        <!-- No-show (PRO + salon setting only) -->
        <button 
          v-if="canUsePwaNoShowControl"
          class="no-show-icon"
          :class="{ 'active': booking.no_show }"
          @click.stop="toggleNoShow"
          :disabled="isProcessingNoShow"
          :title="booking.no_show ? 'Marked as No-Show (click to unmark)' : 'Mark as No-Show'"
        >
          <span class="no-show-icon-svg"></span>
        </button>
        
        <!-- Approve/Reject Actions for Pending Bookings -->
        <div v-if="showApprovalActions" class="approval-actions" @click.stop>
          <button 
            class="approval-btn approve-btn" 
            @click="approveBooking"
            :disabled="isProcessingStatus"
            title="Approve booking"
          >
            👍
          </button>
          <button 
            class="approval-btn reject-btn" 
            @click="rejectBooking"
            :disabled="isProcessingStatus"
            title="Reject booking"
          >
            👎
          </button>
        </div>
        
        <!-- Trash for no-show bookings (same gate as no-show control) -->
        <button 
          v-if="canUsePwaNoShowControl && booking.no_show"
          class="trash-icon"
          @click.stop="confirmDelete"
          :disabled="isDelete"
          title="Delete no-show booking"
        >
          <span class="trash-icon-svg"></span>
        </button>
      </div>
    </div>
    
    <!-- MOBILE-OPTIMIZED RESIZE HANDLE (invisible but functional); optional off via Profile -->
    <div v-if="bookingDragResizeEnabled" class="resize-handle" ref="resizeHandle">
      <div class="resize-handle-visual">
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
      isProcessingStatus: false,
      isProcessingNoShow: false,
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
    statusColor() {
      const map = {
        'sln-b-confirmed': '#16A34A',
        'sln-b-paid': '#16A34A',
        'sln-b-pending': '#D97706',
        'sln-b-pendingpayment': '#D97706',
        'sln-b-paylater': '#0891B2',
        'sln-b-cancelled': '#DC2626',
      };
      return map[this.booking.status] || '#2563EB';
    },
    statusBadgeStyle() {
      const color = this.statusColor;
      return {
        backgroundColor: color + '1F',
        color: color,
      };
    },
    bookingStartTime() {
      // Use _serviceTime.start for attendant view, fallback to booking.time
      return this.booking._serviceTime?.start || this.booking.time || this.booking.start;
    },
    showApprovalActions() {
      // Show approve/reject buttons if:
      // 1. Manual confirmation is enabled in settings
      // 2. Booking status is pending
      const isPending = this.booking.status === 'sln-b-pending';
      const manualConfirmationEnabled = this.$root.settings?.confirmation_mode === 'manual';
      return isPending && manualConfirmationEnabled;
    },
    bookingDragResizeEnabled() {
      return !this.$root.disableBookingDragResize;
    },
  },
  watch: {
    // Reinitialize resize when booking prop changes
    booking: {
      handler() {
        this.destroyNativeResize();
        this.$nextTick(() => {
          if (this.bookingDragResizeEnabled) {
            this.initializeNativeResize();
          }
        });
      },
      deep: true
    },
    bookingDragResizeEnabled(enabled) {
      this.destroyNativeResize();
      if (enabled) {
        this.$nextTick(() => {
          if (this.$refs.bookingCard && this.$refs.resizeHandle) {
            this.initializeNativeResize();
          }
        });
      }
    },
  },
  mounted() {
    console.log('🟢 BookingCard mounted(), booking ID:', this.booking.id);
    // Use $nextTick to ensure refs are available
    this.$nextTick(() => {
      console.log('🟢 $nextTick, refs:', {
        bookingCard: !!this.$refs.bookingCard,
        resizeHandle: !!this.$refs.resizeHandle
      });
      if (this.bookingDragResizeEnabled && this.$refs.bookingCard && this.$refs.resizeHandle) {
        this.initializeNativeResize();
      } else if (this.bookingDragResizeEnabled) {
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
    confirmDelete() {
      // Emit delete event directly for no-show bookings
      this.$emit('deleteItem', this.booking.id);
    },
    onViewProfile(customer) {
      this.$emit('viewCustomerProfile', customer);
    },
    showDetails() {
      if (this.isResizing) return;
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
      if (!this.bookingDragResizeEnabled) {
        return;
      }
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
    async approveBooking() {
      if (this.isProcessingStatus) return;
      
      this.isProcessingStatus = true;
      
      try {
        // Call the backend API to update booking status to confirmed
        const response = await this.axios.patch(`bookings/${this.booking.id}`, {
          status: 'sln-b-confirmed'
        });
        
        if (response.data) {
          // Emit event to parent to refresh the booking
          this.$emit('booking-status-changed', {
            bookingId: this.booking.id,
            newStatus: 'sln-b-confirmed'
          });
          
          // Show success message
          this.$root.$emit('show-notification', {
            type: 'success',
            message: 'Booking approved successfully'
          });
        }
      } catch (error) {
        console.error('Error approving booking:', error);
        this.$root.$emit('show-notification', {
          type: 'error',
          message: 'Failed to approve booking'
        });
      } finally {
        this.isProcessingStatus = false;
      }
    },
    async rejectBooking() {
      if (this.isProcessingStatus) return;
      
      this.isProcessingStatus = true;
      
      try {
        // Call the backend API to update booking status to cancelled
        const response = await this.axios.patch(`bookings/${this.booking.id}`, {
          status: 'sln-b-cancelled'
        });
        
        if (response.data) {
          // Emit event to parent to refresh the booking
          this.$emit('booking-status-changed', {
            bookingId: this.booking.id,
            newStatus: 'sln-b-cancelled'
          });
          
          // Show success message
          this.$root.$emit('show-notification', {
            type: 'success',
            message: 'Booking rejected successfully'
          });
        }
      } catch (error) {
        console.error('Error rejecting booking:', error);
        this.$root.$emit('show-notification', {
          type: 'error',
          message: 'Failed to reject booking'
        });
      } finally {
        this.isProcessingStatus = false;
      }
    },
    async toggleNoShow() {
      if (!this.canUsePwaNoShowControl || this.isProcessingNoShow) return;
      
      this.isProcessingNoShow = true;
      
      try {
        // Toggle the no-show status
        const newNoShowStatus = !this.booking.no_show;
        
        // Call backend API to toggle no-show
        const response = await this.axios.post('bookings/no-show', {
          bookingId: this.booking.id,
          noShow: newNoShowStatus ? 1 : 0
        });
        
        if (response.data && response.data.success) {
          // Update local booking data
          this.booking.no_show = newNoShowStatus;
          
          // Emit event to parent to refresh
          this.$emit('booking-no-show-changed', {
            bookingId: this.booking.id,
            noShow: newNoShowStatus
          });
          
          // Show success message
          this.$root.$emit('show-notification', {
            type: 'success',
            message: newNoShowStatus ? 'Marked as no-show' : 'Unmarked as no-show'
          });
        }
      } catch (error) {
        console.error('Error toggling no-show:', error);
        this.$root.$emit('show-notification', {
          type: 'error',
          message: 'Failed to update no-show status'
        });
      } finally {
        this.isProcessingNoShow = false;
      }
    },
  },
  emits: ['deleteItem', 'showDetails', 'edit', 'viewCustomerProfile', 'resize-start', 'resize-update', 'resize-end', 'booking-status-changed', 'booking-no-show-changed'],
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
  margin: 6px;
  background-color: #FFFFFF;
  border-radius: 8px;
  width: calc(100% - 12px);
  height: calc(100% - 12px);
  padding: 8px 10px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  border: 1px solid #E2E8F0;
  pointer-events: auto;
  cursor: pointer;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  position: relative;
  transition: background-color 0.2s;
  overflow: hidden;
}

.booking-wrapper.is-resizing .booking {
  background-color: #F8FAFC;
  border-color: #E2E8F0;
}

/* No-Show Diagonal Stripes Background */
.booking.no-show {
  background-image: repeating-linear-gradient(
    45deg,
    #FFFFFF,
    #FFFFFF 10px,
    #F1F5F9 10px,
    #F1F5F9 20px
  );
  background-color: #FFFFFF;
}

.status-badge {
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  white-space: nowrap;
  flex-shrink: 0;
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
  color: #2563EB;
  font-size: 20px;
  padding: 5px 10px;
  cursor: pointer;
  pointer-events: auto;
}

.customer-name {
  white-space: nowrap;
  overflow: hidden;
  color: #0F172A;
  font-size: 14px;
  font-weight: 600;
  text-overflow: ellipsis;
  margin-right: 8px;
}

.customer-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.customer-info-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.booking-id {
  font-size: 12px;
  color: #94A3B8;
  font-weight: 500;
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
  color: #64748B;
  font-size: 12px;
  text-align: left;
}

.services-list .service-item .assistant-name {
  color: #94A3B8;
  font-size: 11px;
}

.customer-note {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  margin-top: 8px;
  padding: 6px 8px;
  background: #F8FAFC;
  border-radius: 8px;
  border: 1px solid #E2E8F0;
}

.note-icon {
  font-size: 12px;
  line-height: 1.4;
  flex-shrink: 0;
}

.note-text {
  font-size: 11px;
  color: #64748B;
  line-height: 1.4;
  word-break: break-word;
}

.booking-actions-bottom {
  position: absolute;
  left: 12px;
  bottom: 8px;
  right: 12px;
  z-index: 5;
  display: flex;
  align-items: center;
  justify-content: space-between;
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
    rgba(37, 99, 235, 0.05) 100%
  );
  border-radius: 0 0 8px 8px;
}

.resize-handle-visual {
  background: rgba(37, 99, 235, 0.15);
  border-radius: 8px; /* 30% smaller: 12px * 0.7 = 8px */
  padding: 4px 17px; /* 30% smaller: 6px 24px * 0.7 = 4px 17px */
  opacity: 1; /* Always visible on mobile */
  transition: all 0.2s;
  pointer-events: none;
}

.booking-wrapper.is-resizing .resize-handle {
  cursor: grabbing !important;
  background: linear-gradient(
    to bottom,
    transparent 0%,
    rgba(37, 99, 235, 0.15) 100%
  );
}

.booking-wrapper.is-resizing .resize-handle-visual {
  background: rgba(37, 99, 235, 0.35);
  padding: 6px 22px; /* 30% smaller: 8px 32px * 0.7 = 6px 22px */
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.handle-icon {
  font-size: 13px;
  color: #2563EB;
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
  background: #2563EB;
  color: white;
  padding: 10px 20px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 18px;
  pointer-events: none;
  z-index: 1000;
  box-shadow: 0 4px 16px rgba(37, 99, 235, 0.4);
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

/* No-Show Icon */
.no-show-icon {
  background: transparent;
  border: none;
  padding: 0;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  margin-left: 8px;
}

.no-show-icon:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.no-show-icon-svg {
  display: inline-block;
  width: 28px;
  height: 28px;
  background-color: #2171B1; /* Blue by default (inactive) */
  -webkit-mask-size: 22px;
  mask-size: 22px;
  -webkit-mask-repeat: no-repeat;
  mask-repeat: no-repeat;
  -webkit-mask-position: center;
  mask-position: center;
  -webkit-mask-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 32 32' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M23.7778 17.25C23.7778 16.388 24.1056 15.5614 24.689 14.9519C25.2724 14.3424 26.0638 14 26.8889 14C27.714 14 28.5053 14.3424 29.0888 14.9519C29.6722 15.5614 30 16.388 30 17.25V23.75M26.8889 27H5.11111C4.28599 27 3.49467 26.6576 2.91122 26.0481C2.32778 25.4386 2 24.612 2 23.75V17.25C2 16.388 2.32778 15.5614 2.91122 14.9519C3.49467 14.3424 4.28599 14 5.11111 14C5.93623 14 6.72755 14.3424 7.311 14.9519C7.89445 15.5614 8.22222 16.388 8.22222 17.25V20.5H20.7227' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M5 14V6.50001C4.99947 6.04743 5.07045 5.59737 5.21057 5.16501M8.333 2.19501C8.78074 2.06492 9.24627 1.9992 9.71429 2.00001H22.2857C23.536 2.00001 24.7351 2.47411 25.6192 3.31803C26.5033 4.16194 27 5.30653 27 6.50001V14' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M7 27V30' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M25 27V30' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M2 2L30 30' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  mask-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 32 32' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M23.7778 17.25C23.7778 16.388 24.1056 15.5614 24.689 14.9519C25.2724 14.3424 26.0638 14 26.8889 14C27.714 14 28.5053 14.3424 29.0888 14.9519C29.6722 15.5614 30 16.388 30 17.25V23.75M26.8889 27H5.11111C4.28599 27 3.49467 26.6576 2.91122 26.0481C2.32778 25.4386 2 24.612 2 23.75V17.25C2 16.388 2.32778 15.5614 2.91122 14.9519C3.49467 14.3424 4.28599 14 5.11111 14C5.93623 14 6.72755 14.3424 7.311 14.9519C7.89445 15.5614 8.22222 16.388 8.22222 17.25V20.5H20.7227' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M5 14V6.50001C4.99947 6.04743 5.07045 5.59737 5.21057 5.16501M8.333 2.19501C8.78074 2.06492 9.24627 1.9992 9.71429 2.00001H22.2857C23.536 2.00001 24.7351 2.47411 25.6192 3.31803C26.5033 4.16194 27 5.30653 27 6.50001V14' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M7 27V30' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M25 27V30' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M2 2L30 30' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  transition: all 0.2s ease;
}

/* Active state - when booking is marked as no-show */
.no-show-icon.active .no-show-icon-svg {
  background-color: #EC1E1E; /* Red when active */
}

/* Hover states */
.no-show-icon:hover:not(:disabled) .no-show-icon-svg {
  transform: scale(1.1);
}

.no-show-icon:not(.active):hover:not(:disabled) .no-show-icon-svg {
  background-color: #1a5a8e; /* Darker blue on hover (inactive) */
}

.no-show-icon.active:hover:not(:disabled) .no-show-icon-svg {
  background-color: #d11a1a; /* Darker red on hover (active) */
}

.no-show-icon:active:not(:disabled) .no-show-icon-svg {
  transform: scale(1);
}

/* Trash Icon for No-Show Bookings */
.trash-icon {
  background: transparent;
  border: none;
  padding: 0;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.trash-icon:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.trash-icon-svg {
  display: inline-block;
  width: 28px;
  height: 28px;
  background-color: #DC2626;
  -webkit-mask-size: 22px;
  mask-size: 22px;
  -webkit-mask-repeat: no-repeat;
  mask-repeat: no-repeat;
  -webkit-mask-position: center;
  mask-position: center;
  -webkit-mask-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 29 33' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1.375 7.75033H4.29167M4.29167 7.75033H27.625M4.29167 7.75033V28.167C4.29167 28.9405 4.59896 29.6824 5.14594 30.2294C5.69292 30.7764 6.43479 31.0837 7.20833 31.0837H21.7917C22.5652 31.0837 23.3071 30.7764 23.8541 30.2294C24.401 29.6824 24.7083 28.9405 24.7083 28.167V7.75033M8.66667 7.75033V4.83366C8.66667 4.06011 8.97396 3.31824 9.52094 2.77126C10.0679 2.22428 10.8098 1.91699 11.5833 1.91699H17.4167C18.1902 1.91699 18.9321 2.22428 19.4791 2.77126C20.026 3.31824 20.3333 4.06011 20.3333 4.83366V7.75033M11.5833 15.042V23.792M17.4167 15.042V23.792' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  mask-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 29 33' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1.375 7.75033H4.29167M4.29167 7.75033H27.625M4.29167 7.75033V28.167C4.29167 28.9405 4.59896 29.6824 5.14594 30.2294C5.69292 30.7764 6.43479 31.0837 7.20833 31.0837H21.7917C22.5652 31.0837 23.3071 30.7764 23.8541 30.2294C24.401 29.6824 24.7083 28.9405 24.7083 28.167V7.75033M8.66667 7.75033V4.83366C8.66667 4.06011 8.97396 3.31824 9.52094 2.77126C10.0679 2.22428 10.8098 1.91699 11.5833 1.91699H17.4167C18.1902 1.91699 18.9321 2.22428 19.4791 2.77126C20.026 3.31824 20.3333 4.06011 20.3333 4.83366V7.75033M11.5833 15.042V23.792M17.4167 15.042V23.792' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  transition: all 0.2s ease;
}

.trash-icon:hover:not(:disabled) .trash-icon-svg {
  background-color: #b91c1c;
  transform: scale(1.1);
}

.trash-icon:active:not(:disabled) .trash-icon-svg {
  transform: scale(1);
}

/* Approval Actions */
.approval-actions {
  display: flex;
  gap: 8px;
  margin-left: auto;
}

.approval-btn {
  background: white;
  border: 2px solid;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  cursor: pointer;
  transition: all 0.2s ease;
  padding: 0;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.approval-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.approve-btn {
  border-color: #16A34A;
  background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
}

.approve-btn:hover:not(:disabled) {
  background: #16A34A;
  transform: scale(1.1);
  box-shadow: 0 4px 8px rgba(22, 163, 74, 0.3);
}

.reject-btn {
  border-color: #DC2626;
  background: linear-gradient(135deg, #ffffff 0%, #fef2f2 100%);
}

.reject-btn:hover:not(:disabled) {
  background: #DC2626;
  transform: scale(1.1);
  box-shadow: 0 4px 8px rgba(220, 38, 38, 0.3);
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
    background: rgba(37, 99, 235, 0.2);
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
