<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 34px 40px 48px; }
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; color: #0A1220; font-size: 12px; line-height: 1.55; }
  .kicker { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #1B6DF0; font-weight: bold; }
  .brand { font-size: 20px; font-weight: bold; color: #0A1220; }
  .brand .ai { color: #1B6DF0; }
  .head { border-bottom: 2px solid #0A1220; padding-bottom: 12px; margin-bottom: 18px; }
  h1.title { font-size: 22px; color: #0A1220; margin: 4px 0 2px; }
  .meta { font-size: 10px; color: #5A6B85; }
  .content h1 { font-size: 17px; color: #0A1220; margin: 18px 0 6px; border-bottom: 1px solid #DCE6F5; padding-bottom: 4px; }
  .content h2 { font-size: 14px; color: #0E3FA9; margin: 14px 0 5px; }
  .content h3 { font-size: 12.5px; color: #1B2A44; margin: 12px 0 4px; }
  .content p { margin: 6px 0; }
  .content ul, .content ol { margin: 6px 0 6px 18px; }
  .content li { margin: 3px 0; }
  .content code { font-family: DejaVu Sans Mono, monospace; background: #F6F9FE; padding: 1px 4px; border-radius: 3px; font-size: 11px; }
  .content pre { background: #0A1220; color: #E7F1FE; padding: 10px 12px; border-radius: 6px; overflow: hidden; font-family: DejaVu Sans Mono, monospace; font-size: 10.5px; white-space: pre-wrap; }
  .content pre code { background: transparent; color: inherit; padding: 0; }
  .content blockquote { border-left: 3px solid #1B6DF0; margin: 8px 0; padding: 2px 0 2px 12px; color: #1B2A44; }
  .content table { border-collapse: collapse; width: 100%; margin: 8px 0; font-size: 11px; }
  .content th, .content td { border: 1px solid #DCE6F5; padding: 5px 7px; text-align: left; }
  .content th { background: #E7F1FE; }
  .foot { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8.5px; color: #5A6B85; border-top: 1px solid #DCE6F5; padding-top: 6px; }
</style>
</head>
<body>
  <div class="head">
    <span class="brand">{{ $tenantName }}<span class="ai">.ai</span></span>
    <p class="kicker" style="margin-top:10px">Class notes · {{ $courseName }}</p>
    <h1 class="title">{{ $lessonTitle }}</h1>
    <p class="meta">{{ $moduleName }} · Generated {{ $generatedOn }}</p>
  </div>
  <div class="content">{!! $body !!}</div>
  <div class="foot">
    {{ $tenantName }} · Built from real interviews · Study notes for enrolled students. Not for redistribution.
  </div>
</body>
</html>
