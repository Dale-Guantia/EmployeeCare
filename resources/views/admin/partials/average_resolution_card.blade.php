<section class="card ec-reassignment-card ec-resolution-card {{ ($resolutionTone ?? '') === 'green' ? 'ec-resolution-card-green' : '' }}" aria-label="Average Resolution Time" data-average-resolution-card>
    <div class="card-body">
        <div class="ec-reassignment-main">
            <span class="ec-reassignment-icon" aria-hidden="true"><i class="la la-clock"></i></span>
            <div>
                <h2 class="ec-reassignment-title">Average Resolution Time</h2>
                <div class="ec-reassignment-rate">@include('admin.partials.resolution_average', ['formatted' => $kpis['avg_resolution_formatted']])</div>
                <p class="ec-reassignment-note mb-0">Average from ticket creation to resolution. {{ ($validResolutionTimesOnly ?? false) ? 'Excludes missing or invalid resolution times.' : 'Excludes missing resolution times.' }}</p>
            </div>
        </div>
        <dl class="ec-reassignment-counts">
            <div>
                <dt>Resolved tickets</dt>
                <dd class="ec-reassignment-count">{{ $kpis['resolved'] }}</dd>
            </div>
            <div>
                <dt>Total tickets</dt>
                <dd>{{ $kpis['total'] }}</dd>
            </div>
        </dl>
        <p class="ec-reassignment-scope mb-0">
            {{ $officeFiltersEnabled ? 'Based on the date range, department, and division selected above.' : 'Based on the selected date range and your permitted department.' }}
        </p>
    </div>
</section>
