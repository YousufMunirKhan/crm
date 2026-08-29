<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Switch & Save CRM') }}</title>
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet">

        <!-- PWA Meta Tags -->
        <meta name="theme-color" content="#2563eb">
        <meta name="description" content="Customer Relationship Management System">
        
        <!-- PWA Manifest -->
        <link rel="manifest" href="/manifest.json">
        
        <!-- Apple PWA Meta Tags -->
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="CRM">
        
        <!-- Apple Touch Icons -->
        <link rel="apple-touch-icon" href="/icons/icon-180x180.png">
        <link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-152x152.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-180x180.png">
        <link rel="apple-touch-icon" sizes="167x167" href="/icons/icon-180x180.png">
        
        <!-- Favicon -->
        <link rel="icon" type="image/png" sizes="96x96" href="/icons/icon-96x96.png">
        <link rel="icon" type="image/svg+xml" href="/icons/icon-72x72.svg">
        <link rel="shortcut icon" href="/favicon.ico">
        
        <!-- Microsoft Tiles -->
        <meta name="msapplication-TileColor" content="#2563eb">
        <meta name="msapplication-TileImage" content="/icons/icon-192x192.png">
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
        <div id="app"></div>
    </body>
</html>
