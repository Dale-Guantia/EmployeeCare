    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    @if (config('backpack.base.meta_robots_content'))<meta name="robots" content="{{ config('backpack.base.meta_robots_content', 'noindex, nofollow') }}"> @endif

    <meta name="csrf-token" content="{{ csrf_token() }}" /> {{-- Encrypted CSRF token for Laravel, in order for Ajax requests to work --}}
    <title>{{ isset($title) ? $title.' :: '.config('backpack.base.project_name') : config('backpack.base.project_name') }}</title>

    @yield('before_styles')
    @stack('before_styles')

    @foreach(config('backpack.base.styles', []) as $path)
        <link rel="stylesheet" type="text/css" href="{{ asset($path).'?v='.config('backpack.base.cachebusting_string') }}">
    @endforeach

    @foreach(config('backpack.base.mix_styles', []) as $path => $manifest)
        <link rel="stylesheet" type="text/css" href="{{ mix($path, $manifest) }}">
    @endforeach

    @if(!empty(config('backpack.base.vite_styles', [])))
        @vite(config('backpack.base.vite_styles', []))
    @endif

    <style>
        .app-header {
            position: fixed !important;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1050;
        }

        .app-body {
            margin-top: 55px;
        }

        /* keep sidebar below the fixed header */
        .sidebar {
            top: 55px !important;
            height: calc(100vh - 56px) !important;
        }

        /* mobile fixes */
        @media (max-width: 991.98px) {
            .app-header {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                width: 100% !important;
                z-index: 1050 !important;
            }

            .app-body {
                margin-top: 55px !important;
            }

            .sidebar {
                top: 55px !important;
                height: calc(100vh - 55px) !important;
                z-index: 1040 !important;
            }

            .main {
                padding-top: 0 !important;
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
