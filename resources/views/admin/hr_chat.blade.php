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
    .hrc-conv-item { cursor: pointer; }
    .hrc-conv-item:hover { background: #f5f7fa; }
    .hrc-conv-item.active { background: #eef4ff; }
    .hrc-conv-item.active:hover { background: #eef4ff; }
    .hrc-conv-item:focus { outline: 2px solid #375DA7; outline-offset: -2px; }
    .hrc-conv-title {
        max-width: calc(100% - 28px);
        font-size: 12px;
        color: #212529;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }
    /* SweetAlert's .swal-text defaults to text-align:left; centered here for
       the "Clear All" confirm only (scoped via className: 'hrc-confirm-modal'
       passed to swal() in hr-chat-core.js), leaving other swal() confirms on
       this page (e.g. delete-conversation) unaffected. */
    .hrc-confirm-modal .swal-text {
        text-align: center;
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

        <div class="card mt-3" id="hrc-conversations-card">
            <div class="card-body p-2">
                <button class="btn btn-primary btn-block btn-sm mb-2" id="hrc-new-chat">
                    <i class="la la-plus"></i> New chat
                </button>

                <div id="hrc-starred-group" style="display:none;">
                    <div class="text-muted small px-1 mb-1">Starred</div>
                    <div class="list-group list-group-flush" id="hrc-starred-list"></div>
                </div>

                <div class="d-flex justify-content-between align-items-center px-1 mb-1 mt-2">
                    <span class="text-muted small">Recents</span>
                    <button class="btn btn-sm btn-link p-0 text-danger" id="hrc-clear-btn"
                        onclick="hrChat.clearHistory('Are you sure you want to permanently delete all your conversations? This action cannot be undone.')"
                        style="font-size:11px; text-decoration: none; font-weight: 600;">
                        <i class="la la-trash"></i> Clear All
                    </button>
                </div>
                <div class="list-group list-group-flush" id="hrc-recent-list">
                    {{-- Conversation rows injected via JS --}}
                </div>
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

{{-- ── Rename conversation modal ────────────────────────────── --}}
<div class="modal fade" id="hrc-rename-modal" tabindex="-1" role="dialog"
     aria-labelledby="hrc-rename-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="hrc-rename-modal-title">
                    <i class="la la-edit"></i> Rename conversation
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <label for="hrc-rename-input" class="small text-muted mb-1">
                    Conversation name
                </label>
                <input type="text" class="form-control" id="hrc-rename-input"
                       maxlength="120" autocomplete="off" placeholder="Enter a name…">
                <div class="invalid-feedback" id="hrc-rename-feedback">
                    Please enter a name.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="hrc-rename-save">
                    <i class="la la-save"></i> Save
                </button>
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
    // Bootstrap's modal backdrop is appended straight to <body>, but the
    // modal element itself stays wherever it was written in the DOM. Left
    // inside Backpack's sidebar layout (which uses CSS transforms for the
    // collapse animation), that ancestor becomes a new containing block for
    // position:fixed, so the dialog never gets positioned/sized against the
    // viewport — the backdrop still covers the whole screen, but the dialog
    // itself is invisible/unusable. Same fix already used for the ticket
    // reassignment modals in reassignment_widget.blade.php.
    (function () {
        var modal = document.getElementById('hrc-rename-modal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    })();

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
        },
        endpoints: {
            ask: @json(route('hr.chat.ask')),
            feedback: @json(route('hr.chat.feedback')),
            history: @json(route('hr.chat.history')),
            clearHistory: @json(route('hr.chat.clear_history')),
            conversations: @json(route('hr.chat.conversations')),
            conversationMessages: @json(route('hr.chat.conversation.messages', ['id' => ':id'])),
            updateConversation: @json(route('hr.chat.conversation.update', ['id' => ':id'])),
            deleteConversation: @json(route('hr.chat.conversation.delete', ['id' => ':id'])),
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
            copyBtn: 'btn btn-lg btn-outline-primary py-0 px-1 ml-1',
            sourcesWrap: 'mt-1',
            sourcePill: 'badge badge-light border mr-1',
            notGroundedBanner: 'hrchat-not-grounded',
            typingBubble: 'p-2 rounded bg-white border',
            feedbackWrap: 'mt-1 d-flex align-items-center',
            feedbackLabel: 'text-muted',
            feedbackBtnUp: 'btn btn-sm btn-outline-success ml-1',
            feedbackBtnDown: 'btn btn-sm btn-outline-danger ml-1',
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
        },
        onAfterAnswer: function () { refreshConversationList(); },
        onConversationChanged: function () { refreshConversationList(); },
        onClearView: function () {
            var thread = document.getElementById('hrc-thread');
            if (thread && EMPTY_STATE_HTML) thread.insertAdjacentHTML('beforeend', EMPTY_STATE_HTML);
            var input = document.getElementById('hrc-input');
            if (input) input.focus();
        },
    });

    // ── Conversation sidebar ─────────────────────────────────────────
    function escText(str) { return HrChatCore.esc(str); }

    function confirmAction(message) {
        if (typeof window.swal === 'function') {
            return window.swal({
                title: 'Warning!',
                text: message,
                icon: 'warning',
                buttons: ['Cancel', 'Yes, delete it'],
                dangerMode: true,
            }).then(function (confirmed) { return !!confirmed; });
        }
        return Promise.resolve(window.confirm(message));
    }

    function renderConversationRow(conv) {
        var div = document.createElement('div');
        div.className = 'list-group-item d-flex align-items-center justify-content-between px-2 py-2 hrc-conv-item'
            + (conv.id === hrChat.getConversation() ? ' active' : '');
        div.setAttribute('data-id', conv.id);
        div.setAttribute('data-title', conv.title || 'New conversation');
        div.setAttribute('role', 'button');
        div.setAttribute('tabindex', '0');

        // Plain text, not a button — the whole row (handled by the delegated
        // listeners below) is the click target, not just the title.
        var titleEl = document.createElement('span');
        titleEl.className = 'hrc-conv-title';
        titleEl.title = conv.title || 'New conversation';
        titleEl.textContent = conv.title || 'New conversation';

        var dropdownWrap = document.createElement('div');
        dropdownWrap.className = 'dropdown';
        dropdownWrap.innerHTML =
            '<button class="btn btn-sm btn-link text-muted p-0 px-1" data-toggle="dropdown" aria-label="Options">' +
                '<i class="la la-ellipsis-v"></i>' +
            '</button>' +
            '<div class="dropdown-menu dropdown-menu-right">' +
                '<a class="dropdown-item hrc-conv-star" href="#"><i class="la la-star"></i> ' + (conv.is_starred ? 'Unstar' : 'Star') + '</a>' +
                '<a class="dropdown-item hrc-conv-rename" href="#"><i class="la la-edit"></i> Rename</a>' +
                '<a class="dropdown-item text-danger hrc-conv-delete" href="#"><i class="la la-trash"></i> Delete</a>' +
            '</div>';

        dropdownWrap.querySelector('.hrc-conv-star').onclick = function (e) {
            e.preventDefault();
            e.stopPropagation();
            fetch(hrChat._urlFor(hrChat.cfg.endpoints.updateConversation, conv.id), {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': hrChat.cfg.csrf },
                body: JSON.stringify({ is_starred: !conv.is_starred }),
            }).then(function () { refreshConversationList(); });
        };

        dropdownWrap.querySelector('.hrc-conv-rename').onclick = function (e) {
            e.preventDefault();
            e.stopPropagation();
            hrcOpenRenameModal(conv.id, conv.title);
        };

        dropdownWrap.querySelector('.hrc-conv-delete').onclick = function (e) {
            e.preventDefault();
            e.stopPropagation();
            confirmAction('Delete this conversation? This cannot be undone.').then(function (confirmed) {
                if (!confirmed) return;
                fetch(hrChat._urlFor(hrChat.cfg.endpoints.deleteConversation, conv.id), {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': hrChat.cfg.csrf },
                }).then(function () {
                    if (hrChat.getConversation() === conv.id) {
                        hrChat.startNewConversation();
                    }
                    refreshConversationList();
                });
            });
        };

        // No handler needed on the toggle button itself: Bootstrap's
        // dropdown plugin opens it via a delegated listener on `document`,
        // so stopping propagation here would swallow the click before it
        // ever reaches Bootstrap. The row-open delegated listener already
        // ignores anything inside .dropdown (including this toggle), so
        // clicking it never opens the conversation either.

        div.appendChild(titleEl);
        div.appendChild(dropdownWrap);
        return div;
    }

    // Opens a conversation unless the click/keypress originated inside the
    // kebab dropdown (toggle button or one of its menu items).
    function hrcHandleRowActivate(target) {
        if (target.closest('.dropdown')) return;
        var row = target.closest('.hrc-conv-item');
        if (!row) return;
        // data-id is a string; conv.id from the API is a number — the
        // highlight check (conv.id === hrChat.getConversation()) is a
        // strict comparison, so this must be numeric or it never matches.
        var id = parseInt(row.getAttribute('data-id'), 10);
        hrChat.loadConversation(id).then(function () { renderSidebar(); });
    }

    ['hrc-recent-list', 'hrc-starred-list'].forEach(function (listId) {
        var list = document.getElementById(listId);
        list.addEventListener('click', function (e) { hrcHandleRowActivate(e.target); });
        list.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            if (e.target.closest('.dropdown')) return;
            if (!e.target.closest('.hrc-conv-item')) return;
            e.preventDefault();
            hrcHandleRowActivate(e.target);
        });
    });

    // ── Rename modal ─────────────────────────────────────────────────
    var hrcRenameId = null;

    function hrcOpenRenameModal(id, currentTitle) {
        hrcRenameId = id;
        var input = document.getElementById('hrc-rename-input');
        input.value = currentTitle || '';
        input.classList.remove('is-invalid');
        $('#hrc-rename-modal').modal('show');
    }

    $('#hrc-rename-modal').on('shown.bs.modal', function () {
        var input = document.getElementById('hrc-rename-input');
        input.focus();
        input.select();
    });

    function hrcSubmitRename() {
        var input = document.getElementById('hrc-rename-input');
        var feedback = document.getElementById('hrc-rename-feedback');
        var title = input.value.trim();

        if (!title) {
            input.classList.add('is-invalid');
            feedback.textContent = 'Please enter a name.';
            input.focus();
            return;
        }

        var saveBtn = document.getElementById('hrc-rename-save');
        saveBtn.disabled = true;

        fetch(hrChat._urlFor(hrChat.cfg.endpoints.updateConversation, hrcRenameId), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': hrChat.cfg.csrf },
            body: JSON.stringify({ title: title }),
        })
            .then(function (res) {
                if (!res.ok) throw new Error('Rename failed');
                return res.json();
            })
            .then(function () {
                saveBtn.disabled = false;
                $('#hrc-rename-modal').modal('hide');
                refreshConversationList();
            })
            ['catch'](function () {
                saveBtn.disabled = false;
                input.classList.add('is-invalid');
                feedback.textContent = 'Could not save the name. Please try again.';
            });
    }

    document.getElementById('hrc-rename-save').addEventListener('click', hrcSubmitRename);
    document.getElementById('hrc-rename-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); hrcSubmitRename(); }
    });
    document.getElementById('hrc-rename-input').addEventListener('input', function () {
        this.classList.remove('is-invalid');
    });

    function renderSidebar(conversations) {
        if (!conversations) {
            return refreshConversationList();
        }

        var starredGroup = document.getElementById('hrc-starred-group');
        var starredList = document.getElementById('hrc-starred-list');
        var recentList = document.getElementById('hrc-recent-list');

        var starred = conversations.filter(function (c) { return c.is_starred; });
        var recents = conversations.filter(function (c) { return !c.is_starred; });

        starredList.innerHTML = '';
        recentList.innerHTML = '';

        if (starred.length) {
            starredGroup.style.display = '';
            starred.forEach(function (c) { starredList.appendChild(renderConversationRow(c)); });
        } else {
            starredGroup.style.display = 'none';
        }

        if (recents.length) {
            recents.forEach(function (c) { recentList.appendChild(renderConversationRow(c)); });
        } else {
            recentList.innerHTML = '<div class="text-center p-4 text-muted bg-light">' +
                '<i class="la la-history mb-2 text-secondary" style="font-size: 28px;"></i>' +
                '<p class="mb-0" style="font-size: 12px;">Your conversations will appear here.</p>' +
                '</div>';
        }
    }

    function refreshConversationList() {
        return fetch(hrChat.cfg.endpoints.conversations)
            .then(function (res) { return res.json(); })
            .then(function (conversations) { renderSidebar(conversations); });
    }

    document.getElementById('hrc-new-chat').addEventListener('click', function () {
        hrChat.startNewConversation();
        renderSidebar();
    });

    document.addEventListener('DOMContentLoaded', function () {
        refreshConversationList();
    });
</script>
@endpush

