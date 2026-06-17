{{--
    Floating HR Policy Assistant Widget
    ====================================
    Path: resources/views/vendor/backpack/base/inc/hr-float-widget.blade.php

    Included from: resources/views/vendor/backpack/base/inc/footer.blade.php
    Appears on every authenticated Backpack page as a bottom-right chat bubble.
    Shares the same backend routes as the full HR Assistant page.
--}}
@if(backpack_auth()->check())

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
.hrf-msg.user { align-self: flex-end; align-items: flex-end; }
.hrf-msg.bot  { align-self: flex-start; align-items: flex-start; }
.hrf-msg-sender { font-size: 10px; color: #888; margin-bottom: 3px; }
.hrf-msg-bubble {
    padding: 9px 12px;
    border-radius: 14px;
    font-size: 13px;
    line-height: 1.55;
    word-wrap: break-word;
}
.hrf-msg.user .hrf-msg-bubble {
    background: #375DA7;
    color: #fff;
    border-bottom-right-radius: 4px;
}
.hrf-msg.bot .hrf-msg-bubble {
    background: #fff;
    color: #222;
    border: 1px solid #e0e4ea;
    border-bottom-left-radius: 4px;
}

/* Typing dots */
#hrf-typing { display: none; align-self: flex-start; }
#hrf-typing.visible { display: flex; }
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

/* ── Markdown output ────────────────────────────────────────────── */
.hrf-msg-bubble strong { font-weight: 600; }
.hrf-msg-bubble em     { font-style: italic; }
.hrf-msg-bubble h4     { font-size: 13px; font-weight: 600; margin: 6px 0 2px; }
.hrf-msg-bubble h5     { font-size: 12px; font-weight: 600; margin: 5px 0 2px; }
.hrf-msg-bubble h6     { font-size: 12px; font-weight: 600; margin: 4px 0 2px; }
.hrf-msg-bubble hr     { border: none; border-top: 1px solid #e0e4ea; margin: 6px 0; }
.hrf-msg-bubble code   { background: #f0f0f0; padding: 1px 4px; border-radius: 3px; font-size: 11px; }
</style>

{{-- ── Launcher bubble ─────────────────────────────────────────────── --}}
<button id="hrf-launcher" onclick="hrFloat.toggle()" aria-label="Open HR Policy Assistant">
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
            <button onclick="hrFloat.newChat()" title="New conversation"><i class="la la-plus"></i></button>
            <button onclick="hrFloat.close()" title="Close" aria-label="Close chat"><i class="la la-times"></i></button>
        </div>
    </div>

    {{-- Message thread --}}
    <div id="hrf-thread">
        {{-- Welcome message (rendered by JS on first open) --}}
    </div>

    {{-- Typing indicator --}}
    <div id="hrf-typing" class="hrf-msg bot" aria-live="polite" aria-label="Assistant is typing">
        <div class="hrf-typing-bubble">
            <span class="hrf-dot"></span>
            <span class="hrf-dot"></span>
            <span class="hrf-dot"></span>
        </div>
    </div>

    {{-- Quick suggestion chips --}}
    <div id="hrf-suggestions">
        <button class="hrf-chip" onclick="hrFloat.ask('What are my leave entitlements?')">🏖️ Leave</button>
        <button class="hrf-chip" onclick="hrFloat.ask('What is the Code of Conduct for government employees?')">📋 Code of Conduct</button>
        <button class="hrf-chip" onclick="hrFloat.ask('What are my GSIS and PhilHealth benefits?')">💼 Benefits</button>
    </div>

    {{-- Input --}}
    <div id="hrf-footer">
        <textarea
            id="hrf-input"
            rows="1"
            placeholder="Ask about HR policies…"
            aria-label="Your question"
            onkeydown="hrFloat.handleKey(event)"
            oninput="hrFloat.resize(this)"
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
<script>
(function() {
    'use strict';

    var ENDPOINT    = @json(route('hr.chat.ask'));
    var CSRF        = @json(csrf_token());
    var USER_NAME   = @json(backpack_user()->name);
    var isOpen      = false;
    var isLoading   = false;
    var hasWelcome  = false;

    window.hrFloat = {

        /* ── Open / close ─────────────────────────────── */
        toggle: function() {
            isOpen ? this.close() : this.open();
        },

        open: function() {
            isOpen = true;
            document.getElementById('hrf-panel').classList.add('open');
            document.getElementById('hrf-launcher').classList.add('open');
            document.getElementById('hrf-badge').style.display = 'none';
            if (!hasWelcome) { this._showWelcome(); hasWelcome = true; }
            setTimeout(function() {
                document.getElementById('hrf-input').focus();
            }, 220);
        },

        close: function() {
            isOpen = false;
            document.getElementById('hrf-panel').classList.remove('open');
            document.getElementById('hrf-launcher').classList.remove('open');
        },

        newChat: function() {
            document.getElementById('hrf-thread').innerHTML = '';
            hasWelcome = false;
            this._showWelcome();
            hasWelcome = true;
            document.getElementById('hrf-input').value = '';
            document.getElementById('hrf-send-btn').disabled = true;
            document.getElementById('hrf-input').focus();
        },

        /* ── Send flow ────────────────────────────────── */
        send: function() {
            var input = document.getElementById('hrf-input');
            var text  = input.value.trim();
            if (!text || isLoading) return;
            input.value = '';
            this.resize(input);
            document.getElementById('hrf-send-btn').disabled = true;
            this._hideSuggestions();
            this._doSend(text);
        },

        ask: function(question) {
            if (isLoading) return;
            if (!isOpen) this.open();
            this._hideSuggestions();
            this._doSend(question);
        },

        _doSend: function(text) {
            this._appendUser(text);
            this._showTyping();
            isLoading = true;

            fetch(ENDPOINT, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body:    JSON.stringify({ question: text }),
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                hrFloat._hideTyping();
                hrFloat._appendBot(data.answer || 'No response received.');
                isLoading = false;
                document.getElementById('hrf-send-btn').disabled =
                    !document.getElementById('hrf-input').value.trim();
            })
            .catch(function() {
                hrFloat._hideTyping();
                hrFloat._appendBot('Connection error. Please try again or contact HRDO directly.', true);
                isLoading = false;
                document.getElementById('hrf-send-btn').disabled = false;
            });
        },

        /* ── Renderers ────────────────────────────────── */
        _showWelcome: function() {
            this._appendBot(
                "Hi! I'm your HRDO Policy Assistant. Ask me anything about " +
                "leave, benefits, conduct, performance, or other HR policies."
            );
        },

        _appendUser: function(text) {
            var thread = document.getElementById('hrf-thread');
            var div    = document.createElement('div');
            div.className = 'hrf-msg user';
            div.innerHTML =
                '<span class="hrf-msg-sender">' + this._esc(USER_NAME) + '</span>' +
                '<div class="hrf-msg-bubble">' + this._esc(text) + '</div>';
            thread.appendChild(div);
            this._scroll();
        },

        _appendBot: function(text, isError) {
            var thread = document.getElementById('hrf-thread');
            var div    = document.createElement('div');
            div.className = 'hrf-msg bot';
            div.innerHTML =
                '<span class="hrf-msg-sender">HRDO Assistant</span>' +
                '<div class="hrf-msg-bubble' + (isError ? '" style="border-color:#f5c6cb;background:#fff5f5;' : '"') + '">' +
                this._renderMarkdown(text) +
                '</div>';
            thread.appendChild(div);
            this._scroll();
        },

        _showTyping: function() {
            var t = document.getElementById('hrf-typing');
            t.classList.add('visible');
            document.getElementById('hrf-thread').appendChild(t);
            this._scroll();
        },

        _hideTyping: function() {
            document.getElementById('hrf-typing').classList.remove('visible');
        },

        _hideSuggestions: function() {
            var s = document.getElementById('hrf-suggestions');
            if (s) s.style.display = 'none';
        },

        _scroll: function() {
            var t = document.getElementById('hrf-thread');
            setTimeout(function() { t.scrollTop = t.scrollHeight; }, 30);
        },

        /* ── Input helpers ────────────────────────────── */
        handleKey: function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.send();
            }
        },

        resize: function(el) {
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 80) + 'px';
            document.getElementById('hrf-send-btn').disabled = !el.value.trim() || isLoading;
        },

        /* ── Markdown renderer ────────────────────────── */
        _renderMarkdown(raw) {
            if (!raw) return '';

            // 1. Escape all HTML special chars to prevent injection
            var text = String(raw)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');

            // 2. Restore explicit <br> tags the AI might have provided (e.g., inside tables)
            text = text.replace(/&lt;br\s*\/?&gt;/gi, '<br>');

            // 3. Parse Markdown Tables
            // Matches standard markdown tables: | Header | \n |---| \n | Body |
            text = text.replace(/^\|(.+)\|[ \t]*\n\|([-:| \t]+)\|[ \t]*\n((?:\|.*\|[ \t]*(?:\n|$))*)/gm, function(match, header, sep, body) {
                let html = '<div class="table-responsive my-3"><table class="table table-bordered table-sm bg-white mb-0" style="font-size:13px;"><thead><tr>';

                // Cleanly extract header cells and filter out empty outer elements
                let hCells = header.split('|').map(c => c.trim());
                if (hCells.length > 0 && hCells[0] === '') hCells.shift();
                if (hCells.length > 0 && hCells[hCells.length-1] === '') hCells.pop();

                // Store the exact number of columns we expect
                let numCols = hCells.length;

                hCells.forEach(function(cell) {
                    html += '<th class="bg-light">' + cell + '</th>';
                });
                html += '</tr></thead><tbody>';

                // Parse body
                if (body) {
                    body.trim().split('\n').forEach(function(row) {
                        html += '<tr>';
                        let bCells = row.split('|').map(c => c.trim());
                        if (bCells.length > 0 && bCells[0] === '') bCells.shift();
                        if (bCells.length > 0 && bCells[bCells.length-1] === '') bCells.pop();

                        // CRITICAL FIX: Force the row to output EXACTLY the same number of columns as the header.
                        // This prevents the rogue empty columns if the AI adds an extra trailing pipe.
                        for (let i = 0; i < numCols; i++) {
                            let cellContent = bCells[i] || '';

                            // Apply inline code formatting and bullets inside table cells
                            cellContent = cellContent
                                .replace(/`([^`]+)`/g, '<code>$1</code>')
                                .replace(/•\s+/g, '&bull; ');

                            html += '<td>' + cellContent + '</td>';
                        }
                        html += '</tr>';
                    });
                }
                html += '</tbody></table></div>';
                return html;
            });

            // 4. Headings
            text = text.replace(/^### (.+)$/gm, '<h6 class="mt-3 mb-2 text-primary" style="font-size:14px;font-weight:700;">$1</h6>');
            text = text.replace(/^## (.+)$/gm,  '<h5 class="mt-3 mb-2 text-primary" style="font-size:15px;font-weight:700;">$1</h5>');
            text = text.replace(/^# (.+)$/gm,   '<h4 class="mt-3 mb-2 text-primary" style="font-size:16px;font-weight:700;">$1</h4>');

            // 5. Horizontal rule
            text = text.replace(/^---+$/gm, '<hr style="border-color:#dee2e6;margin:12px 0;">');

            // 6. Bold & Italic
            text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            text = text.replace(/__(.+?)__/g,      '<strong>$1</strong>');
            text = text.replace(/\*([^*\n]+?)\*/g, '<em>$1</em>');
            text = text.replace(/_([^_\n]+?)_/g,   '<em>$1</em>');

            // 7. Inline code
            text = text.replace(/`([^`]+)`/g,
                '<code style="background:#f0f0f0;padding:2px 5px;border-radius:4px;font-size:12px;color:#d63384;">$1</code>');

            // 8. Lists (Numbered & Bullet)
            // Standard line-start bullets
            text = text.replace(/^\d+\.\s+(.+)$/gm,
                '<div style="padding-left:1.5em;text-indent:-1.5em;margin:4px 0;"><strong>&bull;</strong>&nbsp;$1</div>');
            text = text.replace(/^[-*•]\s+(.+)$/gm,
                '<div style="padding-left:1.5em;text-indent:-1.5em;margin:4px 0;">&bull;&nbsp;$1</div>');

            // Clean up bullets placed directly after a <br> (like inside the AI's table cells)
            text = text.replace(/<br>\s*[-*•]\s+(.+?)(?=<br>|$|\n)/g, '<br>&bull;&nbsp;$1');

            // 9. Cleanup extra space around block tags
            text = text.replace(/\n+(<\/?(?:h[456]|div|hr|table|thead|tbody|tr|td|th)[^>]*>)/g, '$1');
            text = text.replace(/(<\/?(?:h[456]|div|hr|table|thead|tbody|tr|td|th)[^>]*>)\n+/g, '$1');

            // 10. Convert remaining newlines
            text = text.replace(/\n{2,}/g, '<br><br>');
            text = text.replace(/\n/g, '<br>');

            return text;
        },

        /* ── HTML escape (for user messages only) ───────────────── */
        _esc(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    };

})();
</script>

@endif
