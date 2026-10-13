<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 34px 40px 52px; }
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; color: #0A1220; font-size: 11.5px; line-height: 1.5; }
  .kicker { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #1B6DF0; font-weight: bold; margin: 0; }
  .head { border-bottom: 2px solid #0A1220; padding-bottom: 12px; margin-bottom: 16px; }
  h1 { font-size: 22px; margin: 6px 0 2px; }
  h2 { font-size: 14px; margin: 20px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #DCE6F5; }
  .meta { font-size: 10px; color: #5A6B85; margin: 0; }
  .score { width: 100%; border-collapse: collapse; margin-top: 4px; }
  .score td { vertical-align: top; }
  .big { font-size: 34px; font-weight: bold; line-height: 1; }
  .pill { display: inline-block; padding: 3px 9px; border-radius: 10px; font-size: 10px; font-weight: bold; }
  .pass { background: #E6F6EC; color: #1F7A3A; }
  .fail { background: #FDECEC; color: #B4232A; }
  .warn { background: #FFF4E0; color: #9A5B00; }
  .ok { background: #EEF3FB; color: #34507A; }
  .bar { height: 6px; background: #EEF3FB; border-radius: 3px; }
  .bar i { display: block; height: 6px; background: #1B6DF0; border-radius: 3px; }
  .dims td { padding: 3px 0; font-size: 11px; }
  ul { margin: 4px 0 0 16px; padding: 0; }
  li { margin: 2px 0; }
  .qa { margin: 0 0 10px; page-break-inside: avoid; }
  .qa .q { font-weight: bold; margin: 0 0 3px; }
  .qa .a { margin: 0; color: #2B3A52; background: #F6F9FE; padding: 6px 9px; border-left: 3px solid #1B6DF0; }
  .flags { width: 100%; border-collapse: collapse; }
  .flags td { border-bottom: 1px solid #EEF3FB; padding: 6px 4px; vertical-align: middle; }
  .flags img { width: 128px; border-radius: 4px; }
  .grid { table-layout: fixed; }
  .grid td { width: 33%; padding: 4px; vertical-align: top; font-size: 9.5px; color: #5A6B85; }
  .grid img { width: 150px; border-radius: 4px; }
  .note { font-size: 10px; color: #5A6B85; background: #F6F9FE; padding: 8px 10px; margin-top: 8px; }
  .foot { position: fixed; bottom: -34px; left: 0; right: 0; font-size: 8.5px; color: #5A6B85; border-top: 1px solid #DCE6F5; padding-top: 6px; }
</style>
</head>
<body>
  <div class="foot">Confidential — {{ $company }} hiring · Generated {{ $generatedOn }} IST by BrowseJobs Taurus · Full profile and recording: {{ $profileUrl }}</div>

  <div class="head">
    <p class="kicker">Interview report · {{ $company }}</p>
    <h1>{{ $name }}</h1>
    <p class="meta">{{ $role }} · {{ $round }}@if($takenAt) · Taken {{ $takenAt }} IST @endif @if($duration) · {{ intdiv($duration, 60) }} min @endif</p>
  </div>

  <table class="score">
    <tr>
      <td style="width: 34%">
        <div class="big">{{ $score }}<span style="font-size: 14px; color: #5A6B85">/100</span></div>
        <p style="margin: 6px 0 0">
          @if($passed)
            <span class="pill pass">Passed · pass mark {{ $passMark }}</span>
          @else
            <span class="pill fail">Below pass mark {{ $passMark }}</span>
          @endif
        </p>
        <p style="margin: 6px 0 0">
          @if(! $proctored)
            <span class="pill ok">{{ $onZoom ? 'Zoom interview' : 'Integrity not captured' }}</span>
          @elseif($flags > 0)
            <span class="pill fail">{{ $flags }} integrity {{ $flags === 1 ? 'flag' : 'flags' }}</span>
          @elseif($possible > 0)
            <span class="pill warn">{{ $possible }} possible {{ $possible === 1 ? 'flag' : 'flags' }}</span>
          @else
            <span class="pill pass">No integrity flags</span>
          @endif
        </p>
      </td>
      <td>
        @if(count($dimensions) > 0)
          <table class="dims" style="width: 100%">
            @foreach($dimensions as $d)
              <tr>
                <td style="width: 42%">{{ $d['label'] }}</td>
                <td style="width: 46%"><div class="bar"><i style="width: {{ $d['score'] }}%"></i></div></td>
                <td style="width: 12%; text-align: right; font-weight: bold">{{ $d['score'] }}</td>
              </tr>
            @endforeach
          </table>
        @endif
      </td>
    </tr>
  </table>

  @if($summary)
    <h2>Summary</h2>
    <p style="margin: 0">{{ $summary }}</p>
  @endif

  @if(count($strengths) > 0 || count($concerns) > 0)
    <table style="width: 100%; margin-top: 6px">
      <tr>
        <td style="width: 50%; vertical-align: top; padding-right: 10px">
          @if(count($strengths) > 0)
            <h2>Strengths</h2>
            <ul>@foreach($strengths as $s)<li>{{ $s }}</li>@endforeach</ul>
          @endif
        </td>
        <td style="width: 50%; vertical-align: top">
          @if(count($concerns) > 0)
            <h2>Concerns</h2>
            <ul>@foreach($concerns as $c)<li>{{ $c }}</li>@endforeach</ul>
          @endif
        </td>
      </tr>
    </table>
  @endif

  <h2>Integrity</h2>
  @if(! $proctored)
    <p style="margin: 0">
      {{ $onZoom ? 'This round ran on Zoom with the AI interviewer, so the browser checks did not apply. The full recording is on the candidate page.' : 'The interview room did not report integrity checks for this round (an older room, or no camera), so nothing can be said either way. Check the recording on the candidate page.' }}
    </p>
  @elseif(count($flagged) === 0)
    <p style="margin: 0">Nothing was flagged: the candidate stayed in the interview window, did not paste, and stayed on camera throughout.</p>
  @else
    <p style="margin: 0 0 6px">
      @foreach($flagCounts as $kind => $count)
        <span class="pill {{ in_array($kind, ['multi_face', 'gaze_down', 'gaze_side'], true) ? 'warn' : 'fail' }}">{{ \App\Models\MockIntegrityEvent::LABELS[$kind] ?? $kind }} × {{ $count }}</span>
      @endforeach
    </p>
    <table class="flags">
      @foreach($flagged as $f)
        <tr>
          <td style="width: 70px; color: #5A6B85">{{ $f['at'] }}</td>
          <td>
            <strong>{{ $f['label'] }}</strong>
            @if($f['severity'] === 'possible')<br><span style="color: #9A5B00; font-size: 9.5px">Camera estimate — check the photo; can misfire on posters, reflections or glasses.</span>@endif
          </td>
          <td style="width: 140px; text-align: right">
            @if($f['image'])<img src="{{ $f['image'] }}">@else<span style="color: #5A6B85; font-size: 9.5px">No photo (camera off or tab hidden)</span>@endif
          </td>
        </tr>
      @endforeach
    </table>
  @endif

  @if(count($checkIns) > 0)
    <p style="margin: 12px 0 4px; font-weight: bold">Check-in photos (random, to confirm the same person throughout)</p>
    <table class="grid" style="width: 100%">
      <tr>
        @foreach($checkIns as $i => $c)
          @if($i > 0 && $i % 3 === 0)</tr><tr>@endif
          <td>@if($c['image'])<img src="{{ $c['image'] }}"><br>@endif{{ $c['at'] }}</td>
        @endforeach
        @for($pad = count($checkIns) % 3; $pad > 0 && $pad < 3; $pad++)<td></td>@endfor
      </tr>
    </table>
  @endif

  @if($proctored)
    <div class="note">
      Flags are noted, not punished: the interview carries on and the candidate is told something was noticed. Read them alongside the answers{{ $hasRecording ? ' and the full recording on the candidate page' : ' below' }}.
    </div>
  @endif

  @if(count($answers) > 0)
    <h2>Questions and answers</h2>
    @foreach($answers as $i => $qa)
      <div class="qa">
        <p class="q">{{ $i + 1 }}. {{ $qa['q'] }}</p>
        <p class="a">{{ $qa['a'] }}</p>
      </div>
    @endforeach
  @endif
</body>
</html>
