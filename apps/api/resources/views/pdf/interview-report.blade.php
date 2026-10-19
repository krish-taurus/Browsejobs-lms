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
        @if(count($notCovered) > 0)
          <p class="meta" style="margin-top: 6px">Not assessed in this interview (not asked): {{ implode(', ', $notCovered) }}.</p>
        @endif
      </td>
    </tr>
  </table>

  @if($proctored && $lipReport['verdict'] === 'not_matched')
    <div style="border: 2px solid #B4232A; background: #FDECEC; padding: 10px 12px; margin-top: 12px">
      <p class="kicker" style="color: #B4232A">Lip-sync report</p>
      <p style="font-size: 15px; font-weight: bold; margin: 3px 0 6px; color: #B4232A">{{ $lipReport['headline'] }}</p>
      <p style="margin: 0 0 4px; font-size: 10.5px">The candidate's mouth, seen on camera, did not match the sound of the answers at the moments below. This is an automatic estimate: read it with the photos and the recording, which opens at each moment.</p>
      <ul style="font-size: 10.5px">@foreach($lipReport['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>

      <table class="dims" style="width: 100%; margin-top: 6px">
        @foreach($lipReport['stats'] as $stat)
          <tr>
            <td style="width: 44%">{{ $stat['label'] }}</td>
            <td style="width: 20%; font-weight: bold">{{ $stat['value'] }}</td>
            <td style="width: 36%; color: #5A6B85; font-size: 9.5px">{{ $stat['note'] }}</td>
          </tr>
        @endforeach
      </table>

      <p style="margin: 8px 0 2px; font-size: 9.5px; color: #5A6B85">Timeline of the interview (red = lips and voice disagree, orange = a second voice)</p>
      <table style="width: 100%; border-collapse: collapse; table-layout: fixed">
        <tr>
          @foreach($lipReport['timeline'] as $slice)
            <td style="height: 12px; padding: 0; border: 0; background: {{ $slice === 'lip' ? '#B4232A' : ($slice === 'voice' ? '#E08A00' : '#C9D8EE') }}"></td>
          @endforeach
        </tr>
      </table>
      <table style="width: 100%; font-size: 8.5px; color: #5A6B85"><tr><td>0:00</td><td style="text-align: right">{{ $lipReport['total'] }}</td></tr></table>

      <table class="flags" style="margin-top: 6px">
        @foreach($lipReport['moments'] as $m)
          <tr>
            <td style="width: 54px; color: #5A6B85">{{ $m['at'] }}</td>
            <td>
              <strong>{{ $m['label'] }}</strong>
              @if($m['text'])<br><span style="font-size: 10px">{{ $m['text'] }}</span>@endif
              @if($m['link'])<br><a href="{{ $m['link'] }}" style="color: #1B6DF0; font-size: 10px">Watch the recording from {{ $m['at'] }}</a>@endif
            </td>
            <td style="width: 140px; text-align: right">
              @if($m['image'])<img src="{{ $m['image'] }}">@else<span style="color: #5A6B85; font-size: 9.5px">No photo</span>@endif
            </td>
          </tr>
        @endforeach
      </table>
    </div>
  @elseif($proctored)
    <p style="margin: 10px 0 0; font-size: 10.5px">
      <span class="pill {{ $lipReport['verdict'] === 'matched' ? 'pass' : ($lipReport['verdict'] === 'inconclusive' ? 'warn' : 'ok') }}">{{ $lipReport['headline'] }}</span>
      {{ $lipReport['line'] }}
    </p>
  @endif

  @if($recordingUrl)
    <div class="note" style="margin-top: 10px">
      <b>Watch the full interview recording:</b>
      <a href="{{ $recordingUrl }}" style="color: #1B6DF0">Open the recording</a>
      — the whole interview, video and audio, exactly as it happened. The link works for 30 days and needs no login.
    </div>
  @endif

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
        <span class="pill {{ (\App\Models\MockIntegrityEvent::KINDS[$kind] ?? '') === 'possible' ? 'warn' : 'fail' }}">{{ \App\Models\MockIntegrityEvent::LABELS[$kind] ?? $kind }} × {{ $count }}</span>
      @endforeach
    </p>
    <table class="flags">
      @foreach($flagged as $f)
        <tr>
          <td style="width: 70px; color: #5A6B85">{{ $f['at'] }}</td>
          <td>
            <strong>{{ $f['label'] }}</strong>
            @if($f['detail'])<br><span style="font-size: 10px">{{ $f['detail'] }}</span>@endif
            @if($f['severity'] === 'possible')<br><span style="color: #9A5B00; font-size: 9.5px">Automatic estimate — check the photo and the recording; it can misfire (poor light, a dropped camera frame, a noisy microphone).</span>@endif
          </td>
          <td style="width: 140px; text-align: right">
            @if($f['image'])<img src="{{ $f['image'] }}">@else<span style="color: #5A6B85; font-size: 9.5px">No photo (camera off or tab hidden)</span>@endif
          </td>
        </tr>
      @endforeach
    </table>
  @endif

  @if($proctored)
    <p style="margin: 12px 0 2px; font-weight: bold">Lip-sync and voice check</p>
    <p style="margin: 0; font-size: 10.5px">
      <span class="pill {{ $lipSync['captured'] ? 'ok' : 'warn' }}">{{ $lipSync['captured'] ? 'Lip-sync checked' : 'Lip-sync not captured' }}</span>
      {{ $lipSync['line'] }}
    </p>
    <p style="margin: 4px 0 0; font-size: 10.5px">
      <span class="pill {{ $voiceCheck['checked'] ? 'ok' : 'warn' }}">{{ $voiceCheck['checked'] ? 'Voice checked' : 'Voice not checked' }}</span>
      {{ $voiceCheck['line'] }}
    </p>
    @if($lipReport['verdict'] === 'not_matched')<p style="margin: 4px 0 0; font-size: 10px; color: #B4232A">The moments are listed in the lip-sync report on the first page.</p>@endif
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
