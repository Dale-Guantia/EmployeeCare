<div class="row g-4 my-4 ec-survey-charts">
    @foreach($charts as $chart)
        <div class="{{ $loop->first ? 'col-xl-8' : 'col-xl-4' }}">
            <div class="card h-100">
                <div class="card-header"><div><strong>{{ $chart['title'] }}</strong><div class="ec-chart-subtitle">{{ $chart['unit'] ?? 'Responses' }} for the selected filters</div></div></div>
                <div class="card-body" tabindex="0" role="region" aria-label="{{ $chart['title'] }} chart area">
                    <div style="height: {{ max(280, count($chart['labels']) * 36 + 50) }}px; position: relative;" data-survey-chart>
                        <canvas role="img" aria-label="{{ $chart['title'] }}"></canvas>
                        <ul class="visually-hidden">
                            @foreach($chart['labels'] as $index => $label)
                                <li>{{ $label }}: {{ $chart['counts'][$index] }}</li>
                            @endforeach
                        </ul>
                        <p class="text-muted text-center pt-5" data-chart-empty hidden>No data to display.</p>
                        <script type="application/json" data-survey-chart-data>@json($chart)</script>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
