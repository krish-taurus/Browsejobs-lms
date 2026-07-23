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
  .instructions { background: #F6F9FE; border-left: 3px solid #1B6DF0; padding: 8px 12px; margin: 0 0 14px; }
  .q { margin: 0 0 14px; }
  .q .prompt { font-weight: bold; margin: 0 0 5px; }
  .q .opt { margin: 2px 0 2px 16px; }
  .q .letter { display: inline-block; width: 18px; color: #0E3FA9; font-weight: bold; }
  .key { page-break-before: always; }
  .key h2 { font-size: 16px; color: #0A1220; border-bottom: 1px solid #DCE6F5; padding-bottom: 4px; }
  .key .warn { font-size: 10px; color: #D64545; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
  .key .row { margin: 6px 0; }
  .key .why { color: #5A6B85; font-size: 11px; }
  .foot { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8.5px; color: #5A6B85; border-top: 1px solid #DCE6F5; padding-top: 6px; }
</style>
</head>
<body>
  <div class="head">
    <span class="brand">{{ $tenantName }}<span class="ai">.ai</span></span>
    <p class="kicker" style="margin-top:10px">Assignment · {{ $courseName }}</p>
    <h1 class="title">{{ $title }}</h1>
    <p class="meta">{{ $moduleName }}@if($dayLabel) · {{ $dayLabel }}@endif · {{ $topicName }} · Generated {{ $generatedOn }}</p>
  </div>

  <div class="instructions">
    {{ $instructions ?: 'Answer every question. Pick the single best option for each.' }}
  </div>

  @foreach ($questions as $i => $q)
    <div class="q">
      <p class="prompt">{{ $i + 1 }}. {{ $q->prompt }}</p>
      @foreach ($q->options as $j => $opt)
        <p class="opt"><span class="letter">{{ chr(65 + $j) }}.</span> {{ $opt }}</p>
      @endforeach
    </div>
  @endforeach

  <div class="key">
    <p class="warn">Trainer copy — do not hand out</p>
    <h2>Answer key</h2>
    @foreach ($questions as $i => $q)
      <div class="row">
        <strong>{{ $i + 1 }}. {{ chr(65 + $q->correct_index) }}</strong>
        @if ($q->explanation)
          — <span class="why">{{ $q->explanation }}</span>
        @endif
      </div>
    @endforeach
  </div>

  <div class="foot">
    {{ $tenantName }} · Built from real interviews · Assignment paper for enrolled students. Not for redistribution.
  </div>
</body>
</html>
