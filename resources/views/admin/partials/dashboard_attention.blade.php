<section class="card ec-dashboard-attention" id="dashboardAttention" aria-label="Tickets Needing Attention" data-dashboard-attention>
    <div class="card-header justify-content-between gap-2">
        <div><strong>Tickets Needing Attention</strong><div class="ec-chart-subtitle">Open tickets created in the selected period · Oldest first</div></div>
        <span class="ec-chart-total">{{ $attentionTickets->count() }} of {{ $dashboardMetrics['open'] }} open</span>
    </div>
    <div class="card-body ec-dashboard-attention-scroll" tabindex="0" aria-label="Open ticket worklist">
        <table class="table table-hover ec-report-table">
            <thead><tr><th scope="col">Ticket</th><th scope="col">Assigned to</th><th scope="col">Priority</th><th scope="col">Age</th><th scope="col">Due</th><th scope="col">Deadline state</th></tr></thead>
            <tbody>
            @forelse($attentionTickets as $row)
                @php($ticket = $row['ticket'])
                <tr>
                    <td><a class="ec-ticket-reference" href="{{ route('ticket.show', $ticket->id) }}">{{ $ticket->reference_id }}</a><div class="ec-attention-subject">{{ $row['subject'] }}</div></td>
                    <td>{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                    <td>@include('admin.partials.record_badge', ['record' => $ticket->priority, 'kind' => 'priority'])</td>
                    <td class="ec-table-number">{{ $row['age'] }}</td>
                    <td><time class="ec-attention-deadline" datetime="{{ $row['deadline']->toIso8601String() }}">{{ $row['deadline']->format('M j, Y · g:i A') }}</time></td>
                    <td><span class="ec-deadline-state ec-deadline-{{ $row['state'] }}">{{ ['overdue' => 'Overdue', 'due_soon' => 'Due within 24h', 'within_sla' => 'Within SLA'][$row['state']] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No open tickets in this selection.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
