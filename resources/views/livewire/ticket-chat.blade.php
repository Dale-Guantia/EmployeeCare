<div class="card" wire:poll.5s style="height: calc(100vh - 250px); min-height: 500px;">
    <div class="card-header bg-primary text-white font-weight-bold">
        <i class="la la-comments"></i> Ticket Discussion
    </div>

    <div class="card-body" id="chat-box" style="overflow-y: auto; background-color: #f4f7f6;">
        @forelse($comments as $c)
            <div class="mb-3 d-flex flex-column {{ $c->user_id == backpack_user()->id ? 'align-items-end' : 'align-items-start' }}">
                <small class="text-muted mb-1">{{ $c->user->name }} • {{ $c->created_at->format('g:i A') }}</small>

                <div class="p-2 rounded shadow-sm {{ $c->user_id == backpack_user()->id ? 'bg-primary text-white' : 'bg-white border' }}"
                    style="max-width: 85%; word-wrap: break-word;">

                    @if($c->comment)
                        <div>{{ $c->comment }}</div>
                    @endif

                    <!-- Display Multiple Attachments -->
                    @if($c->attachment && is_array($c->attachment))
                        <div class="mt-1"> {{-- Removed border-top --}}
                            @foreach($c->attachment as $path)
                                <div class="py-1">
                                    <a href="{{ asset('storage/' . $path) }}" target="_blank"
                                    class="{{ $c->user_id == backpack_user()->id ? 'text-white' : 'text-primary' }} small d-block"
                                    style="text-decoration: underline;">
                                        <i class="la la-paperclip"></i> {{ basename($path) }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif
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
        @if($isResolved)
            <div class="alert alert-warning mb-2">
                <i class="la la-info-circle"></i>
                This ticket is resolved. Reopen it to continue the conversation.
            </div>
        @endif

        @error('comment')
            <div class="alert alert-warning py-2 px-3 mb-2">
                <i class="la la-exclamation-triangle"></i> {{ $message }}
            </div>
        @enderror

        @if ($attachments)
            @foreach($attachments as $index => $file)
                <div class="alert alert-info py-1 px-2 mb-1 small d-flex justify-content-between align-items-center">
                    <span><i class="la la-file"></i> {{ $file->getClientOriginalName() }}</span>

                    @unless($isResolved)
                        <button type="button" class="close" wire:click="removeUpload({{ $index }})">&times;</button>
                    @endunless
                </div>
            @endforeach
        @endif

        <div class="input-group">
            <div class="input-group-prepend">
                <label
                    class="btn btn-outline-secondary mb-0 {{ $isResolved ? 'disabled' : '' }}"
                    style="cursor: {{ $isResolved ? 'not-allowed' : 'pointer' }};"
                >
                    <i class="la la-paperclip"></i>
                    <input
                        type="file"
                        wire:model="attachments"
                        class="d-none"
                        multiple
                        {{ $isResolved ? 'disabled' : '' }}
                    >
                </label>
            </div>

            <input
                type="text"
                wire:model.defer="comment"
                wire:keydown.enter="{{ $isResolved ? '' : 'sendComment' }}"
                wire:loading.attr="disabled"
                class="form-control"
                placeholder="{{ $isResolved ? 'Ticket is resolved' : 'Write a reply...' }}"
                {{ $isResolved ? 'disabled' : '' }}
            >

            <div class="input-group-append">
                <button
                    class="btn btn-primary"
                    wire:click="{{ $isResolved ? '' : 'sendComment' }}"
                    wire:loading.attr="disabled"
                    {{ $isResolved ? 'disabled' : '' }}
                >
                    <i class="la la-paper-plane"></i>
                </button>
            </div>
        </div>

        @unless($isResolved)
            <div wire:loading wire:target="attachments" class="w-100 mt-1">
                <div class="progress" style="height: 5px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                </div>
                <small class="text-muted">Uploading file...</small>
            </div>
        @endunless
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
