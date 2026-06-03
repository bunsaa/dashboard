<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'places_api_key' => env('GOOGLE_PLACES_API_KEY'),
        'place_id' => env('GOOGLE_PLACE_ID'), // berlaku untuk SerpAPI maupun Google Places API
        'data_id' => env('GOOGLE_DATA_ID'),   // SerpAPI data_id (hex format dari Google Maps)
    ],

    // SerpAPI - scraper Google Reviews (free 100 req/bulan, tanpa CC)
    // Daftar di: https://serpapi.com/users/sign_up
    'serpapi' => [
        'key' => env('SERPAPI_KEY'),
    ],

    // Claude AI (Anthropic) - generate rekomendasi IT dari teks review
    // Daftar di: https://console.anthropic.com/
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    // Gemini AI (Google) - generate rekomendasi IT dari teks review
    // Daftar di: https://aistudio.google.com/app/apikey (gratis, tidak perlu CC)
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],

    // Groq AI - generate rekomendasi IT dari teks review (GRATIS, tanpa CC)
    // Daftar di: https://console.groq.com/ → API Keys
    'groq' => [
        'key' => env('GROQ_API_KEY'),
    ],

];
