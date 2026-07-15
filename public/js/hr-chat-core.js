/**
 * Shared chat engine for the HRDO Assistant.
 * Used by both the full-page view (resources/views/admin/hr_chat.blade.php)
 * and the floating widget (resources/views/vendor/backpack/base/inc/hr-float-widget.blade.php).
 * Each caller instantiates its own HrChatCore.create(config) with its own
 * element ids / classes, so the two surfaces stay visually independent while
 * sharing one implementation of send/render/history/feedback logic.
 */
(function (global) {
    'use strict';

    var MARKED_SRC = 'https://cdn.jsdelivr.net/npm/marked@11.1.1/marked.min.js';
    var MARKED_INTEGRITY = 'sha384-zbcZAIxlvJtNE3Dp5nxLXdXtXyxwOdnILY1TDPVmKFhl4r4nSUG1r8bcFXGVa4Te';
    var markedLoadPromise = null;

    function loadMarked() {
        if (global.marked) return Promise.resolve();
        if (markedLoadPromise) return markedLoadPromise;
        markedLoadPromise = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = MARKED_SRC;
            s.integrity = MARKED_INTEGRITY;
            s.crossOrigin = 'anonymous';
            s.onload = function () {
                global.marked.use({ gfm: true, breaks: true });
                resolve();
            };
            s.onerror = function () {
                markedLoadPromise = null;
                reject(new Error('Failed to load marked.js'));
            };
            document.head.appendChild(s);
        });
        return markedLoadPromise;
    }

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // XSS defense: raw AI text is always HTML-escaped BEFORE being handed to
    // marked.parse(). Do not remove the esc() call below.
    function renderMarkdown(raw) {
        if (!raw) return '';
        var safe = esc(raw);
        if (!global.marked) {
            // marked not loaded yet (widget before first open) — fall back to
            // plain escaped text with line breaks so nothing is ever unescaped.
            return safe.replace(/\n/g, '<br>');
        }
        return global.marked.parse(safe);
    }

    function HrChat(config) {
        this.cfg = config;
        this.loading = false;
        this.historyLoaded = false;
    }

    HrChat.prototype.el = function (key) {
        var id = this.cfg.ids[key];
        return id ? document.getElementById(id) : null;
    };

    HrChat.prototype.ensureMarkdown = function () {
        return loadMarked();
    };

    HrChat.prototype._scrollThread = function () {
        var t = this.el('thread');
        if (!t) return;
        setTimeout(function () { t.scrollTop = t.scrollHeight; }, 30);
    };

    HrChat.prototype._removeEmptyState = function () {
        var empty = this.el('emptyState');
        if (empty) empty.remove();
    };

    HrChat.prototype._appendUser = function (text) {
        var c = this.cfg.classes;
        var div = document.createElement('div');
        div.className = c.wrapUser;
        div.innerHTML =
            '<' + c.senderTag + ' class="' + c.senderClass + '">' + esc(this.cfg.userName) + '</' + c.senderTag + '>' +
            '<div class="' + c.bubbleUser + '">' + esc(text) + '</div>';
        this.el('thread').appendChild(div);
        this._scrollThread();
        return div;
    };

    // Builds the inner HTML for a bot message: the rendered bubble, source
    // pills (or the not-grounded banner), and a feedback row that holds the
    // Helpful?/thumbs group plus the copy button on one line. Shared by
    // _appendAi (live answers) and _renderThreadFromLogs (page reload), so
    // both paths render an identical, complete structure.
    HrChat.prototype._buildAiHtml = function (text, sources, logId, isError) {
        var c = this.cfg.classes;
        var s = this.cfg.strings;
        sources = sources || [];
        isError = !!isError;

        // data-hrchat-bubble marks the node whose *rendered* (not raw markdown)
        // text the copy button should read.
        var bodyHtml = '<div class="' + (isError ? c.bubbleBotError : c.bubbleBot) + '" data-hrchat-bubble="1">' +
            renderMarkdown(text) + '</div>';

        var sourcesHtml = '';
        if (!isError && sources.length) {
            sourcesHtml = '<div class="' + c.sourcesWrap + '">' +
                sources.map(function (src) {
                    return '<span class="' + c.sourcePill + '"><i class="la la-file-alt"></i> ' + esc(src) + '</span>';
                }).join('') +
                '</div>';
        } else if (!isError && logId) {
            // Grounding trust marker: an answer with no retrieved policy chunks
            // must never look like a normal, source-backed answer.
            sourcesHtml = '<div class="' + c.notGroundedBanner + '">' +
                '<i class="la la-exclamation-circle"></i> ' + s.notGrounded +
                '</div>';
        }

        var copyBtnHtml = '<button type="button" class="' + c.copyBtn + '" title="Copy response" ' +
            'aria-label="Copy response" onclick="HrChatCore.copy(this)"><i class="la la-copy"></i></button>';

        var feedbackHtml = '';
        if (!isError && logId) {
            // data-hrchat-feedback-group scopes giveFeedback()'s "Thanks!" swap to
            // just the label+thumbs, so the copy button next to it survives.
            feedbackHtml =
                '<div class="' + c.feedbackWrap + '">' +
                    '<span data-hrchat-feedback-group="1">' +
                        '<small class="' + c.feedbackLabel + '">' + s.helpfulPrompt + '</small>' +
                        '<button type="button" class="' + c.feedbackBtnUp + '" aria-label="' + s.thumbsUpAria + '" ' +
                            'onclick="' + this.cfg.globalName + '.giveFeedback(' + logId + ', 1, this)">&#128077;</button>' +
                        '<button type="button" class="' + c.feedbackBtnDown + '" aria-label="' + s.thumbsDownAria + '" ' +
                            'onclick="' + this.cfg.globalName + '.giveFeedback(' + logId + ', -1, this)">&#128078;</button>' +
                    '</span>' +
                    copyBtnHtml +
                '</div>';
        } else if (!isError) {
            // No logId (e.g. the static widget welcome message) — no feedback
            // possible yet, but still offer copy.
            feedbackHtml = '<div class="' + c.feedbackWrap + '">' + copyBtnHtml + '</div>';
        }

        return '<' + c.senderTag + ' class="' + c.senderClass + '">' + esc(s.assistantName) + '</' + c.senderTag + '>' +
            bodyHtml + sourcesHtml + feedbackHtml;
    };

    HrChat.prototype._appendAi = function (text, sources, logId, isError) {
        var c = this.cfg.classes;
        var div = document.createElement('div');
        div.className = c.wrapBot;
        // data-hrchat-msg scopes the copy button's DOM lookup to this message.
        div.setAttribute('data-hrchat-msg', '1');
        div.innerHTML = this._buildAiHtml(text, sources, logId, isError);

        this.el('thread').appendChild(div);
        this._scrollThread();
        return div;
    };

    HrChat.prototype._appendTyping = function () {
        var c = this.cfg.classes;
        var s = this.cfg.strings;
        var div = document.createElement('div');
        div.className = c.wrapBot;
        div.setAttribute('data-hrchat-typing', '1');
        div.innerHTML =
            '<' + c.senderTag + ' class="' + c.senderClass + '">' + esc(s.assistantName) + '</' + c.senderTag + '>' +
            '<div class="' + c.typingBubble + '">' + this.cfg.typingHtml.replace('%SEARCHING%', esc(s.searching)) + '</div>';
        this.el('thread').appendChild(div);
        this._scrollThread();
        return div;
    };

    HrChat.prototype.giveFeedback = function (logId, value, btn) {
        var s = this.cfg.strings;
        fetch(this.cfg.endpoints.feedback, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.cfg.csrf },
            body: JSON.stringify({ log_id: logId, feedback: value }),
        }).then(function () {
            // Scoped to the label+thumbs group only — the copy button sits
            // beside it in the same row and must not be wiped out too.
            var group = btn.closest('[data-hrchat-feedback-group]');
            if (group) {
                group.innerHTML = '<small>' + (value === 1 ? s.thanksHelpful : s.notedUnhelpful) + '</small>';
            }
        });
    };

    HrChat.prototype._setLoading = function (isLoading) {
        this.loading = isLoading;
        var sendBtn = this.el('sendBtn');
        if (!sendBtn) return;
        if (this.cfg.computeSendDisabled) {
            sendBtn.disabled = this.cfg.computeSendDisabled(isLoading, this.el('input'));
        } else {
            sendBtn.disabled = isLoading;
        }
    };

    HrChat.prototype._send = function (text) {
        var self = this;
        this._removeEmptyState();
        this._appendUser(text);
        var typing = this._appendTyping();
        this._setLoading(true);

        this.ensureMarkdown()['catch'](function () { /* fall back to escaped text, non-fatal */ });

        fetch(this.cfg.endpoints.ask, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.cfg.csrf },
            body: JSON.stringify({ question: text }),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                typing.remove();
                self._appendAi(data.answer, data.sources || [], data.log_id);
                self._setLoading(false);
                if (self.cfg.onAfterAnswer) self.cfg.onAfterAnswer();
            })
            ['catch'](function () {
                typing.remove();
                self._appendAi(self.cfg.strings.connectionError, [], null, true);
                self._setLoading(false);
            });
    };

    // Reads and clears the configured input element itself, so callers can
    // bind this directly to a send button with no arguments.
    HrChat.prototype.send = function () {
        var input = this.el('input');
        var text = input ? input.value.trim() : '';
        if (!text || this.loading) return;
        if (input) input.value = '';
        if (this.cfg.onInputCleared) this.cfg.onInputCleared(input);
        this._send(text);
    };

    HrChat.prototype.ask = function (question) {
        if (this.loading) return;
        if (this.cfg.onBeforeAsk) this.cfg.onBeforeAsk();
        this._send(question);
    };

    // Visual reset only — does not create a new server-side conversation.
    // HrChatLog has no conversation grouping, so this intentionally does not
    // call any "new conversation" endpoint. See project notes.
    HrChat.prototype.clearView = function () {
        var thread = this.el('thread');
        if (thread) thread.innerHTML = '';
        if (this.cfg.onClearView) this.cfg.onClearView();
    };

    HrChat.prototype._fetchLogs = function () {
        return fetch(this.cfg.endpoints.history).then(function (res) { return res.json(); });
    };

    // Populates the thread from saved logs. Only ever called once per page
    // session (guarded by historyLoaded) — this must NOT be reused to
    // "refresh" the thread, or every subsequent answer would duplicate the
    // entire history on top of itself.
    HrChat.prototype._renderThreadFromLogs = function (logs) {
        if (!logs || !logs.length) return;

        this._removeEmptyState();
        this.ensureMarkdown()['catch'](function () {});

        var thread = this.el('thread');
        var fragment = document.createDocumentFragment();
        var c = this.cfg.classes;
        var self = this;

        logs.slice().reverse().forEach(function (log) {
            var userDiv = document.createElement('div');
            userDiv.className = c.wrapUser;
            userDiv.innerHTML =
                '<' + c.senderTag + ' class="' + c.senderClass + '">' + esc(self.cfg.userName) + '</' + c.senderTag + '>' +
                '<div class="' + c.bubbleUser + '">' + esc(log.question) + '</div>';
            fragment.appendChild(userDiv);

            var aiDiv = document.createElement('div');
            aiDiv.className = c.wrapBot;
            aiDiv.setAttribute('data-hrchat-msg', '1');
            aiDiv.innerHTML = self._buildAiHtml(log.answer, log.sources || [], log.id, false);
            fragment.appendChild(aiDiv);
        });

        thread.appendChild(fragment);
        this._scrollThread();
    };

    HrChat.prototype.loadHistory = function (force) {
        var self = this;
        if (this.historyLoaded && !force) return Promise.resolve();

        return this._fetchLogs().then(function (logs) {
            self.historyLoaded = true;
            self._renderRecentList(logs);
            self._renderThreadFromLogs(logs);
            return logs;
        });
    };

    HrChat.prototype._renderRecentList = function (logs) {
        var list = this.el('recentList');
        if (!list) return; // widget has no sidebar — nothing to render

        var clearBtn = this.el('clearHistoryBtn');
        list.innerHTML = '';

        if (!logs || !logs.length) {
            if (clearBtn) clearBtn.style.display = 'none';
            list.innerHTML = this.cfg.strings.recentListEmptyHtml;
            return;
        }

        if (clearBtn) clearBtn.style.display = '';
        var self = this;
        var fragment = document.createDocumentFragment();
        logs.forEach(function (log) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = self.cfg.classes.recentItem;
            btn.innerHTML = '<i class="la la-comment-alt mt-2" style="margin-right:5px;"></i>' + esc(log.question);
            btn.onclick = function () { self.ask(log.question); };
            fragment.appendChild(btn);
        });
        list.appendChild(fragment);
    };

    // Refreshes only the sidebar "recent prompts" list (page mode). Must never
    // touch the thread — the new answer was already appended by _send().
    HrChat.prototype.refreshSidebarHistory = function () {
        var self = this;
        return this._fetchLogs().then(function (logs) {
            self._renderRecentList(logs);
            return logs;
        });
    };

    // Confirms via the same SweetAlert dialog Backpack's own CRUD delete
    // buttons use (bundled globally in packages/backpack/base/js/bundle.js),
    // so this looks and behaves like every other destructive-action confirm
    // in the admin panel instead of a bare browser confirm() popup.
    HrChat.prototype._confirm = function (message) {
        if (typeof global.swal !== 'function') {
            // SweetAlert not loaded on this page for some reason — fall back
            // rather than silently doing nothing.
            return Promise.resolve(global.confirm(message));
        }
        return global.swal({
            title: 'Warning!',
            text: message,
            icon: 'warning',
            buttons: ['Cancel', 'Yes, clear it'],
            dangerMode: true,
        }).then(function (confirmed) { return !!confirmed; });
    };

    HrChat.prototype.clearHistory = function (confirmMessage) {
        var self = this;
        return this._confirm(confirmMessage).then(function (confirmed) {
            if (!confirmed) return { ok: false };

            return fetch(self.cfg.endpoints.clearHistory, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': self.cfg.csrf },
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.ok) {
                        self.historyLoaded = false;
                        self._renderRecentList([]);
                        self.clearView();
                    }
                    return data;
                });
        });
    };

    // Textarea+execCommand fallback for browsers/contexts where the async
    // Clipboard API is unavailable (e.g. non-HTTPS, older browsers).
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch (e) { /* nothing more we can do */ }
        document.body.removeChild(ta);
    }

    // Copies the *rendered* answer (what's actually on screen), not the raw
    // markdown source. Reads innerText off the message's rendered bubble
    // (marked by data-hrchat-bubble) rather than a stored raw-text attribute,
    // so bold/lists/code formatting markers are stripped like a real copy.
    function copyResponse(btn) {
        var msg = btn.closest('[data-hrchat-msg]');
        var bubble = msg ? msg.querySelector('[data-hrchat-bubble]') : null;
        var text = bubble ? (bubble.innerText || bubble.textContent || '') : '';
        var original = btn.innerHTML;
        function showCopied() {
            btn.innerHTML = '<i class="la la-check"></i>';
            setTimeout(function () { btn.innerHTML = original; }, 1500);
        }
        if (!text) return;
        if (global.navigator && global.navigator.clipboard && global.navigator.clipboard.writeText) {
            global.navigator.clipboard.writeText(text).then(showCopied)['catch'](function () {
                fallbackCopy(text);
                showCopied();
            });
        } else {
            fallbackCopy(text);
            showCopied();
        }
    }

    global.HrChatCore = {
        create: function (config) { return new HrChat(config); },
        renderMarkdown: renderMarkdown,
        esc: esc,
        loadMarked: loadMarked,
        copy: copyResponse,
    };

})(window);
