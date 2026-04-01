<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'Switch & Save CRM') }}</title>
        
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
        <link rel="apple-touch-icon" href="/icons/icon-192x192.svg">
        <link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-192x192.svg">
        <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-192x192.svg">
        <link rel="apple-touch-icon" sizes="167x167" href="/icons/icon-192x192.svg">
        
        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="/icons/icon-72x72.svg">
        <link rel="shortcut icon" href="/favicon.ico">
        
        <!-- Microsoft Tiles -->
        <meta name="msapplication-TileColor" content="#2563eb">
        <meta name="msapplication-TileImage" content="/icons/icon-192x192.svg">
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
        <div id="app"></div>
    </body>
</html>
