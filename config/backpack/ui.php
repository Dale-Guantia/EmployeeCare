<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Look & feel customizations
    |--------------------------------------------------------------------------
    |
    | Make it yours.
    |
    */

    // Date & Datetime Format Syntax: https://carbon.nesbot.com/docs/#api-localization
    'default_date_format'     => 'D MMM YYYY',
    'default_datetime_format' => 'D MMM YYYY, HH:mm',

    // Direction, according to language
    // (left-to-right vs right-to-left)
    'html_direction' => 'ltr',

    // ----
    // HEAD
    // ----

    // Project name. Shown in the window title.
    'project_name' => 'Employee Care',

    // When clicking on the admin panel's top-left logo/name,
    // where should the user be redirected?
    // The string below will be passed through the url() helper.
    // - default: '' (project root)
    // - alternative: 'admin' (the admin's dashboard)
    'home_link' => '',

    // Content of the HTML meta robots tag to prevent indexing and link following
    'meta_robots_content' => 'noindex, nofollow',

    // ---------
    // DASHBOARD
    // ---------

    // Show "Getting Started with Backpack" info block?
    'show_getting_started' => false,

    // ------
    // STYLES
    // ------

    // CSS files that are loaded in all pages, using Laravel's asset() helper
    'styles' => [
        // 'packages/backpack/base/css/bundle.css', // has primary color electric purple (backpack default)
 // has primary color blue
        'packages/backpack/base/css/custom.css',
        'css/backpack-theme.css',

        // Here's what's inside the bundle:
        // 'packages/@digitallyhappy/backstrap/css/style.min.css',
        // 'packages/animate.css/animate.min.css',
        // 'packages/noty/noty.css',

        // Load the fonts separately (so that you can replace them at will):
        'packages/line-awesome/css/line-awesome.min.css',

        // Example (the fonts above, loaded from CDN instead)
        // 'https://maxcdn.icons8.com/fonts/line-awesome/1.1/css/line-awesome-font-awesome.min.css',
        // 'https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic',

        // Example (load font-awesome instead of line-awesome):
        // 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.2/css/all.min.css',
    ],

    // Font stylesheets loaded directly; Basset cannot render extensionless Google Fonts URLs.
    'font_styles' => [
        'https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap',
    ],

    // CSS files that are loaded in all pages, using Laravel's mix() helper
    'mix_styles' => [ // file_path => manifest_directory_path
        // 'css/app.css' => '',
    ],

    // CSS files that are loaded in all pages, using Laravel's @vite() helper
    // Please note that support for Vite was added in Laravel 9.19. Earlier versions are not able to use this feature.
    'vite_styles' => [ // resource file_path
        // 'resources/css/app.css',
    ],

    // ------
    // HEADER
    // ------

    // Menu logo. You can replace this with an <img> tag if you have a logo.
    'project_logo' => '<img src="/assets/emp-care-logo-1.png" alt="Employee Care Logo" style="height: 30px;">',

    // Show / hide breadcrumbs on admin panel pages.
    'breadcrumbs' => true,

    // Header, body and sidebar classes are configured in theme-coreuiv4.php.

    // ------
    // FOOTER
    // ------

    // Footer classes are configured in theme-coreuiv4.php.

    // Developer or company name. Shown in footer.
    'developer_name' => '',

    // Developer website. Link in footer. Type false if you want to hide it.
    'developer_link' => '',

    // Show powered by Laravel Backpack in the footer? true/false
    'show_powered_by' => false,

    // -------
    // SCRIPTS
    // -------

    // JS files that are loaded in all pages, using Laravel's asset() helper
    'scripts' => [
        // Backstrap includes jQuery, Bootstrap, CoreUI, PNotify, Popper


        // examples (everything inside the bundle, loaded from CDN)
        // 'https://code.jquery.com/jquery-3.4.1.min.js',
        // 'https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js',
        // 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js',
        // 'https://unpkg.com/@coreui/coreui@2.1.16/dist/js/coreui.min.js',
        // 'https://cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js',
        // 'https://unpkg.com/sweetalert/dist/sweetalert.min.js',
        // 'https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.min.js'

        // examples (VueJS or React)
        // 'https://unpkg.com/vue@2.4.4/dist/vue.min.js',
        // 'https://unpkg.com/react@16/umd/react.production.min.js',
        // 'https://unpkg.com/react-dom@16/umd/react-dom.production.min.js',
    ],

    // JS files that are loaded in all pages, using Laravel's mix() helper
    'mix_scripts' => [ // file_path => manifest_directory_path
        // 'js/app.js' => '',
    ],

    // JS files that are loaded in all pages, using Laravel's @vite() helper
    'vite_scripts' => [ // resource file_path
        // 'resources/js/app.js',
    ],

    // Keep the application's custom views, with CoreUI v4 templates as fallback.
    'view_namespace' => 'vendor.backpack.base.',
    'view_namespace_fallback' => 'backpack.theme-coreuiv4::',
];
