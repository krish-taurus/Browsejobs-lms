<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Interview recording · BrowseJobs</title>
<style>
  :root { color-scheme: light dark; --bg: #f4f7fc; --ink: #0a1220; --muted: #5a6b85; --card: #ffffff; --line: #dce6f5; --brand: #1b6df0; }
  @media (prefers-color-scheme: dark) { :root { --bg: #0a1220; --ink: #eef3fb; --muted: #9db0cc; --card: #111c30; --line: #1f2e4a; --brand: #6aa3ff; } }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--ink); font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; line-height: 1.5; }
  main { max-width: 920px; margin: 0 auto; padding: 24px 16px 48px; }
  .kicker { font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: var(--brand); font-weight: 700; margin: 0; }
  h1 { font-size: 24px; margin: 6px 0 4px; text-wrap: balance; }
  .meta { color: var(--muted); margin: 0 0 18px; font-size: 14px; }
  .frame { background: #000; border-radius: 14px; overflow: hidden; border: 1px solid var(--line); }
  video { display: block; width: 100%; max-height: 70vh; background: #000; }
  .wait { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 28px 20px; text-align: center; }
  .note { color: var(--muted); font-size: 13px; margin-top: 14px; }
</style>
</head>
<body>
<main>
  <p class="kicker">Interview recording</p>
  <h1>{{ $role }}{{ $company !== '' ? ' · '.$company : '' }}</h1>
  <p class="meta">{{ $minutes !== null ? "About {$minutes} min · " : '' }}The whole interview, video and audio, as it happened.</p>

  @if($video)
    <div class="frame"><video id="rec" controls playsinline preload="metadata" src="{{ $video }}"></video></div>
    <script>
      // A link ending in #t=125 opens the recording at 2:05 (the lip-sync report links each moment this way).
      (function () {
        var v = document.getElementById('rec');
        var m = /[#&]t=(\d+)/.exec(window.location.hash);
        if (!v || !m) { return; }
        var at = parseInt(m[1], 10);
        v.addEventListener('loadedmetadata', function () { v.currentTime = at; });
      })();
    </script>
    <p class="note">If the video does not play on your phone, open this link in Chrome or on a computer. The link is private to you and expires; do not forward it.</p>
  @else
    <div class="wait">
      <b>The recording is still being processed.</b>
      <p class="note" style="margin-bottom: 0">It is saved a few minutes after the interview ends. Open this same link again shortly.</p>
    </div>
  @endif
</main>
</body>
</html>
