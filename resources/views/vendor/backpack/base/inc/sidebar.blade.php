@if (backpack_auth()->check())
<div class="{{ backpack_theme_config('classes.sidebar') }}" id="sidebar">
    <div class="sidebar-brand d-none d-md-flex">
        <a class="navbar-brand fs-6 text-decoration-none text-uppercase" href="{{ url(backpack_theme_config('home_link')) }}" title="{{ backpack_theme_config('project_name') }}">
            {!! backpack_theme_config('project_logo') !!}
        </a>
    </div>
    <ul class="sidebar-nav" data-coreui="navigation" data-simplebar>
        @include(backpack_view('inc.sidebar_content'))
    </ul>
</div>

@push('before_scripts')
<script>
    document.getElementById('sidebar').classList.toggle('hide', sessionStorage.getItem('sidebar-collapsed') === '1');
</script>
@endpush

@push('after_scripts')
<script>
    document.querySelector('.header-toggler').addEventListener('click', function () {
        sessionStorage.setItem('sidebar-collapsed', Number(document.getElementById('sidebar').classList.contains('hide')));
        window.crud?.table?.fixedHeader?.adjust();
    });
</script>
@endpush
@endif
