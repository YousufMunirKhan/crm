{{--
    Shared PWA head tags.

    Used by both the Vue SPA shell and the Filament panel so a single installed
    home-screen app covers both surfaces. The manifest scope is "/", so
    navigating from the SPA into /admin stays inside the standalone window.
--}}
<meta name="theme-color" content="#2563eb">
<meta name="description" content="Customer Relationship Management System">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="application-name" content="CRM">
<meta name="mobile-web-app-capable" content="yes">
<meta name="format-detection" content="telephone=no">

<link rel="manifest" href="/manifest.json">

<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="CRM">

{{-- iOS ignores SVG for apple-touch-icon; these must stay PNG. --}}
<link rel="apple-touch-icon" href="/icons/icon-180x180.png?v=2">
<link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-152x152.png?v=2">
<link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-180x180.png?v=2">

<link rel="icon" type="image/png" sizes="96x96" href="/icons/icon-96x96.png?v=2">
<link rel="shortcut icon" href="/favicon.ico?v=2">

<meta name="msapplication-TileColor" content="#2563eb">
<meta name="msapplication-TileImage" content="/icons/icon-192x192.png?v=2">
