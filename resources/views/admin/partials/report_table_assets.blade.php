@once
    @push('after_styles')
        @basset(public_path('css/report-tables.css'))
    @endpush
    @basset(public_path('public-assets/js/dataTables.responsive.min.js'))
    @basset(public_path('js/report-tables.js'))
@endonce
