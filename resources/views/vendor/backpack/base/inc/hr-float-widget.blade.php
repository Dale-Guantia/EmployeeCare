{{--
    Floating HR Policy Assistant Widget
    ====================================
    Path: resources/views/vendor/backpack/base/inc/hr-float-widget.blade.php

    Included from: resources/views/vendor/backpack/base/inc/footer.blade.php
    Appears on every authenticated Backpack page as a bottom-right chat bubble.
    Shares the same backend routes and the same shared chat engine
    (public/js/hr-chat-core.js) as the full HR Assistant page.
--}}
@if(backpack_auth()->check() && !request()->routeIs('hr.chat.index'))
{{-- Auto-hidden on the full HR Assistant page itself — that page already offers
     everything this widget does (history, sources, feedback), so mounting both
     would be a redundant second copy of the same conversation on screen. --}}

{{-- ── Floating widget styles ──────────────────────────────────────── --}}
<style>
/* ── Launcher bubble ───────────────────────────────────────────── */
#hrf-launcher {
    position: fixed;
    bottom: 20px;
    right: 30px;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: #375DA7; /* matches Backpack primary */
    border: none;
    box-shadow: 0 4px 14px rgba(0,0,0,0.25);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9998;
    transition: transform 0.2s, box-shadow 0.2s;
}
#hrf-launcher:hover { transform: scale(1.08); box-shadow: 0 6px 20px rgba(0,0,0,0.3); }
#hrf-launcher i { font-size: 22px; color: #fff; transition: transform 0.3s; }
#hrf-launcher.open i { transform: rotate(90deg); }

/* Unread badge */
#hrf-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #e53935;
    color: #fff;
    font-size: 10px;
    font-weight: 600;
    display: none;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
}

/* ── Widget panel ──────────────────────────────────────────────── */
#hrf-panel {
    position: fixed;
    bottom: 90px;
    right: 30px;
    width: 500px;
    height: 500px;
    max-width: calc(100vw - 20px);
    max-height: calc(100vh - 110px);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 9999;
    animation: hrf-pop 0.2s ease;
    font-family: inherit;
}
#hrf-panel.open { display: flex; }

@keyframes hrf-pop {
    from { opacity: 0; transform: translateY(16px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}

/* Narrow viewports: use nearly the full width/height instead of a fixed 500px panel */
@media (max-width: 560px) {
    #hrf-panel {
        right: 10px;
        left: 10px;
        bottom: 80px;
        width: auto;
    }
    #hrf-launcher { right: 16px; bottom: 16px; }
}

/* ── Header ────────────────────────────────────────────────────── */
#hrf-header {
    background: #375DA7;
    color: #fff;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
#hrf-header-icon {
    width: 34px;
    height: 34px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
#hrf-header-icon i { font-size: 17px; }
#hrf-header-title { flex: 1; }
#hrf-header-title p { margin: 0; }
#hrf-header-title .hrf-name  { font-size: 13px; font-weight: 600; line-height: 1.3; }
#hrf-header-title .hrf-sub   { font-size: 11px; opacity: 0.8; line-height: 1.3; }
#hrf-header-actions { display: flex; gap: 6px; }
#hrf-header-actions button {
    background: transparent;
    border: none;
    color: rgba(255,255,255,0.85);
    cursor: pointer;
    padding: 4px 6px;
    border-radius: 6px;
    font-size: 14px;
    transition: background 0.15s;
}
#hrf-header-actions button:hover { background: rgba(255,255,255,0.18); }

/* ── Message thread ─────────────────────────────────────────────── */
#hrf-thread {
    flex: 1;
    overflow-y: auto;
    padding: 14px 14px 6px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f4f6f9;
    scroll-behavior: smooth;
}
#hrf-thread::-webkit-scrollbar { width: 4px; }
#hrf-thread::-webkit-scrollbar-thumb { background: #d0d0d0; border-radius: 4px; }

/* ── Message bubbles ────────────────────────────────────────────── */
.hrf-msg { display: flex; flex-direction: column; max-width: 88%; }
.hrf-msg.align-items-end { align-self: flex-end; align-items: flex-end; }
.hrf-msg.align-items-start { align-self: flex-start; align-items: flex-start; }
.hrf-msg-sender { font-size: 10px; color: #888; margin-bottom: 3px; }
.hrf-bubble-user,
.hrf-bubble-bot,
.hrf-bubble-error {
    padding: 9px 12px;
    border-radius: 14px;
    font-size: 13px;
    line-height: 1.55;
    word-wrap: break-word;
}
.hrf-bubble-user {
    background: #375DA7;
    color: #fff;
    border-bottom-right-radius: 4px;
}
.hrf-bubble-bot {
    background: #fff;
    color: #222;
    border: 1px solid #e0e4ea;
    border-bottom-left-radius: 4px;
}
.hrf-bubble-error {
    background: #fff5f5;
    border: 1px solid #f5c6cb;
    border-bottom-left-radius: 4px;
}

/* Source citations */
.hrf-sources { margin-top: 4px; display: flex; flex-wrap: wrap; gap: 4px; }
.hrf-source-pill {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 12px;
    background: #eef2fa;
    border: 1px solid #c8d0e0;
    color: #375DA7;
    white-space: normal;
}
.hrf-not-grounded {
    margin-top: 4px;
    display: inline-block;
    font-size: 11px;
    color: #856404;
    background: #fff3cd;
    border: 1px solid #ffeeba;
    padding: 3px 8px;
    border-radius: 10px;
}

/* Feedback */
.hrf-feedback { margin-top: 3px; display: flex; align-items: center; gap: 4px; }
.hrf-feedback small { font-size: 10px; color: #888; }
.hrf-feedback button {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 12px;
    padding: 1px 3px;
    border-radius: 4px;
    line-height: 1;
}
.hrf-feedback button:hover { background: #e8eef8; }

/* Typing dots */
.hrf-typing-bubble {
    background: #fff;
    border: 1px solid #e0e4ea;
    border-radius: 14px;
    border-bottom-left-radius: 4px;
    padding: 10px 14px;
    display: flex;
    gap: 4px;
    align-items: center;
}
.hrf-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #aaa;
    animation: hrf-bounce 1.1s ease-in-out infinite;
}
.hrf-dot:nth-child(2) { animation-delay: 0.18s; }
.hrf-dot:nth-child(3) { animation-delay: 0.36s; }
@keyframes hrf-bounce {
    0%,60%,100% { transform: translateY(0); }
    30%         { transform: translateY(-5px); }
}

/* Loading skeleton for the first history fetch */
#hrf-skeleton { padding: 4px 0; display: flex; flex-direction: column; gap: 10px; }
.hrf-skel-row { display: flex; flex-direction: column; gap: 4px; }
.hrf-skel-row.mine { align-items: flex-end; }
.hrf-skel-bar {
    height: 28px;
    border-radius: 12px;
    width: 60%;
    background: linear-gradient(90deg, #e8ebf0 25%, #f2f4f7 37%, #e8ebf0 63%);
    background-size: 400% 100%;
    animation: hrf-shimmer 1.4s ease infinite;
}
.hrf-skel-row.mine .hrf-skel-bar { width: 40%; }
@keyframes hrf-shimmer {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}

/* Quick suggestions */
#hrf-suggestions {
    padding: 6px 14px 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    background: #f4f6f9;
    flex-shrink: 0;
}
.hrf-chip {
    font-size: 11px;
    padding: 4px 10px;
    border-radius: 20px;
    border: 1px solid #c8d0e0;
    background: #fff;
    color: #375DA7;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
    white-space: nowrap;
}
.hrf-chip:hover { background: #e8eef8; border-color: #375DA7; }

/* ── Input area ─────────────────────────────────────────────────── */
#hrf-footer {
    padding: 10px 12px;
    border-top: 1px solid #e8eaee;
    background: #fff;
    display: flex;
    gap: 8px;
    align-items: flex-end;
    flex-shrink: 0;
}
#hrf-input {
    flex: 1;
    border: 1px solid #d0d5e0;
    border-radius: 20px;
    padding: 8px 14px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
    resize: none;
    line-height: 1.4;
    max-height: 80px;
    overflow-y: auto;
    background: #f8f9fb;
    transition: border-color 0.15s, background 0.15s;
}
#hrf-input:focus { border-color: #375DA7; background: #fff; }
#hrf-input::placeholder { color: #aaa; }
#hrf-send-btn {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #375DA7;
    border: none;
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background 0.15s, opacity 0.15s;
}
#hrf-send-btn:hover  { background: #2a4a8a; }
#hrf-send-btn:disabled { opacity: 0.45; cursor: not-allowed; }

/* ── Full page link ─────────────────────────────────────────────── */
#hrf-fullpage {
    text-align: center;
    padding: 6px;
    background: #f4f6f9;
    border-top: 1px solid #e8eaee;
    flex-shrink: 0;
}
#hrf-fullpage a {
    font-size: 11px;
    color: #375DA7;
    text-decoration: none;
}
#hrf-fullpage a:hover { text-decoration: underline; }

/* ── Markdown output (rendered via marked.js, same as the full page) ───── */
.hrf-bubble-bot strong, .hrf-bubble-error strong { font-weight: 600; }
.hrf-bubble-bot em, .hrf-bubble-error em         { font-style: italic; }
.hrf-bubble-bot h1, .hrf-bubble-bot h2, .hrf-bubble-bot h3 { font-size: 13px; font-weight: 700; color: #0056b3; margin: 6px 0 3px; }
.hrf-bubble-bot h4, .hrf-bubble-bot h5, .hrf-bubble-bot h6 { font-size: 12px; font-weight: 600; margin: 5px 0 2px; }
.hrf-bubble-bot p  { margin: 0 0 8px; }
.hrf-bubble-bot p:last-child { margin-bottom: 0; }
.hrf-bubble-bot ul, .hrf-bubble-bot ol { margin: 0 0 8px; padding-left: 18px; }
.hrf-bubble-bot li { margin-bottom: 2px; }
.hrf-bubble-bot hr { border: none; border-top: 1px solid #e0e4ea; margin: 6px 0; }
.hrf-bubble-bot code {
    background: #f0f0f0;
    padding: 1px 4px;
    border-radius: 3px;
    font-size: 11px;
    color: #d63384;
}
.hrf-bubble-bot table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 8px; }
.hrf-bubble-bot th, .hrf-bubble-bot td { padding: 3px 5px; border: 1px solid #e0e4ea; vertical-align: top; }
.hrf-bubble-bot thead th { background: #f4f6f9; }
</style>

{{-- ── Launcher bubble ─────────────────────────────────────────────── --}}
<button id="hrf-launcher" onclick="hrFloat.toggle()" aria-label="Open HR Policy Assistant" aria-expanded="false" aria-controls="hrf-panel">
    <i class="la la-robot"></i>
    <span id="hrf-badge" aria-hidden="true">1</span>
</button>

{{-- ── Widget panel ────────────────────────────────────────────────── --}}
<div id="hrf-panel" role="dialog" aria-label="HR Policy Assistant" aria-modal="true">

    {{-- Header --}}
    <div id="hrf-header">
        <div id="hrf-header-icon"><i class="la la-robot"></i></div>
        <div id="hrf-header-title">
            <p class="hrf-name">HRDO Assistant</p>
            <p class="hrf-sub">Pasig City Government · AI-Powered</p>
        </div>
        <div id="hrf-header-actions">
            <button onclick="hrFloat.clearView()" title="Clears the chat view only — your question history is still saved" aria-label="Clear view"><i class="la la-eraser"></i></button>
            <button onclick="hrFloat.close()" title="Close" aria-label="Close chat"><i class="la la-times"></i></button>
        </div>
    </div>

    {{-- Message thread --}}
    <div id="hrf-thread" aria-live="polite">
        {{-- Populated by JS on first open --}}
    </div>

    {{-- Quick suggestion chips --}}
    <div id="hrf-suggestions">
        <button type="button" class="hrf-chip" onclick="hrFloat.ask('What are my leave entitlements?')">🏖️ Leave</button>
        <button type="button" class="hrf-chip" onclick="hrFloat.ask('What is the Code of Conduct for government employees?')">📋 Code of Conduct</button>
        <button type="button" class="hrf-chip" onclick="hrFloat.ask('What are my GSIS and PhilHealth benefits?')">💼 Benefits</button>
    </div>

    {{-- Input --}}
    <div id="hrf-footer">
        <textarea
            id="hrf-input"
            rows="1"
            placeholder="Ask about HR policies…"
            aria-label="Your question"
            onkeydown="hrFloat.handleKey(event)"
            oninput="hrFloat.handleInput(this)"
        ></textarea>
        <button id="hrf-send-btn" onclick="hrFloat.send()" aria-label="Send" disabled>
            <i class="la la-paper-plane" style="font-size:15px;"></i>
        </button>
    </div>

    {{-- Link to full page --}}
    <div id="hrf-fullpage">
        <a href="{{ backpack_url('hr-assistant') }}">
            <i class="la la-external-link-alt"></i> Open full HR Assistant page
        </a>
    </div>

</div>

{{-- ── Widget JavaScript ────────────────────────────────────────────── --}}
<script src="{{ asset('js/hr-chat-core.js') }}"></script>
<script>
(function() {
    'use strict';

    var DRAFT_KEY = 'hrf-draft';
    var OPEN_KEY  = 'hrf-open';

    var chat = HrChatCore.create({
        globalName: 'hrFloat',
        ids: {
            thread: 'hrf-thread',
            input: 'hrf-input',
            sendBtn: 'hrf-send-btn',
            // no emptyState / recentList / clearHistoryBtn — the widget has no
            // history sidebar by design, only a lightweight thread + chips.
        },
        endpoints: {
            ask: @json(route('hr.chat.ask')),
            feedback: @json(route('hr.chat.feedback')),
            history: @json(route('hr.chat.history')),
            clearHistory: @json(route('hr.chat.clear_history')),
        },
        csrf: @json(csrf_token()),
        userName: @json(backpack_user()->name),
        classes: {
            wrapUser: 'hrf-msg align-items-end',
            wrapBot: 'hrf-msg align-items-start',
            senderTag: 'span',
            senderClass: 'hrf-msg-sender',
            bubbleUser: 'hrf-bubble-user',
            bubbleBot: 'hrf-bubble-bot',
            bubbleBotError: 'hrf-bubble-error',
            sourcesWrap: 'hrf-sources',
            sourcePill: 'hrf-source-pill',
            notGroundedBanner: 'hrf-not-grounded',
            typingBubble: 'hrf-typing-bubble',
            feedbackWrap: 'hrf-feedback',
            feedbackLabel: '',
            feedbackBtnUp: '',
            feedbackBtnDown: '',
        },
        typingHtml: '<span class="hrf-dot"></span><span class="hrf-dot"></span><span class="hrf-dot"></span>'
            + '<span style="position:absolute;width:1px;height:1px;overflow:hidden;">%SEARCHING%</span>',
        strings: {
            assistantName: 'HRDO Assistant',
            searching: 'Searching policies…',
            helpfulPrompt: 'Helpful?',
            thumbsUpAria: 'Mark answer as helpful',
            thumbsDownAria: 'Mark answer as not helpful',
            thanksHelpful: '\u{1F44D} Thanks!',
            notedUnhelpful: '\u{1F44E} Noted!',
            // Chrome (buttons/placeholder/labels) stays English by design.
            // Substantive messages are bilingual so Tagalog-speaking employees
            // aren't left guessing, especially since Tagalog questions are the
            // most likely to hit the ungrounded/no-policy-found path.
            notGrounded: 'Not from an official policy document — verify with HRDO. / Hindi galing sa opisyal na dokumento — mangyaring i-verify sa HRDO.',
            connectionError: 'Connection error. Please try again or contact HRDO directly.\n\n*(Nagkaproblema sa koneksyon. Subukan muli o makipag-ugnayan nang direkta sa HRDO.)*',
        },
        computeSendDisabled: function (isLoading, input) {
            return isLoading || !input.value.trim();
        },
        onInputCleared: function (input) {
            if (input) { input.style.height = 'auto'; }
            sessionStorage.removeItem(DRAFT_KEY);
        },
        onClearView: function () {
            hasWelcome = false;
            showWelcome();
        },
    });

    var WELCOME_TEXT = "Hi! I'm your HRDO Policy Assistant. Ask me anything about "
        + "leave, benefits, conduct, performance, or other HR policies.\n\n"
        + "*(Kumusta! Ako ang inyong HRDO Policy Assistant. Magtanong tungkol sa "
        + "leave, benepisyo, asal, performance, o iba pang patakaran ng HR.)*";

    var isOpen     = false;
    var hasWelcome = false;

    function showWelcome() {
        if (hasWelcome) return;
        chat._appendAi(WELCOME_TEXT, [], null, false);
        hasWelcome = true;
    }

    function showSkeleton() {
        var thread = document.getElementById('hrf-thread');
        var el = document.createElement('div');
        el.id = 'hrf-skeleton';
        el.innerHTML =
            '<div class="hrf-skel-row"><div class="hrf-skel-bar"></div></div>' +
            '<div class="hrf-skel-row mine"><div class="hrf-skel-bar"></div></div>' +
            '<div class="hrf-skel-row"><div class="hrf-skel-bar"></div></div>';
        thread.appendChild(el);
    }

    function hideSkeleton() {
        var el = document.getElementById('hrf-skeleton');
        if (el) el.remove();
    }

    function hideSuggestions() {
        var s = document.getElementById('hrf-suggestions');
        if (s) s.style.display = 'none';
    }

    function restoreDraft() {
        var draft = sessionStorage.getItem(DRAFT_KEY);
        if (!draft) return;
        var input = document.getElementById('hrf-input');
        input.value = draft;
        window.hrFloat.autoResize(input);
    }

    window.hrFloat = {

        /* ── Open / close ─────────────────────────────── */
        toggle: function() {
            isOpen ? this.close() : this.open();
        },

        open: function() {
            isOpen = true;
            document.getElementById('hrf-panel').classList.add('open');
            document.getElementById('hrf-launcher').classList.add('open');
            document.getElementById('hrf-launcher').setAttribute('aria-expanded', 'true');
            document.getElementById('hrf-badge').style.display = 'none';
            sessionStorage.setItem(OPEN_KEY, '1');

            chat.ensureMarkdown()['catch'](function () {});

            if (!hasWelcome && !chat.historyLoaded) {
                showSkeleton();
                chat.loadHistory().then(function (logs) {
                    hideSkeleton();
                    if (!logs || !logs.length) {
                        showWelcome();
                    } else {
                        hideSuggestions();
                    }
                })['catch'](function () {
                    hideSkeleton();
                    showWelcome();
                });
            }

            setTimeout(function() {
                document.getElementById('hrf-input').focus();
            }, 220);
        },

        close: function() {
            isOpen = false;
            document.getElementById('hrf-panel').classList.remove('open');
            document.getElementById('hrf-launcher').classList.remove('open');
            document.getElementById('hrf-launcher').setAttribute('aria-expanded', 'false');
            sessionStorage.removeItem(OPEN_KEY);
        },

        // Visual reset only — see HrChatCore.clearView(). Does not delete
        // saved history; "Open full HR Assistant page" will still show it.
        clearView: function() {
            chat.clearView();
            document.getElementById('hrf-suggestions').style.display = '';
            document.getElementById('hrf-input').value = '';
            sessionStorage.removeItem(DRAFT_KEY);
            document.getElementById('hrf-input').focus();
        },

        /* ── Send flow ────────────────────────────────── */
        send: function() {
            hideSuggestions();
            chat.send();
        },

        ask: function(question) {
            if (chat.loading) return;
            if (!isOpen) this.open();
            hideSuggestions();
            chat.ask(question);
        },

        // Passthrough so the feedback buttons rendered by HrChatCore (which
        // call `<globalName>.giveFeedback(...)`) can reach the chat instance.
        giveFeedback: function(logId, value, btn) {
            chat.giveFeedback(logId, value, btn);
        },

        /* ── Input helpers ────────────────────────────── */
        handleKey: function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.send();
            }
        },

        handleInput: function(el) {
            this.autoResize(el);
            sessionStorage.setItem(DRAFT_KEY, el.value);
        },

        autoResize: function(el) {
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 80) + 'px';
            document.getElementById('hrf-send-btn').disabled = !el.value.trim() || chat.loading;
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        restoreDraft();
        if (sessionStorage.getItem(OPEN_KEY) === '1') {
            hrFloat.open();
        }
    });

})();
</script>

@endif
