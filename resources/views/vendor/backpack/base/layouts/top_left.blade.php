<!DOCTYPE html>

<html lang="{{ app()->getLocale() }}" dir="{{ backpack_theme_config('html_direction') }}">

<head>
  @include(backpack_view('inc.head'))
  @livewireStyles
</head>

<body class="{{ backpack_theme_config('classes.body') }}">

  @include(backpack_view('inc.sidebar'))

  <div class="wrapper d-flex flex-column min-vh-100 bg-light">

    @include(backpack_view('inc.main_header'))

    <div class="app-body flex-grow-1 px-2">

    <main class="main pt-2">

       @yield('before_breadcrumbs_widgets')

       @includeWhen(isset($breadcrumbs), backpack_view('inc.breadcrumbs'))

       @yield('after_breadcrumbs_widgets')

       @yield('header')

        <div class="container-fluid animated fadeIn">

          @yield('before_content_widgets')

          @yield('content')

          @yield('after_content_widgets')

        </div>

    </main>

  </div>{{-- ./app-body --}}

  <footer class="{{ backpack_theme_config('classes.footer') }}">
    @include(backpack_view('inc.footer'))
  </footer>
  </div>{{-- ./wrapper --}}

  @yield('before_scripts')
  @stack('before_scripts')

  @include(backpack_view('inc.scripts'))
  @include(backpack_view('inc.theme_scripts'))
  {{-- Keep Livewire on the current origin, including installations in a subdirectory. --}}
  @php
    $baseUrl = request()->getBaseUrl();
    $updateUri = app('livewire')->getUpdateUri();
    $livewireScriptPath = app(\Livewire\Mechanisms\FrontendAssets\FrontendAssets::class)->javaScriptRoute->uri();
    $livewireScripts = \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(['url' => $baseUrl . '/' . ltrim($livewireScriptPath, '/')]);
    // Keep update requests under the same application mount as the page and assets.
    if ($baseUrl !== '' && !Str::startsWith($updateUri, $baseUrl . '/')) {
        $livewireScripts = str_replace('data-update-uri="' . e($updateUri) . '"',
            'data-update-uri="' . e($baseUrl . $updateUri) . '"', $livewireScripts);
    }
  @endphp
  {!! $livewireScripts !!}

  @yield('after_scripts')
  @stack('after_scripts')
</body>
</html>
