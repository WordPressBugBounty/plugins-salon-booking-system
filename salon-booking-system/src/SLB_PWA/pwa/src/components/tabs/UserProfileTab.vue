<template>
  <div class="profile-screen">
    <div class="profile-loading" v-if="isLoading">
      <b-spinner></b-spinner>
    </div>

    <template v-else-if="user">
      <!-- Hero -->
      <div class="profile-hero">
        <div class="profile-avatar">{{ userInitials }}</div>
        <h2 class="profile-name">{{ user.name }}</h2>
        <span class="profile-role-badge">{{ user.role }}</span>
      </div>

      <!-- Menu -->
      <div class="menu-section">
        <p class="menu-section-label">Active Users</p>
        <div class="active-users-card">
          <div class="active-users-loading" v-if="isLoadingUsers">
            <b-spinner small></b-spinner>
            <span>Loading active users...</span>
          </div>
          <div v-else-if="filteredActiveUsers.length > 0" class="active-users-list">
            <div 
              v-for="activeUser in filteredActiveUsers" 
              :key="activeUser.id"
              class="active-user-item"
            >
              <div class="active-user-avatar">{{ getUserInitials(activeUser.name) }}</div>
              <div class="active-user-info">
                <span class="active-user-name">{{ activeUser.name }}</span>
                <span class="active-user-role">{{ activeUser.role }}</span>
              </div>
              <button 
                v-if="activeUser.id !== user.id"
                class="active-user-logout-btn"
                @click="logoutUser(activeUser.id)"
                :disabled="activeUser.isLoggingOut"
              >
                <font-awesome-icon 
                  :icon="activeUser.isLoggingOut ? 'fa-solid fa-circle-xmark' : 'fa-solid fa-arrow-right-from-bracket'" 
                  :class="{ 'fa-spin': activeUser.isLoggingOut }"
                />
              </button>
              <span v-else class="active-user-indicator active-user-indicator--self">
                You
              </span>
            </div>
          </div>
          <div v-else class="active-users-empty">
            <p>No other users are currently active</p>
          </div>
        </div>
      </div>

      <div class="menu-section">
        <p class="menu-section-label">App</p>
        <div class="menu-list">
          <div v-if="canAccessBookingResizePref" class="menu-row menu-row--toggle">
            <span class="menu-row-left">
              <font-awesome-icon icon="fa-solid fa-arrows-up-down" class="menu-icon" />
              <span class="menu-row-label">Disable drag-to-resize on bookings</span>
            </span>
            <label class="profile-switch" @click.stop>
              <input
                v-model="disableBookingDragResize"
                type="checkbox"
                class="profile-switch-input"
                aria-label="Disable drag-to-resize on calendar bookings"
              />
              <span class="profile-switch-track" aria-hidden="true" />
            </label>
          </div>
          <button class="menu-row" @click="forcePwaUpdate" :disabled="isUpdating">
            <span class="menu-row-left">
              <font-awesome-icon icon="fa-solid fa-rotate-right" class="menu-icon" :class="{ 'fa-spin': isUpdating }" />
              <span class="menu-row-label">{{ isUpdating ? 'Updating…' : 'Force App Update' }}</span>
            </span>
          </button>
          <button
            class="menu-row"
            v-if="isAdmin"
            type="button"
            @click="resetCalendar"
            :disabled="isResetting"
            :aria-busy="isResetting"
          >
            <span class="menu-row-left">
              <font-awesome-icon icon="fa-solid fa-trash" class="menu-icon menu-icon--warning" :class="{ 'fa-spin': isResetting }" />
              <span class="menu-row-label">{{ isResetting ? 'Resetting…' : 'Reset Calendar Cache' }}</span>
            </span>
            <span
              v-if="showResetCalendarSuccess"
              class="menu-row-feedback menu-row-feedback--success"
              aria-hidden="true"
            >
              <font-awesome-icon icon="fa-solid fa-check" />
            </span>
          </button>
        </div>
        <div
          v-if="calendarResetBanner"
          class="profile-inline-notice"
          :class="'profile-inline-notice--' + calendarResetBanner.variant"
          role="status"
          aria-live="polite"
        >
          <font-awesome-icon
            :icon="calendarResetBanner.variant === 'success' ? 'fa-regular fa-circle-check' : 'fa-solid fa-circle-xmark'"
            class="profile-inline-notice-icon"
            aria-hidden="true"
          />
          <span>{{ calendarResetBanner.text }}</span>
        </div>
      </div>

      <div class="menu-section">
        <p class="menu-section-label">Session</p>
        <div class="menu-list">
          <button class="menu-row menu-row--destructive" @click="logOut">
            <span class="menu-row-left">
              <font-awesome-icon icon="fa-solid fa-arrow-right-from-bracket" class="menu-icon" />
              <span class="menu-row-label">Log Out</span>
            </span>
          </button>
        </div>
      </div>

      <p class="app-version">Salon Booking System</p>
    </template>

    <div v-else class="profile-error">
      <p>Failed to load user information. Please try again.</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'UserProfileTab',
  data() {
    return {
      isLoading: true,
      user: null,
      isResetting: false,
      /** Brief checkmark on the reset row after success */
      showResetCalendarSuccess: false,
      /** Inline message under App menu (toast is often unavailable in bootstrap-vue-3) */
      calendarResetBanner: null,
      calendarResetBannerTimer: null,
      showResetSuccessTimer: null,
      isUpdating: false,
      activeUsers: [],
      isLoadingUsers: false,
      userRefreshInterval: null,
    };
  },
  computed: {
    isAdmin() {
      return this.user?.role?.toLowerCase().includes('admin') ||
             this.user?.role?.toLowerCase().includes('administrator');
    },
    userInitials() {
      if (!this.user?.name) return '?';
      return this.user.name
        .split(' ')
        .slice(0, 2)
        .map(w => w[0]?.toUpperCase() ?? '')
        .join('');
    },
    userRoleLevel() {
      const role = this.user?.role?.toLowerCase() || '';
      if (role.includes('admin')) return 3;
      if (role.includes('manager')) return 2;
      if (role.includes('staff')) return 1;
      return 0;
    },
    filteredActiveUsers() {
      // Filter users by role hierarchy - show users with equal or lower role level
      return this.activeUsers
        .filter(u => {
          const role = u.role?.toLowerCase() || '';
          let userLevel = 0;
          if (role.includes('admin')) userLevel = 3;
          else if (role.includes('manager')) userLevel = 2;
          else if (role.includes('staff')) userLevel = 1;
          
          return userLevel <= this.userRoleLevel;
        })
        .map(u => ({
          ...u,
          isLoggingOut: u.isLoggingOut || false
        }))
        .sort((a, b) => {
          // Sort: current user first, then by role level (highest first), then by name
          if (a.id === this.user.id) return -1;
          if (b.id === this.user.id) return 1;
          
          const aLevel = this.getRoleLevel(a.role);
        const bLevel = this.getRoleLevel(b.role);
        if (aLevel !== bLevel) return bLevel - aLevel;
        
        return (a.name || '').localeCompare(b.name || '');
      });
    },
    disableBookingDragResize: {
      get() {
        return !!(this.$root && this.$root.disableBookingDragResize);
      },
      set(val) {
        if (this.$root && typeof this.$root.setDisableBookingDragResize === 'function') {
          this.$root.setDisableBookingDragResize(val);
        }
      },
    },
  },
  methods: {
    loadUserProfile() {
      this.isLoading = true;
      this.axios
          .get('/users/current')
          .then((response) => {
            this.user = response.data;
          })
          .catch((error) => {
            // eslint-disable-next-line
            console.error('Error loading user profile:', error);
            if (process.env.NODE_ENV === 'development' && window.slnPWA?.mock_user) {
              this.user = window.slnPWA.mock_user;
            } else {
              this.user = null;
            }
          })
          .finally(() => {
            this.isLoading = false;
          });
    },
    async loadActiveUsers() {
      this.isLoadingUsers = true;
      try {
        const response = await this.axios.get('/users/active');
        this.activeUsers = response.data.users || [];
      } catch (error) {
        console.error('Error loading active users:', error);
        this.activeUsers = [];
      } finally {
        this.isLoadingUsers = false;
      }
    },
    async logoutUser(userId) {
      // Find the user in the array and set loading state
      const userIndex = this.activeUsers.findIndex(u => u.id === userId);
      if (userIndex === -1) return;

      this.$set(this.activeUsers[userIndex], 'isLoggingOut', true);

      try {
        await this.axios.post(`/users/${userId}/logout`);
        // Remove user from active list after successful logout
        this.activeUsers = this.activeUsers.filter(u => u.id !== userId);
      } catch (error) {
        console.error('Error logging out user:', error);
        this.$set(this.activeUsers[userIndex], 'isLoggingOut', false);
      }
    },
    getUserInitials(name) {
      if (!name) return '?';
      return name
        .split(' ')
        .slice(0, 2)
        .map(w => w[0]?.toUpperCase() ?? '')
        .join('');
    },
    getRoleLevel(role) {
      const r = (role || '').toLowerCase();
      if (r.includes('admin')) return 3;
      if (r.includes('manager')) return 2;
      if (r.includes('staff')) return 1;
      return 0;
    },
    logOut() {
      this.axios
          .post('/users/logout')
          .then(() => {
            this.user = null;
            window.location.href = '/';
          })
          .catch((error) => {
            // eslint-disable-next-line
            console.error('Logout failed:', error);
          });
    },
    clearCalendarResetTimers() {
      if (this.calendarResetBannerTimer) {
        clearTimeout(this.calendarResetBannerTimer);
        this.calendarResetBannerTimer = null;
      }
      if (this.showResetSuccessTimer) {
        clearTimeout(this.showResetSuccessTimer);
        this.showResetSuccessTimer = null;
      }
    },
    async resetCalendar() {
      if (this.isResetting) return;

      this.clearCalendarResetTimers();
      this.calendarResetBanner = null;
      this.showResetCalendarSuccess = false;

      try {
        this.isResetting = true;
        await this.$nextTick();

        let clearedCount = 0;

        // 1. Clear localStorage: sln_*, salon_*, and calendar-specific keys
        const calendarKeys = ['isAttendantView'];
        const prefixKeys = ['sln_', 'salon_'];
        Object.keys(localStorage).forEach(key => {
          const matchPrefix = prefixKeys.some(p => key.startsWith(p));
          const matchCalendar = calendarKeys.includes(key);
          if (matchPrefix || matchCalendar) {
            localStorage.removeItem(key);
            clearedCount++;
          }
        });

        // 2. Clear sessionStorage
        sessionStorage.clear();

        // 3. Dispatch event so Calendar tab reloads data (works even if Calendar is mounted but hidden)
        window.dispatchEvent(new CustomEvent('sln-calendar-cache-cleared'));

        // Let "Resetting…" + spinner paint (work above is synchronous and very fast)
        await new Promise((r) => setTimeout(r, 450));

        const successText =
          clearedCount > 0
            ? `Removed ${clearedCount} stored item${clearedCount === 1 ? '' : 's'}. Calendar data was refreshed in the background.`
            : 'Browser storage for this app was cleared. Open the Calendar tab if you want to confirm fresh data.';

        this.calendarResetBanner = { variant: 'success', text: successText };
        this.showResetCalendarSuccess = true;
        this.showResetSuccessTimer = setTimeout(() => {
          this.showResetCalendarSuccess = false;
          this.showResetSuccessTimer = null;
        }, 2800);

        this.calendarResetBannerTimer = setTimeout(() => {
          this.calendarResetBanner = null;
          this.calendarResetBannerTimer = null;
        }, 8000);

        this.$bvToast?.toast(successText, {
          title: 'Cache cleared',
          variant: 'success',
          solid: true,
          autoHideDelay: 5000,
          toaster: 'b-toaster-top-center',
        });
      } catch (error) {
        await new Promise((r) => setTimeout(r, 200));
        this.calendarResetBanner = {
          variant: 'danger',
          text: 'Could not clear calendar storage. Try again or use Force App Update.',
        };
        this.calendarResetBannerTimer = setTimeout(() => {
          this.calendarResetBanner = null;
          this.calendarResetBannerTimer = null;
        }, 8000);
        this.$bvToast?.toast(
          'Failed to clear calendar cache. Please try again or contact support.',
          {
            title: 'Reset failed',
            variant: 'danger',
            solid: true,
            autoHideDelay: 5000,
            toaster: 'b-toaster-top-center',
          }
        );
      } finally {
        this.isResetting = false;
      }
    },
    clearForceUpdateSafetyTimer() {
      if (this._forceUpdateSafetyTimer != null) {
        clearTimeout(this._forceUpdateSafetyTimer);
        this._forceUpdateSafetyTimer = null;
      }
    },
    /**
     * Race a promise so SW/cache APIs cannot hang the UI forever (some WebViews stall on unregister).
     */
    _withTimeout(promise, ms, label) {
      return Promise.race([
        promise,
        new Promise((_, reject) => {
          setTimeout(() => reject(new Error(`${label} timed out`)), ms);
        }),
      ]);
    },
    async forcePwaUpdate() {
      if (this.isUpdating) return;

      this.clearForceUpdateSafetyTimer();

      try {
        this.isUpdating = true;
        console.log('=== FORCE PWA UPDATE ===');

        // 1. Unregister all service workers (bounded wait — never hang "Updating…")
        if ('serviceWorker' in navigator) {
          let registrations = [];
          try {
            registrations = await this._withTimeout(
              navigator.serviceWorker.getRegistrations(),
              8000,
              'serviceWorker.getRegistrations'
            );
          } catch (e) {
            console.warn('Force update: getRegistrations failed', e);
          }
          for (const registration of registrations) {
            try {
              await this._withTimeout(registration.unregister(), 8000, 'serviceWorker.unregister');
              console.log('   Unregistered service worker');
            } catch (e) {
              console.warn('Force update: unregister failed', e);
            }
          }
        }

        // 2. Clear all Cache API caches
        if ('caches' in window) {
          let cacheNames = [];
          try {
            cacheNames = await this._withTimeout(caches.keys(), 8000, 'caches.keys');
          } catch (e) {
            console.warn('Force update: caches.keys failed', e);
          }
          for (const name of cacheNames) {
            try {
              await this._withTimeout(caches.delete(name), 5000, 'caches.delete');
              console.log('   Deleted cache:', name);
            } catch (e) {
              console.warn('Force update: cache delete failed', name, e);
            }
          }
        }

        // 3. Clear localStorage and sessionStorage
        try {
          localStorage.clear();
          sessionStorage.clear();
        } catch (e) {
          console.warn('Force update: storage clear failed', e);
        }
        console.log('   Cleared storage');

        console.log('=== RELOADING TO LOAD FRESH CODE ===');
        this.$bvToast?.toast(
          'PWA cache cleared. Page will reload with latest code.',
          {
            title: 'Update Complete',
            variant: 'success',
            solid: true,
            autoHideDelay: 2000,
            toaster: 'b-toaster-top-center',
          }
        );

        // Hard reload — success path never set isUpdating=false; if reload is blocked (some PWAs),
        // unlock after a delay so the button is not stuck forever.
        this._forceUpdateSafetyTimer = setTimeout(() => {
          this._forceUpdateSafetyTimer = null;
          this.isUpdating = false;
        }, 12000);

        setTimeout(() => {
          try {
            window.location.reload();
          } catch (e) {
            console.error('Force update: reload failed', e);
            this.clearForceUpdateSafetyTimer();
            this.isUpdating = false;
            this.$bvToast?.toast(
              'Could not reload automatically. Close this app tab and open it again.',
              {
                title: 'Reload blocked',
                variant: 'warning',
                solid: true,
                autoHideDelay: 8000,
                toaster: 'b-toaster-top-center',
              }
            );
          }
        }, 500);
      } catch (error) {
        console.error('Force update error:', error);
        this.clearForceUpdateSafetyTimer();
        this.$bvToast?.toast(
          'Failed to clear cache. Try a hard refresh (Ctrl+Shift+R) or close and reopen the PWA.',
          {
            title: 'Update Failed',
            variant: 'danger',
            solid: true,
            autoHideDelay: 5000,
            toaster: 'b-toaster-top-center',
          }
        );
        this.isUpdating = false;
      }
    },
  },
  mounted() {
    this.loadUserProfile();
    this.loadActiveUsers();
    
    // Refresh active users every 30 seconds
    this.userRefreshInterval = setInterval(() => {
      this.loadActiveUsers();
    }, 30000);
  },
  beforeUnmount() {
    if (this.userRefreshInterval) {
      clearInterval(this.userRefreshInterval);
    }
    this.clearCalendarResetTimers();
    this.clearForceUpdateSafetyTimer();
  },
};
</script>

<style scoped>
/* ── Screen wrapper ── */
.profile-screen {
  min-height: 100vh;
  background-color: var(--color-background, #F4F6FA);
}

.profile-loading {
  display: flex;
  justify-content: center;
  padding-top: 60px;
}

/* ── Hero ── */
.profile-hero {
  background: linear-gradient(180deg, var(--color-primary-light, #EFF6FF) 0%, var(--color-surface, #FFFFFF) 100%);
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 36px 16px 28px;
  gap: 10px;
}

.profile-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background-color: var(--color-primary, #2563EB);
  color: #fff;
  font-size: 28px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  letter-spacing: 0.02em;
}

.profile-name {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text-primary, #0F172A);
  margin: 4px 0 0;
}

.profile-role-badge {
  background-color: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
  font-size: 12px;
  font-weight: 600;
  padding: 4px 14px;
  border-radius: var(--radius-pill, 999px);
  text-transform: capitalize;
}

/* ── Menu sections ── */
.menu-section {
  margin-top: 24px;
}

.menu-section-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-text-muted, #94A3B8);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 0 16px;
  margin: 0 0 4px;
}

.menu-list {
  background-color: var(--color-surface, #FFFFFF);
  border-radius: var(--radius-lg, 16px);
  overflow: hidden;
}

/* ── Menu row ── */
.menu-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  min-height: 52px;
  padding: 0 16px;
  background: transparent;
  border: none;
  border-bottom: 1px solid var(--color-border, #E2E8F0);
  cursor: pointer;
  transition: background-color 0.12s ease;
  text-align: left;
}

.menu-row:last-child {
  border-bottom: none;
}

.menu-row:active {
  background-color: var(--color-background, #F4F6FA);
}

.menu-row:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.menu-row-left {
  display: flex;
  align-items: center;
  gap: 14px;
}

.menu-icon {
  width: 20px;
  font-size: 17px;
  color: var(--color-primary, #2563EB);
  flex-shrink: 0;
}

.menu-icon--warning {
  color: var(--color-pending, #D97706);
}

.menu-row-label {
  font-size: 15px;
  font-weight: 500;
  color: var(--color-text-primary, #0F172A);
}

.menu-row-accessory {
  font-size: 13px;
  color: var(--color-text-muted, #94A3B8);
}

.menu-row-feedback {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: profile-feedback-pop 0.35s ease;
}

.menu-row-feedback--success {
  color: #059669;
  font-size: 18px;
}

@keyframes profile-feedback-pop {
  from {
    opacity: 0;
    transform: scale(0.6);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.profile-inline-notice {
  margin: 12px 16px 0;
  padding: 12px 14px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 500;
  line-height: 1.45;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.profile-inline-notice-icon {
  flex-shrink: 0;
  margin-top: 2px;
  font-size: 18px;
}

.profile-inline-notice--success {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.profile-inline-notice--success .profile-inline-notice-icon {
  color: #059669;
}

.profile-inline-notice--danger {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

.profile-inline-notice--danger .profile-inline-notice-icon {
  color: #dc2626;
}

.menu-row--toggle {
  cursor: default;
  border-bottom: 1px solid var(--color-border, #e2e8f0);
}

.menu-row--toggle:active {
  background-color: transparent;
}

/* iOS-style switch */
.profile-switch {
  position: relative;
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
}

.profile-switch-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.profile-switch-track {
  width: 46px;
  height: 28px;
  background: var(--color-border, #e2e8f0);
  border-radius: 999px;
  transition: background-color 0.2s ease;
  position: relative;
}

.profile-switch-track::after {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 22px;
  height: 22px;
  background: #fff;
  border-radius: 50%;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.2);
  transition: transform 0.2s ease;
}

.profile-switch-input:checked + .profile-switch-track {
  background: var(--color-primary, #2563eb);
}

.profile-switch-input:checked + .profile-switch-track::after {
  transform: translateX(18px);
}

.profile-switch-input:focus-visible + .profile-switch-track {
  outline: 2px solid var(--color-primary, #2563eb);
  outline-offset: 2px;
}

/* ── Destructive row ── */
.menu-row--destructive .menu-icon,
.menu-row--destructive .menu-row-label {
  color: var(--color-error, #DC2626);
}

/* ── Active Users Card ── */
.active-users-card {
  background-color: var(--color-surface, #FFFFFF);
  border-radius: var(--radius-lg, 16px);
  overflow: hidden;
  padding: 16px;
}

.active-users-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 20px;
  color: var(--color-text-secondary, #64748B);
  font-size: 14px;
}

.active-users-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.active-user-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background-color: var(--color-background, #F4F6FA);
  border-radius: var(--radius-md, 12px);
  transition: background-color 0.12s ease;
}

.active-user-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--color-primary, #2563EB), #1D4ED8);
  color: #fff;
  font-size: 15px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  letter-spacing: 0.02em;
  flex-shrink: 0;
}

.active-user-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.active-user-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.active-user-role {
  font-size: 12px;
  color: var(--color-text-secondary, #64748B);
}

.active-user-indicator {
  font-size: 11px;
  font-weight: 600;
  color: var(--color-success, #16A34A);
  background-color: rgba(22, 163, 74, 0.1);
  padding: 4px 10px;
  border-radius: var(--radius-pill, 999px);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  flex-shrink: 0;
}

.active-user-indicator--self {
  color: var(--color-primary, #2563EB);
  background-color: var(--color-primary-light, #EFF6FF);
}

.active-user-logout-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: transparent;
  border: 1.5px solid var(--color-border, #E2E8F0);
  color: var(--color-error, #DC2626);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.12s ease;
  flex-shrink: 0;
  font-size: 14px;
}

.active-user-logout-btn:hover:not(:disabled) {
  background-color: rgba(220, 38, 38, 0.1);
  border-color: var(--color-error, #DC2626);
}

.active-user-logout-btn:active:not(:disabled) {
  transform: scale(0.95);
}

.active-user-logout-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.active-users-empty {
  padding: 30px 16px;
  text-align: center;
}

.active-users-empty p {
  font-size: 14px;
  color: var(--color-text-secondary, #64748B);
  margin: 0;
}

/* ── App version ── */
.app-version {
  text-align: center;
  font-size: 12px;
  color: var(--color-text-muted, #94A3B8);
  margin: 32px 0 16px;
}

/* ── Error state ── */
.profile-error {
  padding: 40px 16px;
  text-align: center;
  color: var(--color-text-secondary, #64748B);
}

/* ── Spin animation for loading icons ── */
.fa-spin {
  animation: fa-spin 1s infinite linear;
}

@keyframes fa-spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
