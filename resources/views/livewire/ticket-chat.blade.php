<div class="card" wire:poll.5s style="height: calc(100vh - 250px); min-height: 500px;">
    <div class="card-header bg-primary text-white font-weight-bold">
        <i class="la la-comments"></i> Ticket Discussion
    </div>

    <!-- ID 'chat-box' is used by the JS script above -->
    <div class="card-body" id="chat-box" style="overflow-y: auto; background-color: #f4f7f6;">
        @forelse($comments as $c)
            <div class="mb-3 d-flex flex-column {{ $c->user_id == auth()->id() ? 'align-items-end' : 'align-items-start' }}">
                <small class="text-muted mb-1">{{ $c->user->name }} • {{ $c->created_at->format('g:i A') }}</small>
                <div class="p-2 rounded shadow-sm {{ $c->user_id == auth()->id() ? 'bg-primary text-white' : 'bg-white border' }}"
                     style="max-width: 85%; word-wrap: break-word;">
                    {{ $c->comment }}
                </div>
            </div>
        @empty
            <div class="text-center text-muted mt-5">
                <i class="la la-comment-slash d-block" style="font-size: 3rem;"></i>
                <p>No messages yet. Start the conversation!</p>
            </div>
        @endforelse
    </div>

    <div class="card-footer bg-white">
        <div class="input-group">
            <input type="text"
                wire:model.defer="comment"
                wire:keydown.enter="sendComment"
                wire:loading.attr="disabled"
                class="form-control"
                placeholder="Write a reply...">
            <div class="input-group-append">
                <button class="btn btn-primary" wire:click="sendComment">
                    <i class="la la-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

@push('after_scripts')
<script>
    // Function to move scroll to bottom
    function scrollToBottom() {
        const chatBox = document.getElementById('chat-box');
        if (chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    }

    // Run when page first loads
    document.addEventListener('DOMContentLoaded', () => {
        scrollToBottom();
    });

    // Run every time Livewire updates the DOM (after a message is sent)
    document.addEventListener('livewire:load', function () {
        window.livewire.on('commentSent', () => {
            setTimeout(() => { scrollToBottom(); }, 50);
        });

        // Alternative: Listen for any Livewire update
        Livewire.hook('message.processed', (message, component) => {
            scrollToBottom();
        });
    });
</script>
@endpush
