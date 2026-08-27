<template>
  <div class="soap-voice-field">
    <div class="soap-voice-label-row">
      <slot name="label">
        <label class="field-label">{{ label }}</label>
      </slot>
      <button
        v-if="supported"
        type="button"
        class="soap-voice-btn"
        :class="{ 'soap-voice-btn--on': listening }"
        :aria-pressed="listening ? 'true' : 'false'"
        :title="listening ? stopTitle : startTitle"
        @click="toggle"
      >
        <font-awesome-icon :icon="listening ? 'fa-solid fa-stop' : 'fa-solid fa-microphone'" />
        <span>{{ listening ? stopTitle : startTitle }}</span>
      </button>
    </div>
    <b-form-textarea
      :model-value="displayValue"
      :placeholder="placeholder"
      rows="4"
      max-rows="10"
      @update:model-value="onType"
    />
    <p class="soap-voice-status soap-voice-status--live" v-if="listening">
      {{ listeningLabel }}
    </p>
    <p class="soap-voice-status soap-voice-status--err" v-else-if="statusError">
      {{ statusError }}
    </p>
  </div>
</template>

<script>
import mixins from '@/mixin'
import {
    appendTranscript,
    isSpeechToTextSupported,
    startSpeechToText,
    stopActiveSpeechToText,
} from '@/utils/speechToText'

export default {
    name: 'SoapVoiceTextarea',
    mixins: [mixins],
    props: {
        label: { type: String, default: '' },
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: '' },
    },
    data() {
        return {
            supported: isSpeechToTextSupported(),
            listening: false,
            interim: '',
            statusError: '',
            stopFn: null,
        }
    },
    computed: {
        displayValue() {
            if (!this.listening || !this.interim) {
                return this.modelValue || ''
            }
            return appendTranscript(this.modelValue, this.interim)
        },
        startTitle() {
            return this.getLabel('soapNotesVoiceStart') || 'Speak'
        },
        stopTitle() {
            return this.getLabel('soapNotesVoiceStop') || 'Stop'
        },
        listeningLabel() {
            return this.getLabel('soapNotesVoiceListening') || 'Listening… tap Stop when you are done'
        },
    },
    beforeUnmount() {
        this.stop()
    },
    methods: {
        onType(value) {
            if (this.listening) {
                this.stop()
            }
            this.$emit('update:modelValue', value)
        },
        toggle() {
            if (this.listening) {
                this.stop()
                return
            }
            this.start()
        },
        start() {
            this.statusError = ''
            this.interim = ''
            this.listening = true
            this.stopFn = startSpeechToText({
                onStart: () => {
                    this.listening = true
                },
                onFinal: (text) => {
                    this.$emit('update:modelValue', appendTranscript(this.modelValue, text))
                    this.interim = ''
                },
                onInterim: (text) => {
                    this.interim = text
                },
                onError: (code) => {
                    this.commitInterim()
                    this.listening = false
                    this.statusError = this.messageFor(code)
                },
                onEnd: () => {
                    this.commitInterim()
                    this.listening = false
                },
            })
        },
        stop() {
            this.commitInterim()
            if (this.stopFn) {
                this.stopFn()
                this.stopFn = null
            } else {
                stopActiveSpeechToText()
            }
            this.listening = false
            this.interim = ''
        },
        commitInterim() {
            if (this.interim) {
                this.$emit('update:modelValue', appendTranscript(this.modelValue, this.interim))
                this.interim = ''
            }
        },
        messageFor(code) {
            if (code === 'unsupported') {
                return this.getLabel('soapNotesVoiceUnsupported') || 'Voice input is not supported in this browser.'
            }
            if (code === 'denied') {
                return this.getLabel('soapNotesVoiceDenied') || 'Microphone permission was denied.'
            }
            if (code === 'network') {
                return this.getLabel('soapNotesVoiceNetwork') || 'Voice input needs a network connection in this browser.'
            }
            return this.getLabel('soapNotesVoiceError') || 'Could not start voice input.'
        },
    },
    emits: ['update:modelValue'],
}
</script>

<style scoped>
.soap-voice-label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 6px;
}
.field-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary, #64748B);
  margin: 0;
}
.soap-voice-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: none;
  border-radius: var(--radius-pill, 999px);
  background: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
  font-size: 12px;
  font-weight: 700;
  padding: 6px 12px;
  min-height: 32px;
  cursor: pointer;
  flex-shrink: 0;
}
.soap-voice-btn--on {
  background: #FEE2E2;
  color: #DC2626;
  animation: soap-voice-pulse 1.4s ease-in-out infinite;
}
.soap-voice-status {
  margin: 6px 0 0;
  font-size: 12px;
}
.soap-voice-status--live { color: var(--color-primary, #2563EB); }
.soap-voice-status--err { color: #DC2626; }
.soap-voice-field :deep(.form-control) {
  border: 1.5px solid var(--color-border, #E2E8F0);
  border-radius: var(--radius-sm, 8px);
  padding: 10px 12px;
  font-size: 15px;
  color: var(--color-text-primary, #0F172A);
}
.soap-voice-field :deep(.form-control:focus) {
  border-color: var(--color-primary, #2563EB);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  outline: none;
}
@keyframes soap-voice-pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.65; }
}
</style>
