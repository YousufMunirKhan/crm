{{-- Minimal footer: optional note + thank you (no company block) --}}
<div class="footer footer--minimal">
    @if(!empty($pdfFooterNote ?? null))
        <div class="footer-note footer-note--meta">{{ $pdfFooterNote }}</div>
    @endif
    <div class="footer-note">Thank you for your business!</div>
</div>
