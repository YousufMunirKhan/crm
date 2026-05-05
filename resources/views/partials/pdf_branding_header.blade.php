{{-- Expects: $logoUrl (nullable string from settings), $settings (array) --}}
<div class="top-bar"></div>
<div class="logo-section page-break-avoid">
    @php
        $logoPath = null;
        if ($logoUrl ?? null) {
            $cleanUrl = preg_replace('#^/storage/|^storage/#', '', trim($logoUrl, '/'));
            $storagePath = storage_path('app/public/' . $cleanUrl);
            $publicPath = public_path('storage/' . $cleanUrl);
            if (file_exists($storagePath)) {
                $logoPath = str_replace('\\', '/', realpath($storagePath));
            } elseif (file_exists($publicPath)) {
                $logoPath = str_replace('\\', '/', realpath($publicPath));
            } elseif (file_exists(public_path($logoUrl))) {
                $logoPath = str_replace('\\', '/', realpath(public_path($logoUrl)));
            } else {
                $pubPath = public_path(ltrim($logoUrl, '/'));
                if (file_exists($pubPath)) {
                    $logoPath = str_replace('\\', '/', realpath($pubPath));
                }
            }
        }
    @endphp
    @if($logoPath && file_exists($logoPath))
        <img src="{{ $logoPath }}" alt="Logo" class="logo-img">
    @elseif(file_exists(public_path('images/logo.png')))
        <img src="{{ public_path('images/logo.png') }}" alt="Logo" class="logo-img">
    @else
        <span class="logo-fallback">{{ $settings['company_name'] ?? '' }}</span>
    @endif
</div>
{{-- DomPDF: break out of centred logo formatting so following full-width tables align left --}}
<div class="pdf-flow-reset"></div>
