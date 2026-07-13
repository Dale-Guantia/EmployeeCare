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

    .hrchat-bubble { max-width: 85%; word-wrap: break-word; }
    .hrchat-recent-item {
        font-size: 12px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .hrchat-not-grounded {
        display: inline-block;
        margin-top: 4px;
        padding: 4px 8px;
        font-size: 12px;
        color: #856404;
        background: #fff3cd;
        border: 1px solid #ffeeba;
        border-radius: 4px;
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
                    onclick="hrChat.clearHistory('Are you sure you want to permanently delete your search records? This action cannot be undone.')" style="font-size:11px; text-decoration: none; font-weight: 600;">
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
                <button class="btn btn-sm btn-outline-light" onclick="hrChat.clearView()"
                    aria-label="Clear the visible chat"
                    title="Clears the chat view only — your question history is still saved">
                    <i class="la la-eraser"></i> Clear view
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
<script src="https://cdn.jsdelivr.net/npm/marked@11.1.1/marked.min.js"
    integrity="sha384-zbcZAIxlvJtNE3Dp5nxLXdXtXyxwOdnILY1TDPVmKFhl4r4nSUG1r8bcFXGVa4Te"
    crossorigin="anonymous"></script>
<script src="{{ asset('js/hr-chat-core.js') }}"></script>
<script>
    var EMPTY_STATE_HTML = document.getElementById('hrc-empty')
        ? document.getElementById('hrc-empty').outerHTML
        : '';

    window.hrChat = HrChatCore.create({
        globalName: 'hrChat',
        ids: {
            thread: 'hrc-thread',
            input: 'hrc-input',
            sendBtn: 'hrc-send',
            emptyState: 'hrc-empty',
            recentList: 'hrc-recent-list',
            clearHistoryBtn: 'hrc-clear-btn',
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
            wrapUser: 'mb-3 d-flex flex-column align-items-end',
            wrapBot: 'mb-3 d-flex flex-column align-items-start',
            senderTag: 'small',
            senderClass: 'text-muted mb-1',
            bubbleUser: 'p-2 rounded shadow-sm bg-primary text-white hrchat-bubble',
            bubbleBot: 'p-2 rounded shadow-sm bg-white border chat-markdown hrchat-bubble',
            bubbleBotError: 'p-2 rounded shadow-sm bg-warning chat-markdown hrchat-bubble',
            sourcesWrap: 'mt-1',
            sourcePill: 'badge badge-light border mr-1',
            notGroundedBanner: 'hrchat-not-grounded',
            typingBubble: 'p-2 rounded bg-white border',
            feedbackWrap: 'mt-1',
            feedbackLabel: 'text-muted',
            feedbackBtnUp: 'btn btn-sm btn-outline-success ml-1',
            feedbackBtnDown: 'btn btn-sm btn-outline-danger ml-1',
            recentItem: 'list-group-item list-group-item-action py-2 text-left hrchat-recent-item',
        },
        typingHtml: '<span class="text-muted"><i class="la la-circle-notch la-spin"></i> %SEARCHING%</span>',
        strings: {
            assistantName: 'HRDO Assistant',
            searching: 'Searching policies…',
            helpfulPrompt: 'Helpful?',
            thumbsUpAria: 'Mark answer as helpful',
            thumbsDownAria: 'Mark answer as not helpful',
            thanksHelpful: '\u{1F44D} Thanks!',
            notedUnhelpful: '\u{1F44E} Noted!',
            notGrounded: 'Not from an official policy document — verify with HRDO.',
            connectionError: 'Connection error. Please try again or contact HRDO directly.',
            recentListEmptyHtml:
                '<div class="text-center p-4 text-muted bg-light">' +
                    '<i class="la la-history mb-2 text-secondary" style="font-size: 28px;"></i>' +
                    '<p class="mb-0" style="font-size: 12px;">Your recent searches will appear here.</p>' +
                '</div>',
        },
        onAfterAnswer: function () { hrChat.refreshSidebarHistory(); },
        onClearView: function () {
            var thread = document.getElementById('hrc-thread');
            if (thread && EMPTY_STATE_HTML) thread.insertAdjacentHTML('beforeend', EMPTY_STATE_HTML);
            var input = document.getElementById('hrc-input');
            if (input) input.focus();
        },
    });

    document.addEventListener('DOMContentLoaded', function () {
        hrChat.loadHistory();
    });
</script>
@endpush

