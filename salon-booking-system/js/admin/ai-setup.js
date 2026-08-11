(function ($) {
	'use strict';

	var cfg = window.slnAiSetup || {};
	var sessionId = null;
	var pendingPreviewId = null;
	var canUndo = false;
	var sessionStarted = false;
	var currentUsage = cfg.usage || null;
	var $root = $();
	var speechRecognition = null;
	var voiceWantListening = false;
	var voiceBaseText = '';
	var voiceCommitted = '';
	var voiceRestartTimer = null;
	var voiceLastFinal = '';
	var voiceLastFinalAt = 0;
	var VOICE_LANG_KEY = 'slnAiSpeechLang';
	var AUTO_OPEN_KEY = 'slnAiCalendarAutoOpened';
	var SPEECH_LANG_MAP = {
		it: 'it-IT',
		es: 'es-ES',
		fr: 'fr-FR',
		de: 'de-DE',
		pt: 'pt-PT',
		en: 'en-US'
	};

	function isWidget() {
		return cfg.mode === 'widget' || $root.is('#sln-ai-calendar');
	}

	function $el(sel) {
		return $root.find(sel);
	}

	function rest(path, method, body) {
		return $.ajax({
			url: (cfg.restUrl || '').replace(/\/$/, '') + path,
			method: method || 'GET',
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', cfg.nonce);
			},
			contentType: 'application/json',
			dataType: 'json',
			data: body ? JSON.stringify(body) : undefined
		});
	}

	function setBusy(busy) {
		$root.toggleClass('is-busy', !!busy);
		var blocked = currentUsage && currentUsage.can_chat === false;
		$el('#sln-ai-setup-send').prop('disabled', !!busy || !!blocked);
		$el('#sln-ai-setup-mic').prop('disabled', !!busy || !!blocked);
		$el('#sln-ai-setup-input').prop('disabled', !!blocked);
		if (busy) {
			stopVoiceInput();
		}
	}

	function i18n(key, fallback) {
		return (cfg.i18n && cfg.i18n[key]) || fallback || '';
	}

	function isFreeEdition() {
		var ed = (currentUsage && currentUsage.edition) || cfg.edition;
		return ed !== 'pro';
	}

	function updateUsage(usage) {
		if (!usage || typeof usage !== 'object') {
			return;
		}
		currentUsage = usage;
		// Calendar widget: track quota silently — no credits meter/paywall chrome.
		if (isWidget()) {
			if (!usage.can_chat) {
				$el('#sln-ai-setup-send').prop('disabled', true);
				$el('#sln-ai-setup-mic').prop('disabled', true);
				$el('#sln-ai-setup-input').prop('disabled', true);
			} else {
				$el('#sln-ai-setup-send').prop('disabled', false);
				$el('#sln-ai-setup-mic').prop('disabled', false);
				$el('#sln-ai-setup-input').prop('disabled', false);
			}
			return;
		}
		var $box = $el('#sln-ai-setup-usage');
		if (!$box.length) {
			return;
		}
		var limit = Math.max(0, parseInt(usage.included_limit, 10) || 0);
		var remainingIncluded = Math.max(0, parseInt(usage.included_remaining, 10) || 0);
		var credits = Math.max(0, parseInt(usage.credits, 10) || 0);
		var total = Math.max(0, parseInt(usage.total_remaining, 10) || 0);
		if (!total && (remainingIncluded || credits)) {
			total = remainingIncluded + credits;
		}
		var pct = limit > 0 ? Math.min(100, Math.round((remainingIncluded / limit) * 100)) : 0;
		if (credits > 0 && remainingIncluded <= 0) {
			pct = 100;
		}

		$box.removeAttr('hidden');
		$box.toggleClass('is-exhausted', !usage.can_chat);
		$box.find('.sln-ai-setup__usage-label').text(i18n('usageLabel', 'Queries left this month'));
		$box.find('.sln-ai-setup__usage-count').text(String(total));
		$box.find('.sln-ai-setup__usage-bar-fill').css('width', pct + '%');

		var metaParts = [];
		if ((cfg.edition || usage.edition) === 'pro') {
			metaParts.push(i18n('editionPro', 'PRO plan: 100 included queries / month'));
		} else {
			metaParts.push(i18n('editionFree', 'Free plan: 10 included queries / month'));
		}
		if (credits > 0) {
			metaParts.push(i18n('usageCredits', 'Purchased credits') + ': ' + credits);
		}
		$box.find('.sln-ai-setup__usage-meta').text(metaParts.join(' · '));

		var $buy = $el('#sln-ai-setup-buy');
		if ($buy.length) {
			$buy.text(i18n('buyCredits', 'Buy credits'));
			// Purchase only when the included/purchased balance is exhausted.
			if (!usage.can_chat) {
				$buy.removeAttr('hidden');
			} else {
				$buy.attr('hidden', true);
			}
		}

		if (!usage.can_chat) {
			showPaywall(usage);
			setBusy(false);
		} else {
			hidePaywall();
			$el('#sln-ai-setup-send').prop('disabled', false);
			$el('#sln-ai-setup-mic').prop('disabled', false);
			$el('#sln-ai-setup-input').prop('disabled', false);
		}
	}

	function hidePaywall() {
		var $wall = $el('#sln-ai-setup-paywall');
		if ($wall.length) {
			$wall.attr('hidden', true);
		}
		$root.removeClass('is-paywalled');
	}

	function showPaywall(usage) {
		var freeEdition = isFreeEdition();
		if (isWidget()) {
			var pageUrl = cfg.pageUrl || '';
			var msg;
			if (freeEdition) {
				msg = i18n('upgradeProText', 'Upgrade to PRO to get more AI queries every month.');
				if (cfg.pricingUrl) {
					msg += ' ' + i18n('viewProPlans', 'View PRO plans') + ': ' + cfg.pricingUrl;
				}
			} else {
				msg = i18n('usageExhausted', 'No queries left. Buy credits to continue.');
				if (pageUrl) {
					msg += ' ' + i18n('openFullSetup', 'Open full AI Setup') + ': ' + pageUrl;
				}
			}
			appendMessage('error', msg);
			return;
		}
		var $wall = $el('#sln-ai-setup-paywall');
		if (!$wall.length) {
			return;
		}
		$root.addClass('is-paywalled');
		$wall.removeAttr('hidden');

		var $upgrade = $el('#sln-ai-setup-upgrade');
		if ($upgrade.length) {
			if (freeEdition && cfg.pricingUrl) {
				$upgrade.find('.sln-ai-setup__upgrade-title')
					.text(i18n('upgradeProTitle', 'Upgrade to PRO for more AI queries'));
				$upgrade.find('.sln-ai-setup__upgrade-text')
					.text(i18n('upgradeProText', 'Upgrade to PRO to get more AI queries every month.'));
				$upgrade.find('.sln-ai-setup__upgrade-cta')
					.attr('href', cfg.pricingUrl)
					.text(i18n('viewProPlans', 'View PRO plans'));
				$upgrade.removeAttr('hidden');
			} else {
				$upgrade.attr('hidden', true);
			}
		}

		$wall.find('.sln-ai-setup__paywall-title').text(i18n('packsTitle', 'Choose a credit pack'));
		$wall.find('.sln-ai-setup__paywall-text').text(
			i18n('usageExhausted', 'No queries left. Buy credits to continue.')
		);

		var $hint = $wall.find('.sln-ai-setup__paywall-hint');
		$hint.text(i18n('packsHint', 'Credits never expire. Secure checkout — no setup required.'));

		var packs = (usage && usage.packs) || cfg.packs || [];
		var $packs = $el('#sln-ai-setup-packs');
		$packs.empty();
		$.each(packs, function (_, pack) {
			if (!pack || !pack.id) {
				return;
			}
			var $card = $('<button type="button"/>')
				.addClass('sln-ai-setup__pack')
				.attr('data-pack-id', pack.id)
				.append($('<span/>').addClass('sln-ai-setup__pack-label').text(pack.label || pack.id))
				.append($('<span/>').addClass('sln-ai-setup__pack-price').text(pack.price_label || ''))
				.append(
					$('<span/>')
						.addClass('sln-ai-setup__pack-cta')
						.text(i18n('buyPack', 'Buy'))
				)
				.on('click', function () {
					buyPack(pack.id);
				});
			$packs.append($card);
		});
	}

	function buyPack(packId) {
		if (!packId) {
			return;
		}
		setBusy(true);
		appendMessage('system', i18n('checkoutStarting', 'Opening checkout…'));
		rest('/buy-credits', 'POST', {
			pack_id: packId,
			return_url: cfg.returnUrl || cfg.pageUrl || window.location.href
		})
			.done(function (data) {
				$el('#sln-ai-setup-messages .sln-ai-setup__msg--system').last().remove();
				if (data && data.checkout_url) {
					window.location.href = data.checkout_url;
					return;
				}
				appendMessage('error', i18n('purchaseUnavailable', 'Credit purchase unavailable.'));
			})
			.fail(function (xhr) {
				$el('#sln-ai-setup-messages .sln-ai-setup__msg--system').last().remove();
				var msg = i18n('purchaseUnavailable', 'Credit purchase unavailable.');
				if (xhr.responseJSON && xhr.responseJSON.message) {
					msg = xhr.responseJSON.message;
				}
				appendMessage('error', msg);
				showPaywall(currentUsage || {});
			})
			.always(function () {
				setBusy(false);
			});
	}

	function handleQuotaError(xhr) {
		var data = (xhr && xhr.responseJSON && xhr.responseJSON.data) || {};
		var usage = data.usage || null;
		if (usage) {
			if (data.packs && data.packs.length) {
				usage.packs = data.packs;
			}
			updateUsage(usage);
		} else {
			showPaywall(currentUsage || { purchase_available: true, packs: cfg.packs || [] });
		}
		var msg =
			(xhr.responseJSON && xhr.responseJSON.message) ||
			i18n('usageExhausted', 'No queries left. Buy credits to continue.');
		appendMessage('error', msg);
	}

	function normalizeSpeechLang(tag) {
		var raw = String(tag || '').trim();
		if (!raw) {
			return '';
		}
		var parts = raw.replace('_', '-').split('-');
		var lang = (parts[0] || '').toLowerCase();
		var region = parts[1] ? parts[1].toUpperCase() : '';
		var candidate = region ? lang + '-' + region : lang;
		var allowed = ['it-IT', 'en-US', 'en-GB', 'es-ES', 'fr-FR', 'de-DE', 'pt-PT'];
		var i;
		for (i = 0; i < allowed.length; i++) {
			if (allowed[i].toLowerCase() === candidate.toLowerCase()) {
				return allowed[i];
			}
		}
		if (SPEECH_LANG_MAP[lang]) {
			return SPEECH_LANG_MAP[lang];
		}
		return '';
	}

	function browserSpeechLang() {
		var list = navigator.languages && navigator.languages.length
			? navigator.languages
			: [navigator.language || navigator.userLanguage || ''];
		var i;
		var matched;
		for (i = 0; i < list.length; i++) {
			matched = normalizeSpeechLang(list[i]);
			if (matched) {
				return matched;
			}
		}
		return '';
	}

	function detectSpeechLangFromText(text) {
		var lower = ' ' + String(text || '').toLowerCase() + ' ';
		if (!$.trim(text || '')) {
			return '';
		}
		var scores = { it: 0, es: 0, fr: 0, de: 0, pt: 0, en: 0 };
		var markers = {
			it: [' il ', ' la ', ' lo ', ' gli ', ' delle ', ' della ', ' sono ', ' aperto ', ' prenotazioni ', ' prenotazione ', ' orari ', ' chiuso ', ' trovami ', ' trova ', ' vorrei ', ' grazie ', ' dalle ', ' alle ', ' agosto ', ' luglio ', ' lunedi ', ' martedi ', ' mercoledi ', ' giovedi ', ' venerdi ', ' sabato ', ' domenica '],
			es: [' el ', ' los ', ' las ', ' estoy ', ' reserva ', ' horario ', ' gracias ', ' quiero '],
			fr: [' le ', ' les ', ' des ', ' je ', ' ouvert ', ' reserve ', ' merci ', ' voudrais '],
			de: [' der ', ' die ', ' das ', ' und ', ' ich ', ' bitte ', ' danke ', ' termin '],
			pt: [' os ', ' as ', ' estou ', ' reserva ', ' horario ', ' obrigado ', ' quero '],
			en: [' the ', ' and ', ' for ', ' with ', ' booking ', ' appointments ', ' please ', ' find ', ' August ']
		};
		var lang;
		var i;
		for (lang in markers) {
			if (!Object.prototype.hasOwnProperty.call(markers, lang)) {
				continue;
			}
			for (i = 0; i < markers[lang].length; i++) {
				if (lower.indexOf(markers[lang][i].toLowerCase()) !== -1) {
					scores[lang] += 1;
				}
			}
		}
		var best = '';
		var bestScore = 0;
		for (lang in scores) {
			if (scores[lang] > bestScore) {
				bestScore = scores[lang];
				best = lang;
			}
		}
		if (bestScore < 1 || !SPEECH_LANG_MAP[best]) {
			return '';
		}
		return SPEECH_LANG_MAP[best];
	}

	function conversationSpeechLangHint() {
		var chunks = [];
		var inputVal = $.trim($el('#sln-ai-setup-input').val() || '');
		if (inputVal) {
			chunks.push(inputVal);
		}
		$el('#sln-ai-setup-messages .sln-ai-setup__msg--user .sln-ai-setup__msg-body')
			.slice(-3)
			.each(function () {
				chunks.push($(this).text() || '');
			});
		return detectSpeechLangFromText(chunks.join(' '));
	}

	function rememberSpeechLang(tag) {
		var lang = normalizeSpeechLang(tag);
		if (!lang) {
			return;
		}
		try {
			window.localStorage.setItem(VOICE_LANG_KEY, lang);
		} catch (e) {
			/* ignore */
		}
	}

	function resolveSpeechLang() {
		var stored = '';
		try {
			stored = window.localStorage.getItem(VOICE_LANG_KEY) || '';
		} catch (e) {
			stored = '';
		}
		return (
			conversationSpeechLangHint() ||
			normalizeSpeechLang(stored) ||
			browserSpeechLang() ||
			normalizeSpeechLang(cfg.speechLang) ||
			'en-US'
		);
	}

	function joinVoiceParts(base, committed, interim) {
		var parts = [];
		if (base) {
			parts.push(base);
		}
		if (committed) {
			parts.push(committed);
		}
		if (interim) {
			parts.push(interim);
		}
		return parts.join(' ').replace(/\s+/g, ' ').replace(/^\s+/, '');
	}

	function appendFinalTranscript(piece) {
		piece = $.trim(piece || '');
		if (!piece) {
			return;
		}
		var now = Date.now();
		// Chrome sometimes re-emits the same final when a session restarts.
		if (piece === voiceLastFinal && now - voiceLastFinalAt < 800) {
			return;
		}
		voiceLastFinal = piece;
		voiceLastFinalAt = now;
		voiceCommitted = $.trim((voiceCommitted + ' ' + piece).replace(/\s+/g, ' '));
	}

	function setVoiceUi(listening) {
		var $mic = $el('#sln-ai-setup-mic');
		var $form = $el('#sln-ai-setup-form');
		$mic.attr('aria-pressed', listening ? 'true' : 'false');
		$mic.attr(
			'aria-label',
			listening
				? ((cfg.i18n && cfg.i18n.voiceStop) || 'Stop voice input')
				: ((cfg.i18n && cfg.i18n.voiceStart) || 'Start voice input')
		);
		$form.toggleClass('is-listening', !!listening);
		$root.toggleClass('is-listening', !!listening);
	}

	function clearVoiceRestart() {
		if (voiceRestartTimer) {
			window.clearTimeout(voiceRestartTimer);
			voiceRestartTimer = null;
		}
	}

	function stopVoiceInput() {
		voiceWantListening = false;
		clearVoiceRestart();
		if (voiceCommitted) {
			rememberSpeechLang(detectSpeechLangFromText(voiceCommitted));
		}
		if (speechRecognition) {
			try {
				speechRecognition.stop();
			} catch (e) {
				/* ignore */
			}
		}
		setVoiceUi(false);
	}

	function startRecognitionEngine() {
		if (!speechRecognition || !voiceWantListening || $root.hasClass('is-busy')) {
			return;
		}
		speechRecognition.lang = resolveSpeechLang();
		try {
			speechRecognition.start();
		} catch (e) {
			// InvalidStateError: already started — ignore and wait for onend.
		}
	}

	function startVoiceInput() {
		if (!speechRecognition || $root.hasClass('is-busy')) {
			return;
		}
		clearVoiceRestart();
		voiceBaseText = $.trim($el('#sln-ai-setup-input').val() || '');
		voiceCommitted = '';
		voiceLastFinal = '';
		voiceLastFinalAt = 0;
		voiceWantListening = true;
		setVoiceUi(true);
		startRecognitionEngine();
	}

	function initVoiceInput() {
		var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
		var $mic = $el('#sln-ai-setup-mic');
		if (!$mic.length) {
			return;
		}
		if (!SpeechRecognition) {
			$mic.attr('hidden', true);
			return;
		}

		speechRecognition = new SpeechRecognition();
		speechRecognition.continuous = true;
		speechRecognition.interimResults = true;
		speechRecognition.maxAlternatives = 1;
		speechRecognition.lang = resolveSpeechLang();

		speechRecognition.onresult = function (event) {
			var interim = '';
			var i;
			for (i = event.resultIndex; i < event.results.length; i++) {
				var piece = event.results[i][0].transcript || '';
				if (event.results[i].isFinal) {
					appendFinalTranscript(piece);
				} else {
					interim += piece;
				}
			}
			$el('#sln-ai-setup-input').val(joinVoiceParts(voiceBaseText, voiceCommitted, interim));
		};

		speechRecognition.onerror = function (event) {
			var err = event && event.error ? event.error : '';
			// Recoverable: keep listening; onend will restart after debounce.
			if (err === 'aborted' || err === 'no-speech') {
				return;
			}
			voiceWantListening = false;
			clearVoiceRestart();
			setVoiceUi(false);
			if (err === 'not-allowed' || err === 'service-not-allowed') {
				appendMessage(
					'error',
					(cfg.i18n && cfg.i18n.voiceDenied) ||
						'Microphone access was denied.'
				);
				return;
			}
			appendMessage(
				'error',
				(cfg.i18n && cfg.i18n.voiceError) ||
					'Voice input failed.'
			);
		};

		speechRecognition.onend = function () {
			if (!voiceWantListening) {
				setVoiceUi(false);
				return;
			}
			// Debounce restart: Chrome ends sessions after short pauses.
			clearVoiceRestart();
			voiceRestartTimer = window.setTimeout(function () {
				voiceRestartTimer = null;
				if (!voiceWantListening) {
					setVoiceUi(false);
					return;
				}
				startRecognitionEngine();
			}, 220);
		};

		$mic.removeAttr('hidden');
		$mic.attr(
			'aria-label',
			(cfg.i18n && cfg.i18n.voiceStart) || 'Start voice input'
		);
		$mic.on('click', function () {
			if (voiceWantListening) {
				stopVoiceInput();
			} else {
				startVoiceInput();
			}
		});

		$(document).on('keydown.slnAiVoice', function (e) {
			if (e.key === 'Escape' && voiceWantListening) {
				stopVoiceInput();
			}
		});
	}

	function escapeHtml(text) {
		return $('<div/>').text(text || '').html();
	}

	function normalizeUrl(url) {
		return String(url || '')
			.replace(/&amp;/gi, '&')
			.replace(/&#0*38;/gi, '&')
			.replace(/&#x0*26;/gi, '&');
	}

	function isAdminUrl(url) {
		try {
			var u = new URL(url, window.location.href);
			return u.origin === window.location.origin && u.pathname.indexOf('/wp-admin') !== -1;
		} catch (e) {
			return false;
		}
	}

	function renderLink(url, label) {
		var href = normalizeUrl(url).replace(/[.,;:!?)]+$/, '');
		var text = label != null && String(label) !== '' ? String(label) : href;
		var attrs = ' class="sln-ai-setup__msg-link" href="' + escapeHtml(href) + '"';
		if (isAdminUrl(href)) {
			attrs += ' target="_self"';
		} else {
			attrs += ' target="_blank" rel="noopener noreferrer"';
		}
		return '<a' + attrs + '>' + escapeHtml(text) + '</a>';
	}

	function formatRichText(text) {
		// Markdown [label](url) and bare URLs on raw text (keeps "&" in query strings).
		text = String(text || '');
		var out = '';
		var re = /\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)|(https?:\/\/[^\s<]+)/g;
		var last = 0;
		var m;
		while ((m = re.exec(text)) !== null) {
			out += escapeHtml(text.slice(last, m.index));
			if (m[1] && m[2]) {
				out += renderLink(m[2], m[1]);
			} else {
				var rawUrl = m[3] || '';
				var norm = normalizeUrl(rawUrl);
				var clean = norm.replace(/[.,;:!?)]+$/, '');
				var trail = norm.slice(clean.length);
				out += renderLink(clean, clean);
				if (trail) {
					out += escapeHtml(trail);
				}
			}
			last = m.index + m[0].length;
		}
		out += escapeHtml(text.slice(last));
		return out.replace(/\n/g, '<br>');
	}

	function appendMessage(role, text) {
		var $box = $el('#sln-ai-setup-messages');
		var roleLabel = role === 'user'
			? (cfg.i18n && cfg.i18n.you) || 'You'
			: (cfg.i18n && cfg.i18n.assistant) || 'AI Setup';
		if (role === 'system' || role === 'error' || role === 'thinking') {
			roleLabel = '';
		}
		var $msg = $('<div/>')
			.addClass('sln-ai-setup__msg sln-ai-setup__msg--' + role);
		if (roleLabel) {
			$msg.append($('<span/>').addClass('sln-ai-setup__msg-role').text(roleLabel));
		}
		$msg.append(
			$('<div/>')
				.addClass('sln-ai-setup__msg-body')
				.html(formatRichText(text || ''))
		);
		$box.append($msg);
		if ($box.length && $box[0]) {
			$box.scrollTop($box[0].scrollHeight);
		}
	}

	function thinkingLabel() {
		var t = (cfg.i18n && cfg.i18n.thinking) || 'Thinking…';
		return String(t).replace(/(?:\.\.\.|…|\.|·|\s)+$/g, '') + '..';
	}

	function appendThinking() {
		removeThinking();
		var $box = $el('#sln-ai-setup-messages');
		var label = thinkingLabel();
		var ariaLabel = (cfg.i18n && cfg.i18n.thinking) || 'Thinking…';
		var iconUrl = cfg.iconUrl || '';
		var $msg = $('<div/>')
			.addClass('sln-ai-setup__msg sln-ai-setup__msg--thinking')
			.attr({
				role: 'status',
				'aria-live': 'polite',
				'aria-label': ariaLabel
			});
		var $inner = $('<div/>').addClass('sln-ai-setup__thinking').attr('aria-hidden', 'true');
		if (iconUrl) {
			$inner.append(
				$('<img/>')
					.addClass('sln-ai-setup__thinking-icon')
					.attr({
						src: iconUrl,
						alt: '',
						width: 24,
						height: 24,
						draggable: 'false'
					})
			);
		}
		$inner.append($('<span/>').addClass('sln-ai-setup__thinking-label').text(label));
		$msg.append($inner);
		$box.append($msg);
		if ($box.length && $box[0]) {
			$box.scrollTop($box[0].scrollHeight);
		}
	}

	function removeThinking() {
		$el('#sln-ai-setup-messages .sln-ai-setup__msg--thinking').remove();
	}

	function hidePreview() {
		pendingPreviewId = null;
		$el('#sln-ai-setup-preview').attr('hidden', true);
		$el('#sln-ai-setup-preview .sln-ai-setup__preview-body').empty();
	}

	function showPreview(preview) {
		if (!preview || !preview.id) {
			hidePreview();
			return;
		}
		pendingPreviewId = preview.id;
		var $p = $el('#sln-ai-setup-preview');
		$p.find('.sln-ai-setup__preview-title').text(
			(cfg.i18n && cfg.i18n.previewTitle) || 'Proposed change'
		);
		$p.find('.sln-ai-setup__preview-body').html(formatRichText(preview.summary || ''));
		$p.find('#sln-ai-setup-confirm').text((cfg.i18n && cfg.i18n.confirm) || 'Confirm');
		$p.find('#sln-ai-setup-cancel').text((cfg.i18n && cfg.i18n.cancel) || 'Cancel');
		$p.removeAttr('hidden');
	}

	function updateUndo(enabled) {
		canUndo = !!enabled;
		var $btn = $el('#sln-ai-setup-undo');
		if (!$btn.length) {
			return;
		}
		// Calendar widget has no Undo control — full AI Setup page only.
		if (isWidget()) {
			return;
		}
		$btn.prop('disabled', !canUndo);
	}

	function updateBackend(backend) {
		var $box = $el('#sln-ai-setup-backend');
		if (!$box.length) {
			return;
		}
		// Merchants should not see LLM key / offline setup instructions.
		if (!cfg.showBackendDebug) {
			$box.attr('hidden', true);
			return;
		}
		backend = backend || 'mock';
		var label = (cfg.i18n && cfg.i18n.backendMock) || 'Local assistant';
		var hint = (cfg.i18n && cfg.i18n.hintMock) || '';
		if (backend === 'llm') {
			label = (cfg.i18n && cfg.i18n.backendLlm) || 'AI model';
			hint = (cfg.i18n && cfg.i18n.hintLlm) || '';
		} else if (backend === 'proxy') {
			label = (cfg.i18n && cfg.i18n.backendProxy) || 'Salon AI cloud';
			hint = (cfg.i18n && cfg.i18n.hintProxy) || '';
		}
		$box
			.removeClass('sln-ai-setup__backend--llm sln-ai-setup__backend--proxy sln-ai-setup__backend--mock')
			.addClass('sln-ai-setup__backend--' + backend)
			.removeAttr('hidden');
		$box.find('.sln-ai-setup__backend-label').text(label);
		$box.find('.sln-ai-setup__backend-hint').text(hint);
	}

	function setWelcome(text) {
		var $welcome = $el('#sln-ai-setup-welcome');
		// Widget mockup: show welcome as an assistant bubble, not a separate banner.
		if (isWidget()) {
			if ($welcome.length) {
				$welcome.attr('hidden', true).text('');
			}
			if (text && !$el('#sln-ai-setup-messages .sln-ai-setup__msg--assistant').length) {
				appendMessage('assistant', text);
			}
			return;
		}
		if (!$welcome.length) {
			return;
		}
		$welcome.text(text || '');
	}

	function handleChatResponse(data) {
		if (!data) {
			return;
		}
		if (data.session_id) {
			sessionId = data.session_id;
		}
		if (data.backend) {
			updateBackend(data.backend);
		}
		if (data.usage) {
			updateUsage(data.usage);
		}
		// Confirm card only when the server sent a valid preview id.
		if (data.preview && data.preview.id && data.preview.summary) {
			showPreview(data.preview);
		} else {
			hidePreview();
		}
		if (data.message) {
			appendMessage('assistant', data.message);
		}
		if (!isWidget() && typeof data.can_undo !== 'undefined') {
			updateUndo(data.can_undo);
		}
	}

	function sendMessage(text) {
		text = $.trim(text || '');
		if (!text) {
			return;
		}
		if (currentUsage && currentUsage.can_chat === false) {
			showPaywall(currentUsage);
			return;
		}
		rememberSpeechLang(detectSpeechLangFromText(text));
		stopVoiceInput();
		appendMessage('user', text);
		$el('#sln-ai-setup-input').val('');
		hidePreview();
		setBusy(true);
		appendThinking();

		rest('/chat', 'POST', {
			session_id: sessionId,
			message: text
		})
			.done(function (data) {
				removeThinking();
				handleChatResponse(data);
			})
			.fail(function (xhr) {
				removeThinking();
				var code = xhr.responseJSON && xhr.responseJSON.code;
				if (code === 'sln_ai_quota_exceeded' || xhr.status === 402) {
					handleQuotaError(xhr);
					return;
				}
				var msg = (cfg.i18n && cfg.i18n.error) || 'Error';
				if (xhr.responseJSON && xhr.responseJSON.message) {
					msg = xhr.responseJSON.message;
				}
				appendMessage('error', msg);
			})
			.always(function () {
				setBusy(false);
			});
	}

	function initSuggestions() {
		var $wrap = $el('#sln-ai-setup-suggestions');
		if (!$wrap.length) {
			return;
		}
		$wrap.empty();
		var items = cfg.suggestions || [];
		if (!items.length || isWidget()) {
			$wrap.attr('hidden', true);
			return;
		}
		$wrap.removeAttr('hidden');
		$.each(items, function (_, label) {
			var $chip = $('<button type="button"/>')
				.addClass('sln-ai-setup__chip')
				.text(label)
				.on('click', function () {
					sendMessage(label);
				});
			if ((label || '').length > 55) {
				$chip.addClass('sln-ai-setup__chip--wide');
			}
			$chip.appendTo($wrap);
		});
	}

	function startSession() {
		if (sessionStarted) {
			return;
		}
		sessionStarted = true;
		if (currentUsage) {
			updateUsage(currentUsage);
		}
		rest('/session', 'GET')
			.done(function (data) {
				if (data && data.session_id) {
					sessionId = data.session_id;
				}
				if (data && data.backend) {
					updateBackend(data.backend);
				}
				if (data && data.usage) {
					updateUsage(data.usage);
				}
				if (!isWidget() && data && typeof data.can_undo !== 'undefined') {
					updateUndo(data.can_undo);
				}
				if (isWidget() && cfg.welcome) {
					setWelcome(cfg.welcome);
				} else if (data && data.welcome) {
					setWelcome(data.welcome);
				}
			})
			.fail(function () {
				updateBackend('mock');
				if (currentUsage) {
					updateUsage(currentUsage);
				}
				setWelcome(
					cfg.welcome ||
						'Ask me to set your salon opening hours or holidays. I will show a preview before anything is saved.'
				);
			});
	}

	function setPanelOpen(open) {
		var $panel = $el('#sln-ai-calendar-panel');
		var $fab = $el('#sln-ai-calendar-fab');
		$root.toggleClass('is-open', !!open);
		if (open) {
			$panel.removeAttr('hidden');
			$fab.attr('aria-expanded', 'true');
			$fab.attr(
				'aria-label',
				(cfg.i18n && cfg.i18n.closeAssistant) || 'Close AI assistant'
			);
			startSession();
			window.setTimeout(function () {
				$el('#sln-ai-setup-input').trigger('focus');
			}, 50);
		} else {
			stopVoiceInput();
			$panel.attr('hidden', true);
			$fab.attr('aria-expanded', 'false');
			$fab.attr(
				'aria-label',
				(cfg.i18n && cfg.i18n.openAssistant) || 'Open AI assistant'
			);
		}
	}

	function bindChatHandlers() {
		$el('#sln-ai-setup-send').text((cfg.i18n && cfg.i18n.send) || 'Send');
		$el('#sln-ai-setup-undo').text((cfg.i18n && cfg.i18n.undo) || 'Undo');
		$el('#sln-ai-setup-input').attr(
			'placeholder',
			(cfg.i18n && cfg.i18n.placeholder) || ''
		);
		initSuggestions();
		initVoiceInput();

		$el('#sln-ai-setup-buy').on('click', function () {
			showPaywall(currentUsage || { purchase_available: true, packs: cfg.packs || [] });
		});

		$el('#sln-ai-setup-form').on('submit', function (e) {
			e.preventDefault();
			sendMessage($el('#sln-ai-setup-input').val());
		});

		$el('#sln-ai-setup-input').on('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				$el('#sln-ai-setup-form').trigger('submit');
			}
		});

		$el('#sln-ai-setup-confirm').on('click', function () {
			if (!pendingPreviewId) {
				return;
			}
			setBusy(true);
			rest('/confirm', 'POST', {
				session_id: sessionId,
				preview_id: pendingPreviewId
			})
				.done(function (data) {
					hidePreview();
					appendMessage(
						'assistant',
						(data && data.message) ||
							((cfg.i18n && cfg.i18n.applied) || 'Applied.')
					);
					updateUndo(data && data.can_undo);
				})
				.fail(function (xhr) {
					hidePreview();
					var msg = (cfg.i18n && cfg.i18n.error) || 'Error';
					if (xhr.responseJSON && xhr.responseJSON.message) {
						msg = xhr.responseJSON.message;
					}
					appendMessage('error', msg);
				})
				.always(function () {
					setBusy(false);
				});
		});

		$el('#sln-ai-setup-cancel').on('click', function () {
			if (!pendingPreviewId) {
				hidePreview();
				return;
			}
			setBusy(true);
			rest('/confirm', 'POST', {
				session_id: sessionId,
				preview_id: pendingPreviewId,
				cancel: true
			})
				.done(function (data) {
					hidePreview();
					appendMessage(
						'assistant',
						(data && data.message) ||
							((cfg.i18n && cfg.i18n.cancelled) || 'Cancelled.')
					);
				})
				.fail(function () {
					hidePreview();
					appendMessage(
						'assistant',
						(cfg.i18n && cfg.i18n.cancelled) || 'Cancelled.'
					);
				})
				.always(function () {
					setBusy(false);
				});
		});

		$el('#sln-ai-setup-undo').on('click', function () {
			if (!canUndo) {
				return;
			}
			setBusy(true);
			rest('/undo', 'POST', { session_id: sessionId })
				.done(function (data) {
					appendMessage(
						'assistant',
						(data && data.message) ||
							((cfg.i18n && cfg.i18n.undone) || 'Undone.')
					);
					updateUndo(data && data.can_undo);
				})
				.fail(function (xhr) {
					var msg = (cfg.i18n && cfg.i18n.error) || 'Error';
					if (xhr.responseJSON && xhr.responseJSON.message) {
						msg = xhr.responseJSON.message;
					}
					appendMessage('error', msg);
				})
				.always(function () {
					setBusy(false);
				});
		});
	}

	function hasAutoOpened() {
		try {
			return window.localStorage.getItem(AUTO_OPEN_KEY) === '1';
		} catch (e) {
			return false;
		}
	}

	function markAutoOpened() {
		try {
			window.localStorage.setItem(AUTO_OPEN_KEY, '1');
		} catch (e) {
			// Storage unavailable (private mode / quota): fall back to not auto-opening.
		}
	}

	function bindWidgetChrome() {
		$el('#sln-ai-calendar-fab').on('click', function () {
			setPanelOpen(!$root.hasClass('is-open'));
		});
		$el('#sln-ai-calendar-close').on('click', function () {
			setPanelOpen(false);
		});
		$(document).on('keydown.slnAiCalendar', function (e) {
			if (e.key === 'Escape' && $root.hasClass('is-open')) {
				setPanelOpen(false);
			}
		});
	}

	function resolveRoot() {
		if (cfg.mode === 'widget' && $('#sln-ai-calendar').length) {
			return $('#sln-ai-calendar');
		}
		if ($('#sln-salon--admin.sln-ai-setup').length) {
			return $('#sln-salon--admin.sln-ai-setup');
		}
		if ($('#sln-ai-calendar').length) {
			return $('#sln-ai-calendar');
		}
		return $();
	}

	function boot() {
		$root = resolveRoot();
		if (!$root.length) {
			return;
		}

		bindChatHandlers();

		if (isWidget()) {
			bindWidgetChrome();
			if (hasAutoOpened()) {
				setPanelOpen(false);
			} else {
				markAutoOpened();
				setPanelOpen(true);
			}
		} else {
			startSession();
		}
	}

	$(boot);
})(jQuery);
