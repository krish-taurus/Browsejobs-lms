@php
    /**
     * Branded transactional email. The body arrives as pre-rendered plain text
     * from the Messenger; this template dresses it up:
     *  - OTP templates show the code big and centered,
     *  - "Key: value" lines (Login / Password / Batch …) become a detail card,
     *  - a "Sign in: <url>" line (or the first URL found) becomes the CTA button,
     *  - remaining lines render as paragraphs with links made clickable.
     */
    $key = (string) ($templateKey ?? '');
    $isOtp = str_contains($key, 'otp');

    $otpCode = null;
    if ($isOtp && preg_match('/\b(\d{4,8})\b/', $bodyText, $m)) {
        $otpCode = $m[1];
    }

    $ctaLabels = [
        'batch_credentials' => 'Sign in to your dashboard',
        'batch_welcome' => 'Open your dashboard',
        'class_scheduled' => 'View your classes',
        'class_reminder' => 'Join your class',
        'bootcamp_payment_nudge' => 'Secure your seat',
        'batch_announcement' => 'Open your dashboard',
        'payment_link' => 'Pay securely now',
        'fee_reminder' => 'Pay securely now',
    ];
    $ctaLabel = 'Open your dashboard';
    foreach ($ctaLabels as $prefix => $label) {
        if (str_starts_with($key, $prefix)) {
            $ctaLabel = $label;
            break;
        }
    }

    $rows = [];
    $paras = [];
    $ctaUrl = null;

    foreach (preg_split('/\r?\n/', trim($bodyText)) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^Sign in\s*:\s*(https?:\/\/\S+)$/i', $line, $m)) {
            $ctaUrl = $m[1];
            continue;
        }
        if (preg_match('/^(Login|Password|Batch|Starts|When|Class|Course|Amount|Instalment|Due by)\s*:\s*(.+)$/i', $line, $m)) {
            $rows[ucfirst(strtolower($m[1]))] = $m[2];
            continue;
        }
        $paras[] = $line;
    }

    if ($ctaUrl === null && preg_match('/https?:\/\/[^\s<>"]+/', $bodyText, $m)) {
        $ctaUrl = rtrim($m[0], '.,;:)');
    }

    // Escape a paragraph, then turn bare URLs into styled links (trailing
    // sentence punctuation stays outside the link).
    $linkify = function (string $text): string {
        return preg_replace_callback(
            '/https?:\/\/[^\s<>&"]+/',
            function (array $m): string {
                $url = rtrim($m[0], '.,;:)');
                $trail = substr($m[0], strlen($url));

                return '<a href="'.$url.'" style="color:#1D4ED8; text-decoration:underline; word-break:break-all;">'.$url.'</a>'.$trail;
            },
            e($text),
        );
    };
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $subjectLine ?? 'BrowseJobs' }}</title>
</head>
<body style="font-family: Inter, -apple-system, 'Segoe UI', Arial, sans-serif; color:#0A1220; background:#F1F5FB; margin:0; padding:0;">
  <div style="padding:32px 16px;">
    <div style="max-width:560px; margin:0 auto;">

      <!-- Header band -->
      <div style="background:#0A1220; border-radius:16px 16px 0 0; padding:22px 28px;">
        <span style="font-weight:800; font-size:20px; letter-spacing:-0.02em; color:#ffffff;">BrowseJobs<span style="color:#3B82F6;">.ai</span></span>
      </div>

      <!-- Card -->
      <div style="background:#ffffff; border:1px solid #DCE6F5; border-top:none; border-radius:0 0 16px 16px; padding:32px 28px;">

        @if ($isOtp && $otpCode !== null)
          <p style="margin:0; font-size:13px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#3B82F6;">Student Portal</p>
          <h1 style="margin:8px 0 0; font-size:22px; letter-spacing:-0.01em;">Your one-time sign-in code</h1>
          <div style="margin:24px 0; text-align:center;">
            <div style="display:inline-block; background:#F1F5FB; border:1px dashed #94A3B8; border-radius:12px; padding:18px 32px;">
              <span style="font-family: 'Courier New', monospace; font-size:36px; font-weight:800; letter-spacing:0.35em; color:#0A1220;">{{ $otpCode }}</span>
            </div>
          </div>
          <p style="margin:0; font-size:14px; line-height:1.6; color:#5A6B85; text-align:center;">
            Enter this code on the sign-in page. It expires in <strong>10 minutes</strong>.<br>
            Didn't request it? You can safely ignore this email.
          </p>
        @else
          @foreach ($paras as $para)
            <p style="margin:0 0 14px; font-size:15px; line-height:1.65;">{!! $linkify($para) !!}</p>
          @endforeach

          @if ($rows !== [])
            <div style="background:#F1F5FB; border:1px solid #DCE6F5; border-radius:12px; padding:6px 18px; margin:20px 0;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                @foreach ($rows as $label => $value)
                  <tr>
                    <td style="padding:10px 12px 10px 0; font-size:13px; color:#5A6B85; white-space:nowrap; vertical-align:top; {{ $loop->last ? '' : 'border-bottom:1px solid #DCE6F5;' }}">{{ $label }}</td>
                    <td style="padding:10px 0; font-size:15px; font-weight:700; font-family: 'Courier New', monospace; word-break:break-all; {{ $loop->last ? '' : 'border-bottom:1px solid #DCE6F5;' }}">{{ $value }}</td>
                  </tr>
                @endforeach
              </table>
            </div>
          @endif

          @if ($ctaUrl !== null)
            <div style="text-align:center; margin:26px 0 6px;">
              <a href="{{ $ctaUrl }}"
                 style="display:inline-block; background:#1D4ED8; color:#ffffff; font-size:15px; font-weight:700; text-decoration:none; padding:13px 34px; border-radius:999px;">
                {{ $ctaLabel }}
              </a>
            </div>
            <p style="margin:10px 0 0; font-size:12px; color:#8A99B3; text-align:center; word-break:break-all;">
              Button not working? Copy this link: {{ $ctaUrl }}
            </p>
          @endif
        @endif

        <hr style="border:none; border-top:1px solid #DCE6F5; margin:28px 0 18px;">
        <div style="font-size:11px; line-height:1.7; color:#5A6B85;">
          Every promise in writing · Every call recorded &amp; AI-monitored.<br>
          +91 86185 19825 · hello@browsejobs.ai · Whitefield, Bengaluru
        </div>
      </div>

      <p style="text-align:center; font-size:11px; color:#8A99B3; margin:16px 0 0;">
        © {{ date('Y') }} BrowseJobs — helping you get hired, step by step.
      </p>
    </div>
  </div>
</body>
</html>
