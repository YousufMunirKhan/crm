<?php

/**
 * Converts a full HTML file with base64 <img> into:
 * - public/images/email/welcome/main-logo.png (first image), partners-row + service/social icons (named files)
 * - resources/email-templates/welcome-rich.partial.html (body fragment with merge tags)
 *
 * Place source as _user_welcome_source.html next to the partial, then: php scripts/build_welcome_email_assets.php
 */

$root = dirname(__DIR__);
$src = $root . '/resources/email-templates/_user_welcome_source.html';
$outDir = $root . '/public/images/email/welcome';
$outPartial = $root . '/resources/email-templates/welcome-rich.partial.html';

if (!is_file($src)) {
    fwrite(STDERR, "Missing source: {$src}\n");
    exit(1);
}

if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

$html = file_get_contents($src);

// Match <img ... src="data:image/...;base64,..."
$pattern = '/<img([^>]*?)src\s*=\s*"(data:image\/(png|jpeg|jpg|gif|webp);base64,([^"]+))"([^>]*)>/i';

$semanticNames = [
    2 => 'partners-row',
    3 => 'icon-epos',
    4 => 'icon-funding',
    5 => 'icon-website',
    6 => 'icon-facebook',
    7 => 'icon-linkedin',
    8 => 'icon-tiktok',
    9 => 'icon-instagram',
];

$index = 0;
$htmlOut = preg_replace_callback($pattern, function ($m) use (&$index, $outDir, $semanticNames) {
    $before = $m[1];
    $ext = strtolower($m[3]);
    $b64 = $m[4];
    $after = $m[5];
    $raw = base64_decode($b64, true);
    if ($raw === false) {
        return $m[0];
    }
    $index++;
    $extResolved = $ext === 'jpeg' ? 'jpg' : $ext;
    if ($index === 1) {
        $fname = 'main-logo.' . $extResolved;
        file_put_contents($outDir . '/' . $fname, $raw);
        if ($fname !== 'main-logo.png' && $extResolved === 'png') {
            @unlink($outDir . '/main-logo.png');
            rename($outDir . '/' . $fname, $outDir . '/main-logo.png');
            $fname = 'main-logo.png';
        }
        return '<img' . $before . 'src="{{header_logo_url}}"' . $after . '>';
    }
    $base = $semanticNames[$index] ?? ('welcome-asset-' . str_pad((string) ($index - 1), 2, '0', STR_PAD_LEFT));
    $fname = $base . '.' . $extResolved;
    file_put_contents($outDir . '/' . $fname, $raw);
    return '<img' . $before . 'src="{{email_welcome_dir_url}}/' . $fname . '"' . $after . '>';
}, $html);

$htmlOut = str_replace(['[FIRSTNAME]', '[firstname]'], '{{first_name}}', $htmlOut);

$htmlOut = str_replace('href="https://www.switch-and-save.uk"', 'href="{{company_website}}"', $htmlOut);
$htmlOut = str_replace(
    'href="#" style="display:inline-block;padding:13px 32px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.3px;">Book Your Free Demo',
    'href="{{company_website}}" style="display:inline-block;padding:13px 32px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.3px;">Book Your Free Demo',
    $htmlOut
);
$htmlOut = str_replace(
    'href="#" style="display:inline-block;padding:12px 22px;font-size:13px;font-weight:700;color:#ffffff;text-decoration:none;white-space:nowrap;">Book Your Free Demo',
    'href="{{company_website}}" style="display:inline-block;padding:12px 22px;font-size:13px;font-weight:700;color:#ffffff;text-decoration:none;white-space:nowrap;">Book Your Free Demo',
    $htmlOut
);

// Facebook / LinkedIn / TikTok / Instagram — replace href="#" on the line that contains distinctive bg
$htmlOut = preg_replace(
    '/<a href="#" style="display:inline-block;width:28px;height:28px;background:#3b5998[^"]*">\s*/',
    '<a href="{{social_facebook_url}}" style="display:inline-block;width:28px;height:28px;background:#3b5998;border-radius:50%;overflow:hidden;text-align:center;line-height:28px;">',
    $htmlOut,
    1
);
$htmlOut = preg_replace(
    '/<a href="#" style="display:inline-block;width:28px;height:28px;background:#0077b5[^"]*">\s*/',
    '<a href="{{social_linkedin_url}}" style="display:inline-block;width:28px;height:28px;background:#0077b5;border-radius:50%;overflow:hidden;text-align:center;line-height:28px;">',
    $htmlOut,
    1
);
$htmlOut = preg_replace(
    '/<a href="#" style="display:inline-block;width:28px;height:28px;background:#000000[^"]*">\s*/',
    '<a href="{{social_tiktok_url}}" style="display:inline-block;width:28px;height:28px;background:#000000;border-radius:50%;overflow:hidden;text-align:center;line-height:28px;">',
    $htmlOut,
    1
);
$htmlOut = preg_replace(
    '/<a href="#" style="display:inline-block;width:28px;height:28px;background:radial-gradient\(circle at 30% 107%[^"]*\)">\s*/',
    '<a href="{{social_instagram_url}}" style="display:inline-block;width:28px;height:28px;background:radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%);border-radius:50%;overflow:hidden;text-align:center;line-height:28px;">',
    $htmlOut,
    1
);
if (str_contains($htmlOut, 'href="#" style="display:inline-block;width:28px;height:28px;background:radial-gradient')) {
    $htmlOut = preg_replace(
        '/<a href="#" style="display:inline-block;width:28px;height:28px;background:radial-gradient\(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%\)[^"]*">\s*/',
        '<a href="{{social_instagram_url}}" style="display:inline-block;width:28px;height:28px;background:radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%);border-radius:50%;overflow:hidden;text-align:center;line-height:28px;">',
        $htmlOut,
        1
    );
}

$htmlOut = str_replace(
    '© 2025 Switch &amp; Save. All rights reserved. | You\'re receiving this because you signed up at switch-and-save.uk',
    '© {{current_year}} {{company_name}}. All rights reserved. | <a href="{{unsubscribe_url}}" style="color:rgba(255,255,255,0.85);">Unsubscribe</a>',
    $htmlOut
);

if (preg_match('/<body[^>]*>(.*)<\/body>/is', $htmlOut, $bm)) {
    $htmlOut = trim($bm[1]);
}

file_put_contents($outPartial, $htmlOut);
$count = count(glob($outDir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE));
echo "Wrote {$outPartial} (" . strlen($htmlOut) . " bytes), {$count} image file(s) in welcome/\n";
