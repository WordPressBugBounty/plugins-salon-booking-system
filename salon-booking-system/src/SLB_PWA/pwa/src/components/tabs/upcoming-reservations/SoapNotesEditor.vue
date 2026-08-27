<template>
  <div class="soap-editor-screen">
    <div class="detail-header">
      <button class="back-btn" type="button" @click="close">
        <font-awesome-icon icon="fa-solid fa-arrow-left" />
      </button>
      <h1 class="detail-title">{{ getLabel('soapNotesTitle') || 'SOAP Notes' }}</h1>
      <span class="back-btn-placeholder"></span>
    </div>

    <div class="soap-loading" v-if="isLoading">
      <b-spinner variant="primary" />
    </div>

    <div class="form-card" v-else-if="loadError">
      <p class="soap-empty">{{ getLabel('soapNotesLoadError') || 'Could not load SOAP notes' }}</p>
    </div>

    <template v-else>
      <div class="form-card soap-standing-card" v-if="hasCustomer">
        <SoapVoiceTextarea
          v-model="standingText"
          :placeholder="getLabel('soapNotesStandingPlaceholder') || 'Allergies, contraindications, ongoing plan…'"
        >
          <template #label>
            <div class="soap-standing-identity">
              <span class="soap-standing-pin" aria-hidden="true">
                <font-awesome-icon icon="fa-solid fa-thumbtack" />
              </span>
              <div class="soap-standing-copy">
                <div class="soap-standing-title-row">
                  <span class="soap-standing-title">{{ getLabel('soapNotesStandingTitle') || 'Standing notes' }}</span>
                  <span class="soap-standing-chip">{{ getLabel('soapNotesStandingChip') || 'Every visit' }}</span>
                </div>
                <span class="soap-standing-sub">{{ getLabel('soapNotesStandingHint') || 'Saved on this customer and shown on every visit' }}</span>
              </div>
            </div>
          </template>
        </SoapVoiceTextarea>
        <p class="soap-standing-status" v-if="standingSaved">{{ getLabel('soapNotesStandingSaved') || 'Standing notes saved' }}</p>
        <p class="soap-standing-status soap-standing-status--err" v-if="standingError">{{ standingError }}</p>
      </div>

      <div class="form-card" v-if="previousVisit && fields.length">
        <button class="soap-copy-btn" type="button" :disabled="isCopying" @click="copyPrevious">
          <b-spinner small v-if="isCopying" />
          <font-awesome-icon v-else icon="fa-regular fa-clipboard" />
          {{ copyPreviousLabel }}
        </button>
        <p class="soap-standing-hint">{{ getLabel('soapNotesCopyHint') || 'Fills this visit only. Does not copy photos or standing notes.' }}</p>
      </div>

      <div class="form-card" v-if="!fields.length && !photos.length">
        <p class="soap-empty">{{ getLabel('soapNotesEmpty') || 'No SOAP notes yet' }}</p>
      </div>
      <div
        v-for="field in fields"
        :key="field.index"
        class="form-card"
        :class="{ 'form-card--half': field.layout === 'half' }"
      >
        <div v-if="field.type === 'input'" class="form-field">
          <label class="field-label">{{ field.label }}</label>
          <b-form-input v-model="field.value" :placeholder="field.placeholder || field.default || ''" />
        </div>

        <div v-else-if="field.type === 'textarea'" class="form-field">
          <SoapVoiceTextarea
            v-model="field.value"
            :label="field.label"
            :placeholder="field.placeholder || field.default || ''"
          />
        </div>

        <div v-else-if="field.type === 'select'" class="form-field">
          <label class="field-label">{{ field.label }}</label>
          <select class="soap-select" v-model="field.value">
            <option value="">—</option>
            <option v-for="opt in field.options" :key="opt.key" :value="opt.key">{{ opt.label }}</option>
          </select>
        </div>

        <div v-else-if="field.type === 'radio'" class="form-field">
          <label class="field-label">{{ field.label }}</label>
          <label class="soap-choice" v-for="opt in field.options" :key="opt.key">
            <input type="radio" :name="'soap-radio-' + field.index" :value="opt.key" v-model="field.value" />
            <span>{{ opt.label }}</span>
          </label>
        </div>

        <div v-else-if="field.type === 'checkbox'" class="form-field">
          <label class="field-label">{{ field.label }}</label>
          <label class="soap-choice" v-for="opt in field.options" :key="opt.key">
            <input type="checkbox" :value="opt.key" :checked="isChecked(field, opt.key)" @change="toggleCheck(field, opt.key)" />
            <span>{{ opt.label }}</span>
          </label>
        </div>

        <SoapNotesImage
          v-else-if="field.type === 'interactive-image'"
          :field="field"
          :add-label="getLabel('soapNotesAddPin') || 'Add note'"
          :clear-label="getLabel('soapNotesClearPins') || 'Clear all notes'"
          :new-pin-label="getLabel('soapNotesNewPin') || 'New note'"
          :placeholder="getLabel('soapNotesPinPlaceholder') || 'Note'"
        />
      </div>

      <div class="form-card">
        <SoapVisitPhotos
          :booking-id="bookingId"
          :photos="photos"
          @update:photos="photos = $event"
          @change="onPhotosChange"
        />
      </div>

      <div class="form-card actions-card" v-if="fields.length">
        <button class="save-btn" type="button" @click="save" :disabled="isSaving">
          <b-spinner small v-if="isSaving" />
          {{ getLabel('soapNotesSaveButton') || 'Save notes' }}
        </button>
        <b-alert :show="isSaved" fade variant="success" class="mt-2">{{ getLabel('soapNotesSavedLabel') || 'SOAP notes saved' }}</b-alert>
        <b-alert :show="isError" fade variant="danger" class="mt-2">{{ errorMessage }}</b-alert>
      </div>
    </template>
  </div>
</template>

<script>
import mixins from '@/mixin'
import SoapNotesImage from './SoapNotesImage.vue'
import SoapVoiceTextarea from './SoapVoiceTextarea.vue'
import SoapVisitPhotos from './SoapVisitPhotos.vue'
import { stopActiveSpeechToText } from '@/utils/speechToText'

export default {
    name: 'SoapNotesEditor',
    mixins: [mixins],
    components: { SoapNotesImage, SoapVoiceTextarea, SoapVisitPhotos },
    props: {
        bookingId: { type: [Number, String], required: true },
    },
    data() {
        return {
            isLoading: true,
            isSaving: false,
            isSaved: false,
            isError: false,
            loadError: false,
            errorMessage: '',
            fields: [],
            photos: [],
            hasNotes: false,
            standingText: '',
            standingSaved: false,
            standingError: '',
            customerId: 0,
            previousVisit: null,
            isCopying: false,
            standingTimer: null,
        }
    },
    computed: {
        hasCustomer() {
            return Number(this.customerId) > 0
        },
        copyPreviousLabel() {
            if (this.previousVisit && this.previousVisit.date) {
                const dated = this.getLabel('soapNotesCopyLastVisitDated') || 'Copy from last visit ({date})'
                return dated.replace('{date}', this.dateFormat(this.previousVisit.date))
            }
            return this.getLabel('soapNotesCopyLastVisit') || 'Copy from last visit'
        },
    },
    mounted() {
        this.load()
    },
    beforeUnmount() {
        stopActiveSpeechToText()
        if (this.standingTimer) {
            clearTimeout(this.standingTimer)
        }
    },
    methods: {
        close() {
            this.$emit('close', { hasNotes: this.hasNotes || this.photos.length > 0 })
        },
        isChecked(field, key) {
            return Array.isArray(field.value) && field.value.map(String).includes(String(key))
        },
        toggleCheck(field, key) {
            const current = Array.isArray(field.value) ? field.value.map(String) : []
            const k = String(key)
            if (current.includes(k)) {
                field.value = current.filter((item) => item !== k)
            } else {
                field.value = current.concat([k])
            }
        },
        load() {
            this.isLoading = true
            this.loadError = false
            this.axios.get('soap-notes/bookings/' + this.bookingId)
                .then((response) => {
                    this.fields = (response.data && response.data.fields) || []
                    this.photos = (response.data && response.data.photos) || []
                    this.hasNotes = !!(response.data && response.data.has_notes)
                    this.applyStanding(response.data)
                    this.previousVisit = (response.data && response.data.previous_visit) || null
                })
                .catch(() => {
                    this.loadError = true
                    this.fields = []
                    this.photos = []
                })
                .finally(() => {
                    this.isLoading = false
                })
        },
        save() {
            this.isSaving = true
            this.isError = false
            this.axios.put('soap-notes/bookings/' + this.bookingId, { fields: this.fields })
                .then((response) => {
                    this.fields = (response.data && response.data.fields) || this.fields
                    if (response.data && Array.isArray(response.data.photos)) {
                        this.photos = response.data.photos
                    }
                    this.hasNotes = !!(response.data && response.data.has_notes)
                    this.isSaved = true
                    setTimeout(() => { this.isSaved = false }, 2500)
                })
                .catch((error) => {
                    this.isError = true
                    this.errorMessage = (error.response && error.response.data && error.response.data.message)
                        || this.getLabel('soapNotesErrorLabel')
                        || 'Could not save SOAP notes'
                    setTimeout(() => { this.isError = false }, 3500)
                })
                .finally(() => {
                    this.isSaving = false
                })
        },
        onPhotosChange(photos) {
            this.photos = photos || []
            this.hasNotes = this.hasNotes || this.photos.length > 0
            if (!this.photos.length && !this.fields.length) {
                this.hasNotes = false
            }
        },
        applyStanding(data) {
            const standing = data && data.standing_notes
            if (standing && typeof standing === 'object') {
                this.customerId = standing.customer_id || 0
                this.standingText = standing.text || ''
            } else {
                this.customerId = 0
                this.standingText = ''
            }
        },
        applyPayload(data) {
            if (!data) {
                return
            }
            this.fields = data.fields || this.fields
            if (Array.isArray(data.photos)) {
                this.photos = data.photos
            }
            this.hasNotes = !!data.has_notes
            this.applyStanding(data)
            this.previousVisit = data.previous_visit || null
        },
        copyPrevious() {
            if (this.hasNotes) {
                const date = this.previousVisit && this.previousVisit.date
                    ? this.dateFormat(this.previousVisit.date)
                    : ''
                const message = (this.getLabel('soapNotesCopyConfirm') || 'Replace this visit’s SOAP notes with the notes from {date}?')
                    .replace('{date}', date)
                if (!window.confirm(message)) {
                    return
                }
            }
            this.isCopying = true
            this.isError = false
            this.axios.post('soap-notes/bookings/' + this.bookingId + '/copy-previous')
                .then((response) => {
                    this.applyPayload(response.data)
                    this.isSaved = true
                    setTimeout(() => { this.isSaved = false }, 2500)
                })
                .catch((error) => {
                    this.isError = true
                    this.errorMessage = (error.response && error.response.data && error.response.data.message)
                        || this.getLabel('soapNotesCopyError')
                        || 'Could not copy previous notes'
                    setTimeout(() => { this.isError = false }, 3500)
                })
                .finally(() => {
                    this.isCopying = false
                })
        },
        saveStanding() {
            if (!this.hasCustomer) {
                return
            }
            this.standingError = ''
            this.axios.put('soap-notes/bookings/' + this.bookingId + '/standing-notes', { text: this.standingText })
                .then(() => {
                    this.standingSaved = true
                    setTimeout(() => { this.standingSaved = false }, 2000)
                })
                .catch((error) => {
                    this.standingError = (error.response && error.response.data && error.response.data.message)
                        || this.getLabel('soapNotesErrorLabel')
                        || 'Could not save SOAP notes'
                })
        },
    },
    watch: {
        standingText() {
            if (this.isLoading || !this.hasCustomer) {
                return
            }
            if (this.standingTimer) {
                clearTimeout(this.standingTimer)
            }
            this.standingTimer = setTimeout(() => {
                this.saveStanding()
            }, 700)
        },
    },
    emits: ['close'],
}
</script>

<style scoped>
.soap-editor-screen {
  min-height: 100vh;
  background: var(--color-background, #F4F6FA);
  padding-bottom: 32px;
}
.detail-header {
  display: flex;
  align-items: center;
  padding: 12px var(--spacing-page, 16px);
  position: sticky;
  top: 0;
  z-index: 10;
  background: var(--color-background, #F4F6FA);
}
.back-btn {
  background: none;
  border: none;
  padding: 6px 8px;
  color: var(--color-text-primary, #0F172A);
  font-size: 18px;
  cursor: pointer;
  min-width: 40px;
  min-height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}
.back-btn-placeholder { min-width: 40px; }
.detail-title {
  flex: 1;
  text-align: center;
  font-size: 17px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
  margin: 0;
}
.soap-loading {
  display: flex;
  justify-content: center;
  padding: 40px 0;
}
.form-card {
  background: var(--color-surface, #fff);
  border-radius: var(--radius-md, 12px);
  margin: 12px var(--spacing-page, 16px) 0;
  padding: var(--spacing-card, 14px);
}
.form-field { margin-bottom: 0; }
.field-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary, #64748B);
  margin-bottom: 5px;
  display: block;
}
.form-field :deep(.form-control) {
  border: 1.5px solid var(--color-border, #E2E8F0);
  border-radius: var(--radius-sm, 8px);
  padding: 10px 12px;
  font-size: 15px;
  color: var(--color-text-primary, #0F172A);
}
.form-field :deep(.form-control:focus) {
  border-color: var(--color-primary, #2563EB);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  outline: none;
}
.soap-select {
  width: 100%;
  border: 1.5px solid var(--color-border, #E2E8F0);
  border-radius: var(--radius-sm, 8px);
  padding: 10px 12px;
  font-size: 15px;
  background: #fff;
  color: var(--color-text-primary, #0F172A);
}
.soap-choice {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 0;
  font-size: 14px;
  color: var(--color-text-primary, #0F172A);
}
.soap-empty {
  margin: 0;
  font-size: 14px;
  color: var(--color-text-muted, #94A3B8);
}
.save-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  border-radius: var(--radius-pill, 999px);
  border: none;
  background: var(--color-primary, #2563EB);
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  width: 100%;
  justify-content: center;
}
.save-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.soap-standing-card {
  background: #FFFBEB;
  border: 1.5px solid #F6D98B;
  box-shadow: inset 4px 0 0 #D97706;
  padding-left: 16px;
}
.soap-standing-card :deep(.soap-voice-label-row) {
  align-items: flex-start;
  margin-bottom: 10px;
}
.soap-standing-card :deep(.soap-voice-btn) {
  background: #FEF3C7;
  color: #B45309;
  flex-shrink: 0;
}
.soap-standing-card :deep(.soap-voice-btn--on) {
  background: #D97706;
  color: #fff;
}
.soap-standing-card :deep(.form-control) {
  background: #fff;
  border-color: #F3D19A;
}
.soap-standing-card :deep(.form-control:focus) {
  border-color: #D97706;
  box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15);
}
.soap-standing-identity {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  min-width: 0;
}
.soap-standing-pin {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: #FDE68A;
  color: #B45309;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  flex-shrink: 0;
  margin-top: 1px;
}
.soap-standing-copy {
  min-width: 0;
}
.soap-standing-title-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}
.soap-standing-title {
  font-size: 15px;
  font-weight: 700;
  color: #92400E;
  line-height: 1.2;
}
.soap-standing-chip {
  display: inline-flex;
  align-items: center;
  background: #D97706;
  color: #fff;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  border-radius: 999px;
  padding: 2px 8px;
  line-height: 1.4;
}
.soap-standing-sub {
  display: block;
  margin-top: 3px;
  font-size: 12px;
  color: #B45309;
  line-height: 1.35;
}
.soap-standing-hint {
  font-size: 12px;
  color: var(--color-text-muted, #94A3B8);
  margin: 8px 0 0;
}
.soap-standing-status {
  font-size: 12px;
  color: #16A34A;
  margin: 6px 0 0;
}
.soap-standing-status--err { color: #DC2626; }
.soap-copy-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  min-height: 40px;
  border: 1.5px solid var(--color-border, #E2E8F0);
  border-radius: var(--radius-sm, 8px);
  background: #fff;
  color: var(--color-text-primary, #0F172A);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
.soap-copy-btn:disabled { opacity: 0.6; }
</style>
