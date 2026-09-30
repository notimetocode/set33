<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | Public site URLs for this locale have no prefix (/, /privacy, …).
    | Other locales are served under /{locale}/….
    |
    */

    'default' => env('APP_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Available Locales
    |--------------------------------------------------------------------------
    |
    | Supported language codes. Add a code here and provide matching
    | lang/{code}/*.php (public) and resources/js/app/i18n/locales/{code}.json (ЛК).
    |
    */

    'available' => [
        'en',
        'ru',
    ],

    /*
    |--------------------------------------------------------------------------
    | Locale Labels
    |--------------------------------------------------------------------------
    |
    | Native names shown in the language switcher.
    |
    */

    'labels' => [
        'en' => 'English',
        'ru' => 'Русский',
    ],

    /*
    |--------------------------------------------------------------------------
    | BCP 47 Tags (dates / Intl)
    |--------------------------------------------------------------------------
    */

    'intl' => [
        'en' => 'en-US',
        'ru' => 'ru-RU',
    ],

];
