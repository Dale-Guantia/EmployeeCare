<section class="card ec-reassignment-card" aria-label="Reassignment Rate" data-reassignment-card>
    <div class="card-body">
        <div class="ec-reassignment-main">
            <span class="ec-reassignment-icon" aria-hidden="true"><i class="la la-random"></i></span>
            <div>
                <h2 class="ec-reassignment-title">Reassignment Rate</h2>
                <div class="ec-reassignment-rate">{{ $rate }}%</div>
                <p class="ec-reassignment-note mb-0">
                    {{ $total ? 'Share of selected tickets with a reassignment request.' : 'No tickets in this selection.' }}
                </p>
            </div>
        </div>
        <dl class="ec-reassignment-counts">
            <div>
                <dt>Reassigned tickets</dt>
                <dd class="ec-reassignment-count">{{ $reassigned }}</dd>
            </div>
            <div>
                <dt>Total tickets</dt>
                <dd>{{ $total }}</dd>
            </div>
        </dl>
        <p class="ec-reassignment-scope mb-0">
            {{ $officeFiltersEnabled ? 'Based on the date range, department, and division selected above.' : 'Based on the selected date range and your permitted department.' }}
        </p>
    </div>
</section>
