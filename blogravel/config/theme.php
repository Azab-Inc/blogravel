<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Theme Name
    |--------------------------------------------------------------------------
    |
    | The name of the base (fallback) theme. Components in this theme are used
    | when the active theme does not provide its own version.
    |
    */

    'base' => 'base',

    /*
    |--------------------------------------------------------------------------
    | Themes Directory
    |--------------------------------------------------------------------------
    |
    | The directory where themes are stored, relative to the application root.
    | Each theme should contain a theme.json file and a components directory.
    |
    */

    'themes_dir' => 'resources/themes',

    /*
    |--------------------------------------------------------------------------
    | Default Active Theme
    |--------------------------------------------------------------------------
    |
    | The default theme used when no tenant-specific setting is configured.
    | This should match the name of a directory in the themes directory.
    |
    */

    'default' => 'base',

];
