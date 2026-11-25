<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scarlet Hospital Management System
    |--------------------------------------------------------------------------
    | Branding and hospital-wide defaults. Branch-level overrides live in the
    | settings table and are resolved by App\Domains\Settings\Services\SettingsService.
    */

    'name' => env('APP_NAME', 'Scarlet HMS'),
    'name_ar' => 'نظام سكارلت لإدارة المستشفيات',
    'legal_name' => 'Scarlet Care Medical Group',
    'tagline_ar' => 'رعاية رقمية متكاملة',
    'tagline_en' => 'Integrated digital care',
    'default_locale' => env('SCARLET_DEFAULT_LOCALE', 'ar'),
    'supported_locales' => ['ar', 'en'],
    'currency' => env('SCARLET_CURRENCY', 'SDG'),
    'currency_symbol' => 'ج.س',
    'mrn_prefix' => env('SCARLET_MRN_PREFIX', 'SC'),
    'timezone' => env('APP_DISPLAY_TIMEZONE', 'Africa/Khartoum'),
    'seed_demo_data' => (bool) env('SCARLET_SEED_DEMO_DATA', false),

    'demo_credentials' => [
        'admin' => ['username' => 'admin', 'password' => 'Scarlet@2026'],
        'doctor' => ['username' => 'doctor', 'password' => 'Scarlet@2026'],
        'reception' => ['username' => 'reception', 'password' => 'Scarlet@2026'],
    ],

    'brand' => [
        'primary' => '#8A1538',
        'primary_dark' => '#5E0E26',
        'accent' => '#E11D48',
        'surface' => '#FFF8F9',
    ],
];
