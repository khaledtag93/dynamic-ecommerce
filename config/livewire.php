<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Livewire 3 compatibility bridge
    |--------------------------------------------------------------------------
    |
    | Dynamic's existing components intentionally remain under App\Http\Livewire
    | during the framework-security upgrade. Keeping the established namespace
    | avoids a broad file move while preserving existing component aliases.
    |
    */
    'class_namespace' => 'App\\Http\\Livewire',
    'view_path' => resource_path('views/livewire'),
    'layout' => 'layouts.app',

    /*
    | Preserve Livewire 2 model-binding behavior during the upgrade pass.
    | This can be revisited after QAS acceptance of the Laravel 12 baseline.
    */
    'legacy_model_binding' => true,
];
