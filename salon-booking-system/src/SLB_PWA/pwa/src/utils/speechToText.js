/**
 * Speech-to-text engine for SOAP note dictation.
 *
 * Uses the browser Speech Recognition API (Chrome/Edge/Android via webkit,
 * Safari 14.5+). Chrome still ends "continuous" sessions after silence or
 * ~60s; this wrapper restarts until the staff member taps stop.
 */

function getCtor() {
    if (typeof window === 'undefined') {
        return null
    }
    return window.SpeechRecognition || window.webkitSpeechRecognition || null
}

export function isSpeechToTextSupported() {
    return !!getCtor()
}

export function speechToTextLang() {
    const raw = (typeof window !== 'undefined' && window.slnPWA && window.slnPWA.locale)
        ? String(window.slnPWA.locale)
        : ''
    const fromPwa = raw.replace('_', '-')
    const nav = (typeof navigator !== 'undefined' && navigator.language) ? navigator.language : 'en-US'
    if (fromPwa.length === 2 && nav.toLowerCase().startsWith(fromPwa.toLowerCase() + '-')) {
        return nav
    }
    return fromPwa || nav || 'en-US'
}

export function appendTranscript(existing, incoming) {
    let next = String(incoming || '').replace(/\s+/g, ' ').trim()
    if (!next) {
        return existing == null ? '' : String(existing)
    }
    const prev = String(existing || '').replace(/\s+$/, '')
    if (!prev) {
        return next.charAt(0).toUpperCase() + next.slice(1)
    }
    if (/[.!?]$/.test(prev)) {
        next = next.charAt(0).toUpperCase() + next.slice(1)
    }
    return prev + ' ' + next
}

let activeStop = null

export function stopActiveSpeechToText() {
    if (typeof activeStop === 'function') {
        activeStop()
    }
}

/**
 * @param {object} opts
 * @param {(text: string) => void} opts.onFinal
 * @param {(text: string) => void} [opts.onInterim]
 * @param {(message: string) => void} [opts.onError]
 * @param {() => void} [opts.onStart]
 * @param {() => void} [opts.onEnd]
 * @param {string} [opts.lang]
 * @returns {() => void} stop
 */
export function startSpeechToText(opts) {
    const Ctor = getCtor()
    if (!Ctor) {
        if (opts.onError) {
            opts.onError('unsupported')
        }
        return function () {}
    }

    stopActiveSpeechToText()

    const recognition = new Ctor()
    recognition.continuous = true
    recognition.interimResults = true
    recognition.maxAlternatives = 1
    recognition.lang = opts.lang || speechToTextLang()

    let wanted = true
    let restarting = false

    const stop = function () {
        wanted = false
        if (activeStop === stop) {
            activeStop = null
        }
        try {
            recognition.stop()
        } catch (e) {
            /* already stopped */
        }
        if (opts.onEnd) {
            opts.onEnd()
        }
    }

    activeStop = stop

    recognition.onstart = function () {
        restarting = false
        if (opts.onStart) {
            opts.onStart()
        }
    }

    recognition.onresult = function (event) {
        let interim = ''
        let finals = ''
        for (let i = event.resultIndex; i < event.results.length; i++) {
            const piece = event.results[i]
            const text = piece[0] && piece[0].transcript ? piece[0].transcript : ''
            if (piece.isFinal) {
                finals += text
            } else {
                interim += text
            }
        }
        if (finals && opts.onFinal) {
            opts.onFinal(finals)
        }
        if (opts.onInterim) {
            opts.onInterim(interim)
        }
    }

    recognition.onerror = function (event) {
        const code = event && event.error ? String(event.error) : 'unknown'
        if (!wanted) {
            return
        }
        if (code === 'no-speech' || code === 'aborted') {
            return
        }
        if (code === 'not-allowed' || code === 'service-not-allowed') {
            wanted = false
            if (opts.onError) {
                opts.onError('denied')
            }
            return
        }
        if (code === 'network') {
            wanted = false
            if (opts.onError) {
                opts.onError('network')
            }
        }
    }

    recognition.onend = function () {
        if (!wanted) {
            return
        }
        if (restarting) {
            return
        }
        restarting = true
        window.setTimeout(function () {
            if (!wanted) {
                return
            }
            try {
                recognition.start()
            } catch (e) {
                restarting = false
            }
        }, 180)
    }

    try {
        recognition.start()
    } catch (e) {
        wanted = false
        activeStop = null
        if (opts.onError) {
            opts.onError('start-failed')
        }
    }

    return stop
}
