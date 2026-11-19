<section class="card ec-staff-leaderboard" aria-label="Staff Leaderboard" data-staff-leaderboard>
    <div class="card-header justify-content-between gap-2">
        <div>
            <strong><i class="la la-trophy text-warning me-1" aria-hidden="true"></i>Staff Leaderboard</strong>
            <div class="ec-leaderboard-subtitle">Top 10 by total tickets resolved</div>
        </div>
        <span class="ec-leaderboard-scope">Selected filters</span>
    </div>
    <div class="card-body">
        @if($staffLeaderboard->isEmpty())
            <div class="ec-leaderboard-empty">
                <i class="la la-trophy" aria-hidden="true"></i>
                <strong>No resolved tickets yet</strong>
                <span>No completed resolutions for the selected filters.</span>
            </div>
        @else
            <div class="ec-leaderboard-scroll" tabindex="0" aria-label="Staff rankings">
                <table class="table table-hover w-100 ec-report-table ec-leaderboard-table">
                    <caption class="visually-hidden">Staff ranked by resolved tickets; ties use on-time counts, then average resolution time.</caption>
                    <thead><tr><th scope="col">Staff</th><th scope="col">Resolved</th><th scope="col">Avg. time</th></tr></thead>
                    <tbody>
                    @foreach($staffLeaderboard as $row)
                        <tr>
                            <td>
                                <div class="ec-leaderboard-person">
                                    <span class="ec-leaderboard-rank {{ $loop->index < 3 ? 'ec-leaderboard-rank-' . $loop->iteration : '' }}" aria-label="Rank {{ $loop->iteration }}">{{ $loop->iteration }}</span>
                                    <span>{{ $row['name'] }}</span>
                                </div>
                            </td>
                            <td>
                                <strong class="ec-leaderboard-total">{{ $row['resolved_count'] }}</strong>
                                <div class="ec-leaderboard-statuses">
                                    <span class="ec-leaderboard-on-time">{{ $row['on_time_count'] }} on time</span>
                                    <span class="ec-leaderboard-overdue">{{ $row['overdue_count'] }} overdue</span>
                                </div>
                            </td>
                            <td class="ec-leaderboard-average">{{ $row['avg_resolution_formatted'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="ec-leaderboard-footnote mb-0">Uses ticket creation dates and the exact 72-hour SLA. Only completed resolutions with recorded staff and valid times are included.</p>
    </div>
</section>
