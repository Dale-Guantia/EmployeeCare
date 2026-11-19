<table id="latestTicketsTable" class="table table-hover w-100 ec-report-table" data-report-table data-report-order-column="6" aria-label="Latest Tickets">
    <caption class="visually-hidden">Up to 10 most recent tickets matching your date range and office access.</caption>
    <thead>
        <tr>
            <th class="control report-details-cell"><span class="visually-hidden">Details</span></th>
            <th data-priority="1">Reference</th>
            <th data-priority="6">Created by</th>
            <th data-priority="1">Subject</th>
            <th data-priority="2">Status</th>
            <th data-priority="4">Priority</th>
            <th data-priority="5">Created</th>
        </tr>
    </thead>
    <tbody>
        @foreach($latestTickets as $ticket)
            @php
                $standardSubject = $ticket->issue?->issue_description;
                $customSubject = $ticket->custom_issue;
                $subject = $ticket->is_custom_issue
                    ? (filled($customSubject) ? $customSubject : 'Custom request')
                    : (filled($standardSubject) ? $standardSubject : (filled($customSubject) ? $customSubject : 'Issue unavailable'));
                $preview = \Illuminate\Support\Str::squish($ticket->message ?? '');
            @endphp
            <tr>
                <td class="report-details-cell">
                    <button type="button" class="btn btn-sm btn-outline-secondary report-details-toggle"
                        aria-label="Show additional details for {{ $ticket->reference_id ?? 'ticket' }}" aria-expanded="false">+</button>
                </td>
                <td><a class="ec-ticket-reference" href="{{ route('ticket.show', $ticket->id) }}">{{ $ticket->reference_id ?? 'View ticket' }}</a></td>
                <td>{{ $ticket->user?->name ?? 'User unavailable' }}</td>
                <td class="ec-ticket-subject" data-search="{{ $subject . ' ' . $preview }}" data-order="{{ $subject }}">
                    <div class="ec-ticket-subject-label">{{ $subject }}</div>
                    @if($ticket->is_custom_issue)<span class="ec-custom-label">Custom</span>@endif
                    @if($preview !== '')<div class="ec-ticket-message">{{ \Illuminate\Support\Str::limit($preview, 140) }}</div>@endif
                </td>
                <td>@include('admin.partials.record_badge', ['record' => $ticket->status, 'kind' => 'status'])</td>
                <td>@include('admin.partials.record_badge', ['record' => $ticket->priority, 'kind' => 'priority'])</td>
                <td data-order="{{ $ticket->created_at?->timestamp ?? 0 }}"><time datetime="{{ $ticket->created_at?->toIso8601String() }}">{{ $ticket->created_at?->format('M j, Y · g:i A') ?? '—' }}</time></td>
            </tr>
        @endforeach
    </tbody>
</table>
