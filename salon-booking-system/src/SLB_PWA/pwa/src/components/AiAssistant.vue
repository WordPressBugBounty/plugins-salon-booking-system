<template>
    <div v-if="enabled" v-show="!hidden || open" class="ai-assistant" :class="{ 'is-busy': busy }">
        <section
            v-show="open"
            id="sln-pwa-ai-panel"
            class="ai-assistant__panel"
            role="dialog"
            aria-modal="false"
            aria-labelledby="sln-pwa-ai-title"
            @keydown.esc="closePanel"
        >
            <header class="ai-assistant__header">
                <div class="ai-assistant__header-main">
                    <div class="ai-assistant__header-icon" aria-hidden="true">
                        <img v-if="iconUrl" :src="iconUrl" alt="" width="20" height="20" />
                    </div>
                    <span id="sln-pwa-ai-title" class="ai-assistant__title">{{ getLabel('aiAssistantTitle') }}</span>
                </div>
                <button
                    type="button"
                    class="ai-assistant__close"
                    :aria-label="getLabel('aiAssistantClose')"
                    @click="closePanel"
                >
                    <span aria-hidden="true">&times;</span>
                </button>
            </header>

            <div class="ai-assistant__body">
                <div ref="scroll" class="ai-assistant__scroll">
                    <div class="ai-assistant__messages" aria-live="polite">
                        <div
                            v-for="msg in messages"
                            :key="msg.id"
                            class="ai-assistant__msg"
                            :class="'ai-assistant__msg--' + msg.role"
                        >
                            <div class="ai-assistant__msg-body" v-html="formatRichText(msg.text)"></div>
                            <div v-if="msg.support" class="ai-assistant__msg-actions">
                                <a
                                    class="ai-assistant__chip"
                                    :href="msg.support.fallback_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >{{ msg.support.label || getLabel('aiAssistantContactSupport') }}</a>
                            </div>
                            <div v-if="msg.setupLink && setupUrl" class="ai-assistant__msg-actions">
                                <a class="ai-assistant__chip" :href="setupUrl" target="_blank" rel="noopener">
                                    {{ getLabel('aiAssistantOpenSetup') }}
                                </a>
                            </div>
                            <div v-if="msg.undoable && canUndo" class="ai-assistant__msg-actions">
                                <button type="button" class="ai-assistant__chip" :disabled="busy" @click="undo">
                                    {{ getLabel('aiAssistantUndo') }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="busy"
                            class="ai-assistant__msg ai-assistant__msg--thinking"
                            role="status"
                            :aria-label="getLabel('aiAssistantThinking')"
                        >
                            <div class="ai-assistant__thinking" aria-hidden="true">
                                <img v-if="iconUrl" class="ai-assistant__thinking-icon" :src="iconUrl" alt="" width="24" height="24" draggable="false" />
                                <span class="ai-assistant__thinking-label">{{ thinkingLabel }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="preview" class="ai-assistant__preview">
                        <h3 class="ai-assistant__preview-title">{{ getLabel('aiAssistantPreviewTitle') }}</h3>
                        <div class="ai-assistant__preview-body" v-html="formatRichText(preview.summary)"></div>
                        <div class="ai-assistant__preview-actions">
                            <button type="button" class="ai-assistant__button ai-assistant__button--primary" :disabled="busy" @click="confirmPreview(false)">
                                {{ getLabel('aiAssistantConfirm') }}
                            </button>
                            <button type="button" class="ai-assistant__button" :disabled="busy" @click="confirmPreview(true)">
                                {{ getLabel('aiAssistantCancel') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!online" class="ai-assistant__notice">{{ getLabel('aiAssistantOffline') }}</div>
                <div v-else-if="voiceError" class="ai-assistant__notice">{{ voiceError }}</div>

                <form class="ai-assistant__composer" :class="{ 'is-listening': listening }" @submit.prevent="send()">
                    <textarea
                        ref="input"
                        v-model="draft"
                        rows="1"
                        :aria-label="getLabel('aiAssistantPlaceholder')"
                        :placeholder="getLabel('aiAssistantPlaceholder')"
                        :disabled="!canChat"
                        @input="autoGrow"
                        @keydown.enter.exact.prevent="send()"
                    ></textarea>
                    <button
                        v-if="voiceSupported"
                        type="button"
                        class="ai-assistant__mic"
                        :aria-label="listening ? getLabel('aiAssistantVoiceStop') : getLabel('aiAssistantVoiceStart')"
                        :aria-pressed="listening ? 'true' : 'false'"
                        :disabled="!canChat || busy"
                        @click="toggleVoice"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
                    </button>
                    <button
                        type="submit"
                        class="ai-assistant__button ai-assistant__button--primary ai-assistant__send"
                        :disabled="!canChat || busy || !draft.trim()"
                    >{{ getLabel('aiAssistantSend') }}</button>
                </form>

                <div v-if="setupUrl" class="ai-assistant__footer-actions">
                    <a class="ai-assistant__full-link" :href="setupUrl" target="_blank" rel="noopener">
                        {{ getLabel('aiAssistantOpenFullSetup') }}
                    </a>
                </div>
            </div>
        </section>

        <button
            type="button"
            class="ai-assistant__fab"
            :aria-expanded="open ? 'true' : 'false'"
            aria-controls="sln-pwa-ai-panel"
            :aria-label="open ? getLabel('aiAssistantClose') : getLabel('aiAssistantOpen')"
            @click="open ? closePanel() : openPanel()"
        >
            <span class="ai-assistant__fab-icon" aria-hidden="true">
                <img v-if="iconUrl" :src="iconUrl" alt="" width="28" height="28" />
            </span>
        </button>
    </div>
</template>

<script>
import {
    appendTranscript,
    isSpeechToTextSupported,
    startSpeechToText,
    stopActiveSpeechToText,
} from '../utils/speechToText'

const STORAGE_KEY = 'sln_pwa_ai_chat'
const MAX_STORED_MESSAGES = 40

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
}

function renderLink(url, label) {
    return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(label) + '</a>'
}

export default {
    name: 'AiAssistant',
    props: {
        /** Hidden while a full-screen booking/customer editor covers the tabs. */
        hidden: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['changed'],
    data() {
        const config = (window.slnPWA && window.slnPWA.ai) || {}
        return {
            config,
            open: false,
            busy: false,
            draft: '',
            sessionId: null,
            messages: [],
            preview: null,
            canUndo: false,
            usage: config.usage || null,
            online: typeof navigator === 'undefined' ? true : navigator.onLine !== false,
            listening: false,
            voiceError: '',
            voiceSupported: isSpeechToTextSupported(),
            /** Set when a change was applied or undone, so the tabs reload on close. */
            dirty: false,
            nextId: 1,
        }
    },
    computed: {
        enabled() {
            return !!this.config.enabled
        },
        setupUrl() {
            return this.config.setup_url || ''
        },
        canChat() {
            return this.online && !(this.usage && this.usage.can_chat === false)
        },
        iconUrl() {
            return this.config.icon_url || ''
        },
        /** Same "Thinking.." label as the wp-admin calendar assistant. */
        thinkingLabel() {
            return String(this.getLabel('aiAssistantThinking') || '').replace(/(?:\.\.\.|…|\.|·|\s)+$/g, '') + '..'
        },
    },
    mounted() {
        this.restore()
        window.addEventListener('online', this.updateOnline)
        window.addEventListener('offline', this.updateOnline)
    },
    beforeUnmount() {
        window.removeEventListener('online', this.updateOnline)
        window.removeEventListener('offline', this.updateOnline)
        this.stopVoice()
    },
    methods: {
        updateOnline() {
            this.online = navigator.onLine !== false
        },
        openPanel() {
            this.open = true
            if (!this.sessionId) {
                this.startSession()
            }
            this.$nextTick(() => {
                this.scrollToEnd()
                if (this.$refs.input) {
                    this.$refs.input.focus()
                }
            })
        },
        closePanel() {
            this.stopVoice()
            this.open = false
            if (this.dirty) {
                this.dirty = false
                this.$emit('changed')
            }
        },
        startSession() {
            if (!this.online) {
                return
            }
            this.busy = true
            this.axios.get('ai/session', { params: { variant: 'widget' } })
                .then((response) => {
                    const data = response.data || {}
                    this.sessionId = data.session_id || null
                    this.canUndo = !!data.can_undo
                    if (data.usage) {
                        this.usage = data.usage
                    }
                    if (data.welcome && !this.messages.length) {
                        this.push('assistant', data.welcome)
                    }
                })
                .catch((error) => {
                    this.push('error', this.errorMessage(error))
                })
                .finally(() => {
                    this.busy = false
                    this.persist()
                })
        },
        send(text) {
            const message = String(typeof text === 'string' ? text : this.draft).trim()
            if (!message || this.busy) {
                return
            }
            if (!this.canChat) {
                if (this.usage && this.usage.can_chat === false) {
                    this.pushExhausted()
                }
                return
            }
            this.stopVoice()
            this.push('user', message)
            this.draft = ''
            this.preview = null
            this.$nextTick(this.autoGrow)
            this.busy = true
            this.axios.post('ai/chat', { session_id: this.sessionId, message })
                .then((response) => {
                    this.handleReply(response.data || {})
                })
                .catch((error) => {
                    this.handleError(error)
                })
                .finally(() => {
                    this.busy = false
                    this.persist()
                })
        },
        handleReply(data) {
            if (data.session_id) {
                this.sessionId = data.session_id
            }
            if (data.usage) {
                this.usage = data.usage
            }
            if (typeof data.can_undo !== 'undefined') {
                this.canUndo = !!data.can_undo
            }
            if (data.message) {
                const msg = this.push('assistant', data.message)
                if (data.support && data.support.fallback_url) {
                    msg.support = data.support
                }
            }
            this.preview = data.preview && data.preview.id && data.preview.summary ? data.preview : null
            this.scrollToEnd()
        },
        confirmPreview(cancel) {
            if (!this.preview || this.busy) {
                return
            }
            const previewId = this.preview.id
            this.busy = true
            this.axios.post('ai/confirm', { session_id: this.sessionId, preview_id: previewId, cancel: !!cancel })
                .then((response) => {
                    const data = response.data || {}
                    this.preview = null
                    this.canUndo = !!data.can_undo
                    const msg = this.push(cancel ? 'system' : 'assistant', data.message || '')
                    if (!cancel) {
                        this.messages.forEach((m) => { m.undoable = false })
                        msg.undoable = true
                        this.dirty = true
                    }
                })
                .catch((error) => {
                    this.preview = null
                    this.push('error', this.errorMessage(error))
                })
                .finally(() => {
                    this.busy = false
                    this.persist()
                })
        },
        undo() {
            if (this.busy) {
                return
            }
            this.busy = true
            this.axios.post('ai/undo', { session_id: this.sessionId })
                .then((response) => {
                    const data = response.data || {}
                    this.canUndo = false
                    this.messages.forEach((m) => { m.undoable = false })
                    this.push('system', data.message || '')
                    this.dirty = true
                })
                .catch((error) => {
                    this.push('error', this.errorMessage(error))
                })
                .finally(() => {
                    this.busy = false
                    this.persist()
                })
        },
        handleError(error) {
            const data = error && error.response && error.response.data
            if ((data && data.code === 'sln_ai_quota_exceeded') || (error.response && error.response.status === 402)) {
                if (data && data.data && data.data.usage) {
                    this.usage = data.data.usage
                } else {
                    this.usage = { ...(this.usage || {}), can_chat: false, total_remaining: 0 }
                }
                this.pushExhausted()
                return
            }
            this.push('error', this.errorMessage(error))
        },
        pushExhausted() {
            const msg = this.push('error', this.getLabel('aiAssistantExhausted'))
            msg.setupLink = true
        },
        errorMessage(error) {
            if (!this.online) {
                return this.getLabel('aiAssistantOffline')
            }
            const data = error && error.response && error.response.data
            return (data && data.message) || this.getLabel('aiAssistantError')
        },
        push(role, text) {
            const msg = { id: this.nextId++, role, text: String(text || '') }
            this.messages.push(msg)
            this.scrollToEnd()
            // Return the reactive copy so later flags (undo, support) re-render.
            return this.messages[this.messages.length - 1]
        },
        scrollToEnd() {
            this.$nextTick(() => {
                const el = this.$refs.scroll
                if (el) {
                    el.scrollTop = el.scrollHeight
                }
            })
        },
        autoGrow() {
            const el = this.$refs.input
            if (!el) {
                return
            }
            el.style.height = 'auto'
            el.style.height = Math.min(el.scrollHeight, 140) + 'px'
        },
        toggleVoice() {
            if (this.listening) {
                this.stopVoice()
                return
            }
            this.voiceError = ''
            let base = this.draft
            startSpeechToText({
                lang: this.config.speech_lang || undefined,
                onStart: () => { this.listening = true },
                onEnd: () => { this.listening = false },
                onInterim: (interim) => {
                    this.draft = interim ? appendTranscript(base, interim) : base
                    this.autoGrow()
                },
                onFinal: (finalText) => {
                    base = appendTranscript(base, finalText)
                    this.draft = base
                    this.autoGrow()
                },
                onError: (code) => {
                    this.listening = false
                    this.voiceError = code === 'denied'
                        ? this.getLabel('aiAssistantVoiceDenied')
                        : this.getLabel('aiAssistantVoiceError')
                },
            })
        },
        stopVoice() {
            if (this.listening) {
                stopActiveSpeechToText()
            }
            this.listening = false
        },
        /** Same rules as the wp-admin assistant: escaped text, links, **bold**, line breaks. */
        formatRichText(text) {
            text = String(text || '')
            let out = ''
            const re = /\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)|(https?:\/\/[^\s<]+)/g
            let last = 0
            let m
            while ((m = re.exec(text)) !== null) {
                out += escapeHtml(text.slice(last, m.index))
                if (m[1] && m[2]) {
                    out += renderLink(m[2], m[1])
                } else {
                    const raw = m[3] || ''
                    const clean = raw.replace(/[.,;:!?)]+$/, '')
                    out += renderLink(clean, clean) + escapeHtml(raw.slice(clean.length))
                }
                last = m.index + m[0].length
            }
            out += escapeHtml(text.slice(last))
            out = out.replace(/\*\*([^*\n]+?)\*\*/g, '<strong>$1</strong>')
            return out.replace(/\n/g, '<br>')
        },
        persist() {
            try {
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
                    sessionId: this.sessionId,
                    messages: this.messages.slice(-MAX_STORED_MESSAGES).map((m) => ({ role: m.role, text: m.text })),
                }))
            } catch (e) { /* storage unavailable */ }
        },
        restore() {
            try {
                const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || 'null')
                if (saved && saved.sessionId && Array.isArray(saved.messages)) {
                    this.sessionId = saved.sessionId
                    saved.messages.forEach((m) => this.push(m.role, m.text))
                }
            } catch (e) { /* storage unavailable */ }
        },
    },
}
</script>

<style scoped>
/* Mirrors css/ai-calendar-assistant.css (wp-admin calendar assistant). */
.ai-assistant {
    --ai-fab-size: 58px;
    --ai-fab-bottom: calc(84px + env(safe-area-inset-bottom, 0px));
    --ai-fab-right: 16px;
    --ai-blue: #4f5d95;
    --ai-blue-hover: #445184;
    --ai-blue-soft: #6573a8;
    --ai-bubble: #eef0f4;
    --ai-border: #e4e6ec;
    --ai-footer: #f5f6f8;
    --ai-text: #2a2f3a;
    --ai-muted: #8a91a3;
    position: fixed;
    right: var(--ai-fab-right);
    bottom: var(--ai-fab-bottom);
    z-index: 100100;
    font-family: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif;
    pointer-events: none;
}

.ai-assistant__fab,
.ai-assistant__panel {
    pointer-events: auto;
}

.ai-assistant__fab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: var(--ai-fab-size);
    height: var(--ai-fab-size);
    margin: 0;
    padding: 0;
    border: none;
    border-radius: 999px;
    background: var(--ai-blue);
    color: #fff;
    box-shadow: 0 10px 28px rgba(79, 93, 149, 0.38);
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    transition: background 0.15s ease, transform 0.15s ease;
}

.ai-assistant__fab:active {
    background: var(--ai-blue-hover);
    transform: scale(0.96);
}

.ai-assistant__fab:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px #fff, 0 0 0 5px var(--ai-blue);
}

.ai-assistant__fab-icon {
    display: inline-flex;
    line-height: 0;
}

.ai-assistant__fab-icon img,
.ai-assistant__header-icon img {
    display: block;
    object-fit: contain;
    filter: brightness(0) invert(1);
}

.ai-assistant__fab-icon img {
    width: 28px;
    height: 28px;
}

/* Panel */
.ai-assistant__panel {
    position: absolute;
    right: 0;
    bottom: calc(var(--ai-fab-size) + 14px);
    display: flex;
    flex-direction: column;
    width: min(380px, calc(100vw - 32px));
    height: min(640px, calc(100vh - 84px - var(--ai-fab-size) - 14px - 16px));
    height: min(640px, calc(100dvh - var(--ai-fab-bottom) - var(--ai-fab-size) - 14px - 16px));
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 18px 48px rgba(30, 40, 70, 0.22);
    overflow: hidden;
}

.ai-assistant__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 14px 14px 14px 16px;
    background: var(--ai-blue);
    flex: 0 0 auto;
}

.ai-assistant__header-main {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.ai-assistant__header-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: var(--ai-blue-soft);
    flex-shrink: 0;
}

.ai-assistant__header-icon img {
    width: 20px;
    height: 20px;
}

.ai-assistant__title {
    font-size: 16px;
    font-weight: 700;
    color: #fff;
    line-height: 1.3;
}

.ai-assistant__close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    margin: 0;
    padding: 0;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: #fff;
    font-size: 24px;
    line-height: 1;
    opacity: 0.92;
}

.ai-assistant__close:active {
    background: rgba(255, 255, 255, 0.14);
    opacity: 1;
}

.ai-assistant__body {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
    background: #fff;
}

.ai-assistant__scroll {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
}

.ai-assistant__messages {
    display: flex;
    flex-direction: column;
    padding: 16px 14px 8px;
}

.ai-assistant__msg {
    max-width: 88%;
    margin: 0 0 10px;
    padding: 10px 12px;
    font-size: 14px;
    line-height: 1.45;
}

.ai-assistant__msg-body {
    overflow-wrap: anywhere;
}

.ai-assistant__msg-body :deep(a),
.ai-assistant__preview-body :deep(a) {
    color: var(--ai-blue);
    text-decoration: underline;
    word-break: break-all;
}

.ai-assistant__msg--assistant {
    margin-right: auto;
    background: var(--ai-bubble);
    color: var(--ai-text);
    border-radius: 12px 12px 12px 4px;
}

.ai-assistant__msg--user {
    margin-left: auto;
    background: var(--ai-blue);
    color: #fff;
    border-radius: 12px 12px 4px 12px;
}

.ai-assistant__msg--user .ai-assistant__msg-body :deep(a) {
    color: #fff;
}

.ai-assistant__msg--system {
    max-width: 100%;
    margin-left: auto;
    margin-right: auto;
    padding: 6px 10px;
    background: transparent;
    color: var(--ai-muted);
    font-size: 12px;
    font-style: italic;
    text-align: center;
}

.ai-assistant__msg--error {
    max-width: 100%;
    margin-left: auto;
    margin-right: auto;
    background: #fcf0f1;
    border: 1px solid #d63638;
    color: var(--ai-text);
    border-radius: 12px;
}

.ai-assistant__msg-actions {
    margin-top: 8px;
}

.ai-assistant__chip {
    display: inline-block;
    max-width: 100%;
    padding: 8px 14px;
    border: 1px solid #d9dce4;
    border-radius: 999px;
    background: #fff;
    color: var(--ai-blue);
    font-size: 13px;
    font-weight: 500;
    line-height: 1.35;
    text-align: left;
    text-decoration: none;
}

/* Thinking: launcher icon pulse + label */
.ai-assistant__msg--thinking {
    padding: 0;
    background: transparent;
    animation: ai-assistant-thinking-enter 0.35s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.ai-assistant__thinking {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.ai-assistant__thinking-icon {
    display: block;
    width: 22px;
    height: 22px;
    padding: 5px;
    box-sizing: content-box;
    object-fit: contain;
    border-radius: 9px;
    background: var(--ai-bubble);
    border: 1px solid #dfe2ea;
    animation: ai-assistant-thinking-pulse 1.35s ease-in-out infinite;
}

.ai-assistant__thinking-label {
    font-size: 12.5px;
    font-weight: 500;
    color: #5c6478;
    white-space: nowrap;
}

@keyframes ai-assistant-thinking-enter {
    from { opacity: 0; transform: translateY(4px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes ai-assistant-thinking-pulse {
    0%, 100% { opacity: 0.42; transform: scale(0.92); }
    50% { opacity: 1; transform: scale(1.06); }
}

/* Preview */
.ai-assistant__preview {
    margin: 0 12px 8px;
    padding: 12px;
    border: 1px solid #b9bfd5;
    border-radius: 12px;
    background: #f3f4f8;
}

.ai-assistant__preview-title {
    margin: 0 0 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--ai-text);
}

.ai-assistant__preview-body {
    margin-bottom: 12px;
    font-size: 13px;
    line-height: 1.55;
    color: var(--ai-text);
    overflow-wrap: anywhere;
}

.ai-assistant__preview-actions {
    position: sticky;
    bottom: 0;
    z-index: 1;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0 -12px -12px;
    padding: 10px 12px 12px;
    background: #f3f4f8;
}

.ai-assistant__button {
    min-height: 42px;
    padding: 10px 16px;
    border: 1px solid #d9dce4;
    border-radius: 10px;
    background: #fff;
    color: #3c4252;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.3;
}

.ai-assistant__button--primary {
    border-color: var(--ai-blue);
    background: var(--ai-blue);
    color: #fff;
    font-weight: 700;
}

.ai-assistant__button--primary:active {
    border-color: var(--ai-blue-hover);
    background: var(--ai-blue-hover);
}

.ai-assistant__button:disabled,
.ai-assistant__chip:disabled,
.ai-assistant__mic:disabled {
    opacity: 0.65;
}

.ai-assistant__notice {
    flex: 0 0 auto;
    padding: 8px 12px;
    font-size: 12.5px;
    color: #8a5a00;
    background: #fcf9e8;
    border-top: 1px solid #f0e2a8;
}

/* Composer footer */
.ai-assistant__composer {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 8px;
    margin: 0;
    padding: 12px 12px 10px;
    border-top: 1px solid var(--ai-border);
    background: var(--ai-footer);
}

.ai-assistant__composer textarea {
    flex: 1;
    min-width: 0;
    min-height: 42px;
    max-height: 96px;
    resize: none;
    margin: 0;
    padding: 9px 12px;
    overflow-y: auto;
    /* 16px keeps iOS Safari from zooming on focus. */
    font-family: inherit;
    font-size: 16px;
    line-height: 1.35;
    color: var(--ai-text);
    background: #fff;
    border: 1px solid var(--ai-border);
    border-radius: 10px;
    outline: none;
}

.ai-assistant__composer textarea::placeholder {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--ai-muted);
}

.ai-assistant__composer textarea:focus,
.ai-assistant__composer.is-listening textarea {
    border-color: var(--ai-blue);
    box-shadow: 0 0 0 2px rgba(79, 93, 149, 0.18);
}

.ai-assistant__mic {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 42px;
    width: 42px;
    height: 42px;
    padding: 0;
    border: 1px solid var(--ai-border);
    border-radius: 10px;
    background: #fff;
    color: var(--ai-blue);
    line-height: 1;
}

.ai-assistant__mic[aria-pressed="true"] {
    background: var(--ai-blue);
    border-color: var(--ai-blue);
    color: #fff;
    animation: ai-assistant-mic-pulse 1.4s ease-in-out infinite;
}

@keyframes ai-assistant-mic-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(79, 93, 149, 0.35); }
    50% { box-shadow: 0 0 0 6px rgba(79, 93, 149, 0); }
}

.ai-assistant__send {
    flex: 0 0 auto;
    min-width: 72px;
}

.ai-assistant__footer-actions {
    display: flex;
    flex: 0 0 auto;
    justify-content: flex-end;
    padding: 0 12px 12px;
    background: var(--ai-footer);
}

.ai-assistant__full-link {
    font-size: 11.5px;
    font-weight: 500;
    color: var(--ai-blue);
    text-decoration: none;
}

.ai-assistant.is-busy .ai-assistant__send,
.ai-assistant.is-busy .ai-assistant__mic {
    opacity: 0.65;
    pointer-events: none;
}

@media (prefers-reduced-motion: reduce) {
    .ai-assistant__msg--thinking,
    .ai-assistant__thinking-icon,
    .ai-assistant__mic[aria-pressed="true"] {
        animation: none;
    }
}
</style>
