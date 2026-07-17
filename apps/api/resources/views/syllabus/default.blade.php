@php
    $trust = $colors['trust_blue'] ?? '#1B6DF0';
    $ink = $colors['ink_navy'] ?? '#0A1220';
    $paper = $colors['paper'] ?? '#F6F9FE';
    $line = $colors['line'] ?? '#DCE6F5';
    $muted = $colors['muted'] ?? '#5A6B85';
    $display = $fonts['display'] ?? 'Sora';
    $body = $fonts['body'] ?? 'Inter';
    $mono = $fonts['mono'] ?? 'IBM Plex Mono';
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Syllabus — {{ $courseName }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: '{{ $body }}', Arial, sans-serif; color: {{ $ink }}; background: {{ $paper }}; padding: 40px; line-height: 1.6; }
    .doc { max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid {{ $line }}; border-top: 8px solid {{ $trust }}; border-radius: 14px; padding: 56px 64px; }
    .kicker { font-family: '{{ $mono }}', monospace; text-transform: uppercase; letter-spacing: 3px; font-size: 12px; color: {{ $trust }}; }
    .brand { font-family: '{{ $display }}', sans-serif; font-weight: 800; font-size: 20px; color: {{ $ink }}; margin-top: 4px; }
    h1 { font-family: '{{ $display }}', sans-serif; font-weight: 800; font-size: 32px; color: {{ $ink }}; margin-top: 28px; letter-spacing: -0.02em; }
    .content { margin-top: 28px; }
    .content h2 { font-family: '{{ $display }}', sans-serif; font-weight: 700; font-size: 20px; color: {{ $ink }}; margin-top: 28px; }
    .content h3 { font-family: '{{ $display }}', sans-serif; font-weight: 700; font-size: 16px; color: {{ $ink }}; margin-top: 20px; }
    .content p { margin-top: 12px; font-size: 15px; }
    .content ul { margin-top: 12px; padding-left: 22px; }
    .content li { margin-top: 6px; font-size: 15px; }
    .foot { margin-top: 48px; border-top: 1px solid {{ $line }}; padding-top: 20px; font-family: '{{ $mono }}', monospace; font-size: 11px; color: {{ $muted }}; }
</style>
</head>
<body>
    <div class="doc">
        <p class="kicker">Course Syllabus</p>
        <p class="brand">{{ $tenantName }}</p>
        <h1>{{ $title }}</h1>
        <div class="content">{!! $bodyHtml !!}</div>
        <p class="foot">Every promise in writing · Every call recorded &amp; AI-monitored.</p>
    </div>
</body>
</html>
