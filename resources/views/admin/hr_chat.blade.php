@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid d-flex mb-2 mt-2">
    <h2><i class="la la-robot"></i> HR Assistant</h2>
</section>

<style>
    /* Scope all markdown styling strictly to the AI chat bubbles */
    .chat-markdown h1,
    .chat-markdown h2,
    .chat-markdown h3 {
        color: #0056b3; /* Bootstrap Primary Blue */
        font-weight: 700;
        margin-top: 16px;
        margin-bottom: 8px;
        line-height: 1.2;
    }

    /* Scale the headers down so they fit inside a chat bubble */
    .chat-markdown h1 { font-size: 16px; }
    .chat-markdown h2 { font-size: 15px; }
    .chat-markdown h3,
    .chat-markdown h4 { font-size: 14px; }

    /* Paragraph and List spacing */
    .chat-markdown p {
        margin-bottom: 12px;
    }
    .chat-markdown p:last-child {
        margin-bottom: 0; /* Prevents awkward gaps at the bottom of the chat bubble */
    }
    .chat-markdown ul,
    .chat-markdown ol {
        margin-bottom: 12px;
        padding-left: 24px; /* Indents bullets nicely */
    }
    .chat-markdown li {
        margin-bottom: 4px;
    }

    /* Inline Code styling (Replicates your old regex look) */
    .chat-markdown code {
        background: #f0f0f0;
        padding: 2px 5px;
        border-radius: 4px;
        font-size: 12px;
        color: #d63384;
    }

    /* Table styling (Mimics Bootstrap's table-bordered table-sm) */
    .chat-markdown table {
        width: 100%;
        margin-bottom: 1rem;
        border-collapse: collapse;
        font-size: 13px;
        background-color: #fff;
    }
    .chat-markdown table th,
    .chat-markdown table td {
        padding: 0.3rem;
        vertical-align: top;
        border: 1px solid #dee2e6;
    }
    .chat-markdown table thead th {
        vertical-align: bottom;
        border-bottom: 2px solid #dee2e6;
        background-color: #f8f9fa;
    }
</style>
@endsection

@section('content')
<div class="row">

    {{-- ── Sidebar ──────────────────────────────────────────── --}}
    <div class="col-md-3">

        {{-- Recent questions (Synchronized with 10 records backend schema) --}}
        <div class="card mt-3" id="hrc-recent-card">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <strong style="font-size:13px"><i class="la la-question-circle text-danger"></i> Recent Prompts</strong>
                <button class="btn btn-sm btn-link p-0 text-danger" id="hrc-clear-btn"
                    onclick="hrChat.clearHistory()" style="font-size:11px; text-decoration: none; font-weight: 600;">
                    <i class="la la-trash"></i> Clear All
                </button>
            </div>
            <div class="list-group list-group-flush" id="hrc-recent-list">
                {{-- Fallback UI will be injected here via JS if empty --}}
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body text-muted small">
                <i class="la la-info-circle"></i>
                AI responses are for guidance only. Contact HRDO directly for official rulings.
            </div>
        </div>

    </div>

    {{-- ── Main chat area ───────────────────────────────────── --}}
    <div class="col-md-9">
        <div class="card" style="height:calc(100vh - 220px);min-height:500px;display:flex;flex-direction:column;">

            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
                <span><i class="la la-robot"></i> <strong>HRDO Policy Assistant</strong>
                    <span class="badge badge-light ml-2">AI-Powered</span></span>
                <button class="btn btn-sm btn-outline-light" onclick="hrChat.newConversation()"
                    title="Start a new conversation">
                    <i class="la la-plus"></i> New
                </button>
            </div>

            {{-- Message thread --}}
            <div class="card-body" id="hrc-thread"
                style="overflow-y:auto;flex:1;background:#f8f9fa;">

                {{-- Empty state --}}
                <div id="hrc-empty" class="text-center text-muted mt-5">
                    <i class="la la-comments d-block" style="font-size:3rem;"></i>
                    <h5 class="mt-2">Ask me anything about HR policies</h5>
                    <p class="small">Leave, benefits, conduct, performance, discipline —
                        I'll find the answer from official HRDO documents.</p>
                </div>
            </div>

            {{-- Input --}}
            <div class="card-footer bg-white">
                <div class="input-group">
                    <input type="text" id="hrc-input" class="form-control"
                        placeholder="Ask about HR policies… (Enter to send)"
                        onkeydown="if(event.key==='Enter') hrChat.send()">
                    <div class="input-group-append">
                        <button class="btn btn-primary" id="hrc-send" onclick="hrChat.send()">
                            <i class="la la-paper-plane"></i>
                        </button>
                    </div>
                </div>
                <small class="text-muted">Press <kbd>Enter</kbd> to send</small>
            </div>

        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
const hrChat = {

    /* ── Config ────────────────────────────────────────────── */
    endpoint:        @json(route('hr.chat.ask')),
    feedbackUrl:     @json(route('hr.chat.feedback')),
    historyEndpoint: @json(route('hr.chat.history')),
    clearHistoryUrl: @json(route('hr.chat.clear_history')),
    csrf:            @json(csrf_token()),
    userName:        @json(backpack_user()->name),
    loading:         false,

    /* ── Public actions ─────────────────────────────────────── */
    send() {
        const input = document.getElementById('hrc-input');
        const text  = input.value.trim();
        if (!text || this.loading) return;
        input.value = '';
        this._send(text);
    },

    ask(question) {
        if (this.loading) return;
        this._send(question);
    },

    newConversation() {
        const thread = document.getElementById('hrc-thread');
        thread.innerHTML = '';
        // Re-inject empty state
        const empty = document.createElement('div');
        empty.id = 'hrc-empty';
        empty.className = 'text-center text-muted mt-5';
        empty.innerHTML = `
            <i class="la la-comments d-block" style="font-size:3rem;"></i>
            <h5 class="mt-2">Ask me anything about HR policies</h5>
            <p class="small">Leave, benefits, conduct, performance, discipline —
                I'll find the answer from official HRDO documents.</p>`;
        thread.appendChild(empty);
        document.getElementById('hrc-input').value = '';
        document.getElementById('hrc-input').focus();
    },

    /**
     * Absolute Database Deletion Routine
     */
    async clearHistory() {
        if (!confirm("Are you sure you want to permanently delete your search records? This action cannot be undone.")) {
            return;
        }

        try {
            const res = await fetch(this.clearHistoryUrl, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf
                }
            });
            const data = await res.json();

            if (data.ok) {
                // Instantly render the empty fallback UI
                const list = document.getElementById('hrc-recent-list');
                if (list) {
                    list.innerHTML =
                        '<div class="text-center p-4 text-muted bg-light">' +
                            '<i class="la la-history mb-2 text-secondary" style="font-size: 28px;"></i>' +
                            '<p class="mb-0" style="font-size: 12px;">Your recent searches will appear here.</p>' +
                        '</div>';
                }
                // Hide the clear button
                const clearBtn = document.getElementById('hrc-clear-btn');
                if (clearBtn) clearBtn.style.display = 'none';

                this.newConversation();
            }
        } catch (e) {
            console.error("Failed executing storage clear down action:", e);
        }
    },

    /* ── Core send flow ─────────────────────────────────────── */
    async _send(text) {
        const emptyEl = document.getElementById('hrc-empty');
        if (emptyEl) emptyEl.remove();

        this._appendMsg('user', text);
        const typing = this._appendTyping();
        this.loading = true;
        document.getElementById('hrc-send').disabled = true;

        try {
            const res  = await fetch(this.endpoint, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                body:    JSON.stringify({ question: text }),
            });
            const data = await res.json();
            typing.remove();
            this._appendAi(data.answer, data.sources || [], data.log_id);

            // Re-render the fresh sidebar layout immediately upon dynamic generation updates
            this.refreshSidebarHistory();
        } catch (e) {
            typing.remove();
            this._appendAi(
                'Connection error. Please try again or contact HRDO directly.',
                [], null, true
            );
        }

        this.loading = false;
        document.getElementById('hrc-send').disabled = false;
        document.getElementById('hrc-input').focus();
    },

    /* ── Message renderers ──────────────────────────────────── */
    _appendMsg(role, text) {
        const thread = document.getElementById('hrc-thread');
        const isUser = role === 'user';
        const div    = document.createElement('div');
        div.className = 'mb-3 d-flex flex-column ' + (isUser ? 'align-items-end' : 'align-items-start');
        div.innerHTML =
            '<small class="text-muted mb-1">' +
                this._esc(isUser ? this.userName : 'HRDO Assistant') +
            '</small>' +
            '<div class="p-2 rounded shadow-sm ' +
                (isUser ? 'bg-primary text-white' : 'bg-white border') +
                '" style="max-width:85%;word-wrap:break-word;">' +
                (isUser ? this._esc(text) : this._renderMarkdown(text)) +
            '</div>';
        thread.appendChild(div);
        thread.scrollTop = thread.scrollHeight;
        return div;
    },

    _appendAi(text, sources, logId, isError) {
        isError = isError || false;
        const thread = document.getElementById('hrc-thread');
        const div    = document.createElement('div');
        div.className = 'mb-3 d-flex flex-column align-items-start';

        const sourcePills = (sources || []).map(function(s) {
            return '<span class="badge badge-light border mr-1">' +
                   '<i class="la la-file-alt"></i> ' + hrChat._esc(s) + '</span>';
        }).join('');

        let feedbackHtml = '';
        if (!isError && logId) {
            feedbackHtml =
                '<div class="mt-1">' +
                    '<small class="text-muted">Helpful?</small>' +
                    '<button class="btn btn-sm btn-outline-success ml-1" ' +
                        'onclick="hrChat.giveFeedback(' + logId + ', 1, this)">&#128077;</button>' +
                    '<button class="btn btn-sm btn-outline-danger ml-1" ' +
                        'onclick="hrChat.giveFeedback(' + logId + ', -1, this)">&#128078;</button>' +
                '</div>';
        }

        div.innerHTML =
            '<small class="text-muted mb-1">HRDO Assistant</small>' +
            '<div class="p-2 rounded shadow-sm chat-markdown ' + (isError ? 'bg-warning' : 'bg-white border') +
                '" style="max-width:85%;word-wrap:break-word;">' +
                this._renderMarkdown(text) +
            '</div>' +
            (sourcePills ? '<div class="mt-1">' + sourcePills + '</div>' : '') +
            feedbackHtml;

        thread.appendChild(div);
        thread.scrollTop = thread.scrollHeight;
    },

    _appendTyping() {
        const thread = document.getElementById('hrc-thread');
        const div    = document.createElement('div');
        div.className = 'mb-3 d-flex flex-column align-items-start';
        div.id = 'hrc-typing';
        div.innerHTML =
            '<small class="text-muted mb-1">HRDO Assistant</small>' +
            '<div class="p-2 rounded bg-white border">' +
                '<span class="text-muted">' +
                    '<i class="la la-circle-notch la-spin"></i> Searching policies&hellip;' +
                '</span>' +
            '</div>';
        thread.appendChild(div);
        thread.scrollTop = thread.scrollHeight;
        return div;
    },

    /* ── Feedback ───────────────────────────────────────────── */
    async giveFeedback(logId, value, btn) {
        await fetch(this.feedbackUrl, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf },
            body:    JSON.stringify({ log_id: logId, feedback: value }),
        });
        btn.closest('div').innerHTML =
            '<small class="text-muted">' + (value === 1 ? '&#128077; Thanks!' : '&#128078; Noted!') + '</small>';
    },

    /* ── History Execution & Core Synchronization ────────────── */
    async loadHistory() {
        try {
            const res  = await fetch(this.historyEndpoint);
            const logs = await res.json();

            const list = document.getElementById('hrc-recent-list');
            const clearBtn = document.getElementById('hrc-clear-btn');

            if (list) {
                list.innerHTML = '';

                // If history is empty, show Fallback UI
                if (!logs || !logs.length) {
                    if (clearBtn) clearBtn.style.display = 'none'; // Hide Clear button
                    list.innerHTML =
                        '<div class="text-center p-4 text-muted bg-light">' +
                            '<i class="la la-history mb-2 text-secondary" style="font-size: 28px;"></i>' +
                            '<p class="mb-0" style="font-size: 12px;">Your recent searches will appear here.</p>' +
                        '</div>';
                } else {
                    if (clearBtn) clearBtn.style.display = ''; // Show clear button
                    logs.forEach(function(log) {
                        const btn = document.createElement('button');
                        btn.className  = 'list-group-item list-group-item-action py-2 text-left';
                        btn.style.cssText = 'font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
                        btn.innerHTML = '<i class="la la-comment-alt mt-2" style="margin-right: 5px;"></i>' + hrChat._esc(log.question);
                        btn.onclick = function() { hrChat.ask(log.question); };
                        list.appendChild(btn);
                    });
                }
            }

            // Stop here so we don't try to loop over an empty array for the chat window
            if (!logs || !logs.length) return;

            const emptyEl = document.getElementById('hrc-empty');
            if (emptyEl) emptyEl.remove();

            logs.slice().reverse().forEach(function(log) {
                hrChat._appendMsg('user', log.question);
                hrChat._appendAi(log.answer, [], log.id);
            });

        } catch (e) {
            console.error("History configuration bypass structural load error:", e);
        }
    },

    /**
     * Isolated Sidebar update workflow to refresh recent sidebar listing on new chats
     */
    async refreshSidebarHistory() {
        try {
            const res = await fetch(this.historyEndpoint);
            const logs = await res.json();
            const list = document.getElementById('hrc-recent-list');
            const clearBtn = document.getElementById('hrc-clear-btn');

            if (list) {
                list.innerHTML = '';

                if (!logs || !logs.length) {
                    if (clearBtn) clearBtn.style.display = 'none';
                    list.innerHTML =
                        '<div class="text-center p-4 text-muted bg-light">' +
                            '<i class="la la-history mb-2 text-secondary" style="font-size: 28px;"></i>' +
                            '<p class="mb-0" style="font-size: 12px;">Your recent searches will appear here.</p>' +
                        '</div>';
                } else {
                    if (clearBtn) clearBtn.style.display = '';
                    logs.forEach(function(log) {
                        const btn = document.createElement('button');
                        btn.className  = 'list-group-item list-group-item-action py-2 text-left';
                        btn.style.cssText = 'font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
                        btn.innerHTML = '<i class="la la-comment-alt mt-2" style="margin-right: 5px;"></i>' + hrChat._esc(log.question);
                        btn.onclick = function() { hrChat.ask(log.question); };
                        list.appendChild(btn);
                    });
                }
            }
        } catch (e) {
            // Silently complete structural dynamic sidebar tasks
        }
    },

    _renderMarkdown(raw) {
        if (!raw) return '';

        // Step 1: Escape HTML to prevent any malicious script injection (XSS)
        const safeText = String(raw)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        // Step 2: Configure Marked to use GitHub Flavored Markdown
        // 'breaks: true' ensures standard newlines (\n) become <br> tags automatically
        marked.use({
            gfm: true,
            breaks: true
        });

        // Step 3: Parse and return the clean HTML
        return marked.parse(safeText);
    },
    _esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
};

document.addEventListener('DOMContentLoaded', function() {
    hrChat.loadHistory();
});
</script>
@endpush
