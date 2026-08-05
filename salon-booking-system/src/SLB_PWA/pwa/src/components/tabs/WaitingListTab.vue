<template>
  <div class="wl-screen">
    <h1 class="wl-title">Waiting list</h1>

    <!-- Search -->
    <div class="wl-search">
      <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="wl-search-icon" />
      <input
        type="text"
        class="wl-search-input"
        v-model="search"
        @input="onSearchInput"
        placeholder="Search by name, email or phone…"
      />
      <button v-if="search" type="button" class="wl-search-clear" @click="clearSearch">
        <font-awesome-icon icon="fa-solid fa-xmark" />
      </button>
    </div>

    <!-- Status filter -->
    <div class="wl-filters">
      <button
        type="button"
        class="wl-chip"
        :class="{ 'wl-chip--active': status === f.value }"
        v-for="f in statusFilters"
        :key="f.value"
        @click="setStatus(f.value)"
      >{{ f.label }}</button>
    </div>

    <!-- Feedback -->
    <transition name="wl-flash-fade">
      <div class="wl-flash" :class="'wl-flash--' + flashType" v-if="flash">
        <font-awesome-icon
          :icon="flashType === 'error' ? 'fa-solid fa-circle-xmark' : 'fa-regular fa-circle-check'"
          class="wl-flash-icon"
        />
        <span class="wl-flash-text">{{ flash }}</span>
        <button type="button" class="wl-flash-close" @click="dismissFlash" aria-label="Dismiss">
          <font-awesome-icon icon="fa-solid fa-xmark" />
        </button>
      </div>
    </transition>

    <!-- Loading (first load) -->
    <div v-if="isLoading && !entries.length" class="wl-skeleton-list">
      <div class="wl-skeleton" v-for="n in 4" :key="'sk' + n"></div>
    </div>

    <!-- Empty -->
    <div v-else-if="!entries.length" class="wl-empty">
      <font-awesome-icon icon="fa-solid fa-bell" class="wl-empty-icon" />
      <p class="wl-empty-text">{{ search ? 'No customers match your search.' : 'No customers on the waiting list.' }}</p>
    </div>

    <!-- List -->
    <div v-else class="wl-list">
      <div class="wl-card" v-for="e in entries" :key="e.id">
        <div class="wl-card-top">
          <div class="wl-avatar">{{ initials(e) }}</div>
          <div class="wl-info">
            <div class="wl-name">{{ e.name }}</div>
            <div class="wl-sub" v-if="e.service_name">{{ e.service_name }}</div>
            <div class="wl-when" v-if="e.requested">
              <font-awesome-icon icon="fa-regular fa-clock" class="wl-when-icon" />
              <span class="wl-when-text">{{ requestedLabel(e.requested) }}</span>
            </div>
          </div>
          <span class="wl-status" :class="'wl-status--' + e.status">{{ statusLabel(e.status) }}</span>
        </div>

        <div class="wl-actions">
          <div class="wl-contact" v-if="phoneOf(e) && !shouldHidePhone">
            <a :href="'tel:' + phoneOf(e)" class="wl-contact-btn" aria-label="Call">
              <font-awesome-icon icon="fa-solid fa-phone" />
            </a>
            <a :href="'sms:' + phoneOf(e)" class="wl-contact-btn" aria-label="Text">
              <font-awesome-icon icon="fa-solid fa-message" />
            </a>
            <a :href="'https://wa.me/' + waNumber(e)" target="_blank" rel="noopener" class="wl-contact-btn" aria-label="WhatsApp">
              <font-awesome-icon icon="fa-brands fa-whatsapp" />
            </a>
          </div>
          <span v-else class="wl-nophone">No phone</span>

          <button
            type="button"
            class="wl-notify"
            :class="{ 'wl-notify--secondary': e.last_notified_at }"
            :disabled="!e.can_notify || notifyingId === e.id"
            :title="e.can_notify ? '' : e.notify_reason"
            @click="notify(e.id)"
          >
            <font-awesome-icon v-if="notifyingId === e.id" icon="fa-solid fa-rotate-right" spin />
            <span v-else>{{ e.last_notified_at ? 'Notify again' : 'Notify' }}</span>
          </button>
        </div>

        <p class="wl-reason" v-if="!e.can_notify && e.notify_reason">{{ e.notify_reason }}</p>
      </div>

      <button v-if="hasMore" type="button" class="wl-more-btn" :disabled="isLoading" @click="loadMore">
        {{ isLoading ? 'Loading…' : 'Load more' }}
      </button>
    </div>
  </div>
</template>

<script>
    import mixins from "@/mixin";

    export default {
        name: 'WaitingListTab',
        mixins: [mixins],
        props: {
            shop: {
                default: function () {
                    return null;
                },
            },
        },
        data() {
            return {
                entries: [],
                status: 'active',
                search: '',
                page: 1,
                perPage: 20,
                total: 0,
                hasMore: false,
                isLoading: false,
                notifyingId: null,
                searchTimer: null,
                flash: null,
                flashType: 'success',
                flashTimer: null,
                statusFilters: [
                    {value: '', label: 'All'},
                    {value: 'active', label: 'Active'},
                    {value: 'offered', label: 'Offered'},
                    {value: 'accepted', label: 'Booked'},
                    {value: 'expired', label: 'Expired'},
                ],
            }
        },
        mounted() {
            this.load()
        },
        beforeUnmount() {
            clearTimeout(this.searchTimer)
            clearTimeout(this.flashTimer)
        },
        methods: {
            load() {
                this.page = 1
                this.fetchEntries(false)
            },
            loadMore() {
                this.page += 1
                this.fetchEntries(true)
            },
            fetchEntries(append) {
                this.isLoading = true
                this.axios.get('waitlist/entries', {
                    params: {status: this.status, search: this.search.trim(), page: this.page, per_page: this.perPage},
                }).then((response) => {
                    const data = response.data || {}
                    const items = data.items || []
                    this.entries = append ? this.entries.concat(items) : items
                    this.total = data.total || 0
                    this.hasMore = !!data.has_more
                }).catch(() => {
                    if (!append) {
                        this.entries = []
                    }
                    this.hasMore = false
                }).finally(() => {
                    this.isLoading = false
                })
            },
            onSearchInput() {
                clearTimeout(this.searchTimer)
                this.searchTimer = setTimeout(() => this.load(), 350)
            },
            clearSearch() {
                clearTimeout(this.searchTimer)
                this.search = ''
                this.load()
            },
            setStatus(value) {
                if (this.status === value) {
                    return
                }
                this.status = value
                this.load()
            },
            notify(entryId) {
                if (this.notifyingId) {
                    return
                }
                this.notifyingId = entryId
                this.dismissFlash()
                this.axios.post('waitlist/entries/' + entryId + '/notify', {})
                    .then((response) => {
                        const updated = response.data && response.data.entry
                        if (updated) {
                            const idx = this.entries.findIndex(e => e.id === entryId)
                            if (idx !== -1) {
                                this.entries.splice(idx, 1, updated)
                            }
                        }
                        this.setFlash((response.data && response.data.message) || 'The customer has been notified.', 'success')
                    })
                    .catch((error) => {
                        const msg = error && error.response && error.response.data ? error.response.data.message : null
                        this.setFlash(msg || 'Could not notify the customer.', 'error')
                    })
                    .finally(() => {
                        this.notifyingId = null
                    })
            },
            setFlash(message, type) {
                this.flash = message
                this.flashType = type || 'success'
                clearTimeout(this.flashTimer)
                this.flashTimer = setTimeout(() => {
                    this.flash = null
                }, 5000)
            },
            dismissFlash() {
                clearTimeout(this.flashTimer)
                this.flash = null
            },
            initials(e) {
                const parts = (e.name || '').trim().split(/\s+/)
                const f = (parts[0] || '')[0] || ''
                const l = parts.length > 1 ? (parts[parts.length - 1][0] || '') : ''
                return (f + l).toUpperCase() || '?'
            },
            phoneOf(e) {
                if (!e.phone) {
                    return ''
                }
                return (e.phone_country_code || '') + e.phone
            },
            waNumber(e) {
                return this.phoneOf(e).replace(/[^0-9]/g, '')
            },
            requestedLabel(requested) {
                if (!requested) {
                    return 'Any'
                }
                return String(requested).split(' ').map((part) => {
                    if (/^\d{4}-\d{2}-\d{2}$/.test(part)) {
                        return this.dateFormat(part)
                    }
                    if (/^\d{1,2}:\d{2}/.test(part)) {
                        return this.timeFormat(part)
                    }
                    return part
                }).join(' ')
            },
            statusLabel(status) {
                const map = {
                    active: 'Active',
                    offered: 'Offered',
                    accepted: 'Booked',
                    expired: 'Expired',
                    paused: 'Paused',
                    opted_out: 'Opted out',
                }
                return map[status] || status
            },
        },
        emits: ['hideTabsHeader'],
    }
</script>

<style scoped>
.wl-screen { padding-bottom: 100px; }
.wl-title {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text-primary, #0F172A);
  margin: 8px 0 12px;
}
.wl-search {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #E2E8F0);
  border-radius: var(--radius-md, 12px);
  padding: 10px 12px;
}
.wl-search-icon { color: var(--color-text-muted, #94A3B8); font-size: 14px; }
.wl-search-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-size: 14px;
  color: var(--color-text-primary, #0F172A);
  min-width: 0;
}
.wl-search-clear {
  border: none;
  background: none;
  color: var(--color-text-muted, #94A3B8);
  cursor: pointer;
  padding: 2px 4px;
}
.wl-filters {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding: 12px 0 4px;
  -webkit-overflow-scrolling: touch;
}
.wl-chip {
  flex-shrink: 0;
  border: 1px solid var(--color-border, #E2E8F0);
  background: var(--color-surface, #fff);
  color: var(--color-text-secondary, #64748B);
  border-radius: var(--radius-pill, 999px);
  font-size: 13px;
  font-weight: 500;
  padding: 6px 14px;
  cursor: pointer;
}
.wl-chip--active {
  background: var(--color-primary, #2563EB);
  border-color: var(--color-primary, #2563EB);
  color: #fff;
}
.wl-flash {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 13px;
  line-height: 1.4;
  border-radius: var(--radius-sm, 8px);
  padding: 10px 10px 10px 12px;
  margin: 10px 0;
}
.wl-flash-icon { font-size: 15px; margin-top: 1px; flex-shrink: 0; }
.wl-flash-text { flex: 1; min-width: 0; }
.wl-flash-close {
  border: none;
  background: none;
  cursor: pointer;
  padding: 0 2px;
  font-size: 13px;
  color: inherit;
  opacity: 0.55;
  flex-shrink: 0;
}
.wl-flash-close:hover { opacity: 1; }
.wl-flash--success { color: #166534; background: #dcfce7; }
.wl-flash--error { color: #b42318; background: #fee4e2; }
.wl-flash-fade-enter-active,
.wl-flash-fade-leave-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.wl-flash-fade-enter-from,
.wl-flash-fade-leave-to { opacity: 0; transform: translateY(-4px); }
.wl-skeleton {
  height: 96px;
  border-radius: var(--radius-md, 12px);
  margin-top: 12px;
  background: linear-gradient(90deg, #E2E8F0 25%, #CBD5E1 50%, #E2E8F0 75%);
  background-size: 200% 100%;
  animation: wl-shimmer 1.2s infinite;
}
@keyframes wl-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
.wl-empty {
  text-align: center;
  color: var(--color-text-muted, #94A3B8);
  padding: 48px 16px;
}
.wl-empty-icon { font-size: 32px; margin-bottom: 12px; opacity: 0.5; }
.wl-empty-text { font-size: 14px; margin: 0; }
.wl-list { margin-top: 12px; display: flex; flex-direction: column; gap: 12px; }
.wl-card {
  background: var(--color-surface, #fff);
  border-radius: var(--radius-md, 12px);
  padding: 14px;
}
.wl-card-top { display: flex; align-items: center; gap: 12px; }
.wl-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
}
.wl-info { flex: 1; min-width: 0; }
.wl-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.wl-sub {
  font-size: 13px;
  color: var(--color-text-secondary, #64748B);
  margin-top: 2px;
}
.wl-when {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  margin-top: 5px;
  padding: 2px 8px;
  border-radius: var(--radius-sm, 8px);
  background: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
}
.wl-when-icon { font-size: 11px; }
.wl-when-text {
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.01em;
}
.wl-status {
  flex-shrink: 0;
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: var(--radius-pill, 999px);
  text-transform: uppercase;
  letter-spacing: 0.03em;
  background: #f1f5f9;
  color: #64748B;
}
.wl-status--active { background: #dcfce7; color: #166534; }
.wl-status--offered { background: #fef9c3; color: #854d0e; }
.wl-status--accepted { background: #dbeafe; color: #1e40af; }
.wl-status--expired { background: #fee2e2; color: #b91c1c; }
.wl-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-top: 12px;
}
.wl-contact { display: flex; gap: 8px; }
.wl-contact-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
  font-size: 15px;
  text-decoration: none;
}
.wl-nophone { font-size: 12px; color: var(--color-text-muted, #94A3B8); }
.wl-notify {
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius-pill, 999px);
  background: var(--color-primary, #2563EB);
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  padding: 9px 18px;
  min-height: 38px;
  cursor: pointer;
}
.wl-notify:disabled { opacity: 0.5; cursor: default; }
.wl-notify--secondary:not(:disabled) {
  background: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
}
.wl-reason {
  font-size: 12px;
  color: var(--color-text-muted, #94A3B8);
  margin: 8px 0 0;
}
.wl-more-btn {
  border: 1px solid var(--color-border, #E2E8F0);
  background: var(--color-surface, #fff);
  color: var(--color-primary, #2563EB);
  border-radius: var(--radius-md, 12px);
  font-size: 14px;
  font-weight: 600;
  padding: 12px;
  cursor: pointer;
  margin-top: 4px;
}
.wl-more-btn:disabled { opacity: 0.6; cursor: default; }
</style>
