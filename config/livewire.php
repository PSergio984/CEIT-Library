<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | The view that will be used as the layout when rendering a component via
    | Route::get('/some-endpoint', SomeComponent::class);. In this case the
    | layout will be 'layouts.app'.
    |
    */
    'layout' => 'layouts.app',

    /*
    |--------------------------------------------------------------------------
    | Pagination Theme
    |--------------------------------------------------------------------------
    |
    | When paginating results, Livewire will use this theme to render
    | pagination views. Of course, you can customize these views
    | based on your application's needs.
    |
    */
    'pagination_theme' => 'tailwind',

    /*
    |--------------------------------------------------------------------------
    | Temporary File Uploads
    |--------------------------------------------------------------------------
    |
    | Livewire handles file uploads by storing uploads in a temporary directory
    | before the file is validated and stored permanently. All file uploads
    | are directed to a global endpoint for temporary storage. The configuration
    | below can be used to customize the temporary storage.
    |
    */
    'temporary_file_upload' => [
        'disk' => null,        // Example: 'local', 's3'              Default: 'default'
        'rules' => null,       // Example: ['file', 'mimes:png,jpg']  Default: ['required', 'file', 'max:12288'] (12MB)
        'directory' => null,   // Example: 'tmp'                      Default  'livewire-tmp'
        'middleware' => null,  // Example: 'throttle:5,1'             Default: 'throttle:60,1'
        'preview_mimes' => [   // Supported file types for temporary pre-signed file URLs...
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5, // Max duration (in minutes) before an upload gets invalidated...
    ],

    /*
    |--------------------------------------------------------------------------
    | Render On Redirect
    |--------------------------------------------------------------------------
    |
    | This value determines if Livewire will render before it's redirected
    | or not. Setting this to "false" (default) will mean the render method
    | will NOT be called before a redirect. However, if you set this to
    | "true", it will be called. In most cases, you should leave this as "false".
    |
    */
    'render_on_redirect' => false,

    /*
    |--------------------------------------------------------------------------
    | Eloquent Model Binding
    |--------------------------------------------------------------------------
    |
    | Previous versions of Livewire supported binding Eloquent model
    | properties directly to component properties. However, this
    | behavior has been deemed too "magical" and has been removed.
    | Use the "locked" property modifier to achieve similar results.
    |
    */
    'legacy_model_binding' => false,

    /*
    |--------------------------------------------------------------------------
    | Auto-inject Frontend Assets
    |--------------------------------------------------------------------------
    |
    | By default, Livewire will automatically inject its JavaScript and CSS into the <head>
    | and before the closing </body> tag of your application's base layout.
    | By setting this configuration option to "false", Livewire won't automatically
    | inject these assets, but you can manually include them in your layout file:
    |
    | In your <head>:
    | @livewireStyles
    |
    | Before closing </body>:
    | @livewireScripts
    |
    */
    'inject_assets' => true,

    /*
    |--------------------------------------------------------------------------
    | Navigate (SPA mode)
    |--------------------------------------------------------------------------
    |
    | By default, Livewire will walk through the returned HTML looking for
    | Alpine.js x-data expressions, and additionally load any Alpine.js
    | plugins referenced by x-data. You may wish to disable this behavior
    | by setting this value to "false".
    |
    */
    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#2299dd',
        'auto_scroll' => true,
    ],
];
