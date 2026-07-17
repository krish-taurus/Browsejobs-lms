@php
    $trust = $colors['trust_blue'] ?? '#1B6DF0';
    $ink = $colors['ink_navy'] ?? '#0A1220';
    $verify = $colors['verify_green'] ?? '#0BA860';
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
<title>Certificate — {{ $studentName }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: '{{ $body }}', Arial, sans-serif; color: {{ $ink }}; background: {{ $paper }}; padding: 40px; }
    .cert { max-width: 900px; margin: 0 auto; background: #fff; border: 2px solid {{ $line }}; border-top: 8px solid {{ $trust }}; border-radius: 14px; padding: 56px 64px; }
    .kicker { font-family: '{{ $mono }}', monospace; text-transform: uppercase; letter-spacing: 3px; font-size: 12px; color: {{ $trust }}; }
    .brand { font-family: '{{ $display }}', sans-serif; font-weight: 800; font-size: 22px; color: {{ $ink }}; margin-top: 4px; }
    h1 { font-family: '{{ $display }}', sans-serif; font-weight: 800; font-size: 34px; color: {{ $ink }}; margin-top: 40px; letter-spacing: -0.02em; }
    .awarded { color: {{ $muted }}; margin-top: 28px; font-size: 15px; }
    .name { font-family: '{{ $display }}', sans-serif; font-weight: 800; font-size: 40px; color: {{ $trust }}; margin-top: 8px; }
    .course { font-size: 18px; margin-top: 20px; }
    .course strong { color: {{ $ink }}; }
    .foot { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 48px; border-top: 1px solid {{ $line }}; padding-top: 24px; }
    .meta { font-family: '{{ $mono }}', monospace; font-size: 12px; color: {{ $muted }}; line-height: 1.8; }
    .meta b { color: {{ $ink }}; }
    .qr { width: 120px; height: 120px; }
    .verify { font-family: '{{ $mono }}', monospace; font-size: 10px; color: {{ $muted }}; text-align: center; margin-top: 6px; max-width: 140px; word-break: break-all; }
    .seal { color: {{ $verify }}; font-weight: 600; font-size: 13px; }
</style>
</head>
<body>
    <div class="cert">
        <p class="kicker">Certificate of Completion</p>
        <p class="brand">{{ $tenantName }}</p>

        <h1>This certifies that</h1>
        <p class="name">{{ $studentName }}</p>
        <p class="course">has successfully completed <strong>{{ $courseName }}</strong>.</p>
        <p class="seal">✓ Verified completion · {{ $tenantName }}</p>

        <div class="foot">
            <div class="meta">
                Issued <b>{{ $issuedOn }}</b><br>
                Certificate no. <b>{{ $number }}</b><br>
                ID <b>{{ $code }}</b>
            </div>
            <div>
                <div class="qr">{!! $qrSvg !!}</div>
                <p class="verify">Verify at {{ $verifyUrl }}</p>
            </div>
        </div>
    </div>
</body>
</html>
