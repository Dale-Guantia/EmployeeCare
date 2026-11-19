    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    @if (config('backpack.ui.meta_robots_content'))<meta name="robots" content="{{ config('backpack.ui.meta_robots_content', 'noindex, nofollow') }}"> @endif

    <meta name="csrf-token" content="{{ csrf_token() }}" /> {{-- Encrypted CSRF token for Laravel, in order for Ajax requests to work --}}
    <title>{{ isset($title) ? $title.' :: '.config('backpack.ui.project_name') : config('backpack.ui.project_name') }}</title>

    @yield('before_styles')
    @stack('before_styles')

    @foreach (config('backpack.ui.font_styles', []) as $fontStylesheet)
        <link rel="stylesheet" href="{{ $fontStylesheet }}">
    @endforeach

    @include(backpack_view('inc.theme_styles'))
    @include(backpack_view('inc.styles'))

    <style>
        /* Ticket show/preview: prevent long values (department names, attachment
           filenames) from overflowing their table row instead of wrapping. */
        .ticket-show table.table td,
        .ticket-show table.table th {
            word-break: break-word;
            overflow-wrap: break-word;
        }

        @media (max-width: 576px) {
            /* Stack label above value per row so neither column has to fight
               the other for width on narrow screens. */
            .ticket-show table.table td,
            .ticket-show table.table th {
                display: block;
                width: 100% !important;
                white-space: normal;
            }

            .ticket-show table.table th {
                padding-bottom: 0;
            }

            .ticket-show table.table td {
                padding-top: 4px;
            }
        }
    </style>

    @yield('after_styles')
    @stack('after_styles')

    {{-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries --}}
    {{-- WARNING: Respond.js doesn't work if you view the page via file:// --}}
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
