<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 34px 40px 48px; }
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; color: #0A1220; font-size: 11.5px; line-height: 1.5; }
  h1 { font-size: 22px; margin: 0 0 2px; }
  h2 { font-size: 12px; letter-spacing: 1.5px; text-transform: uppercase; color: #1B6DF0; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #DCE6F5; }
  .meta { font-size: 10px; color: #5A6B85; margin: 0; }
  .item { margin: 0 0 9px; page-break-inside: avoid; }
  .item .t { font-weight: bold; margin: 0; }
  .item .s { color: #5A6B85; font-size: 10.5px; margin: 0; }
  ul { margin: 3px 0 0 16px; padding: 0; }
  li { margin: 1px 0; }
  .chip { display: inline-block; background: #EEF3FB; color: #34507A; border-radius: 9px; padding: 2px 8px; margin: 0 4px 4px 0; font-size: 10px; }
  .foot { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8.5px; color: #5A6B85; border-top: 1px solid #DCE6F5; padding-top: 6px; }
</style>
</head>
<body>
  <div class="foot">CV from the candidate's BrowseJobs profile · shared for {{ $role }}</div>

  <h1>{{ $name }}</h1>
  <p class="meta">Applying for {{ $role }}</p>

  @if($summary)
    <h2>Summary</h2>
    <p style="margin: 0">{{ $summary }}</p>
  @endif

  @if(count($skills) > 0)
    <h2>Skills</h2>
    <p style="margin: 0">@foreach($skills as $s)<span class="chip">{{ $s }}</span>@endforeach</p>
  @endif

  @if(count($experience) > 0)
    <h2>Experience</h2>
    @foreach($experience as $e)
      <div class="item">
        <p class="t">{{ $e['title'] ?? '' }}@if(! empty($e['company'])) · {{ $e['company'] }}@endif</p>
        @if(! empty($e['period']))<p class="s">{{ is_string($e['period']) ? $e['period'] : '' }}</p>@endif
        @if(! empty($e['bullets']) && is_array($e['bullets']))
          <ul>@foreach($e['bullets'] as $b)@if(is_string($b))<li>{{ $b }}</li>@endif @endforeach</ul>
        @endif
      </div>
    @endforeach
  @endif

  @if(count($projects) > 0)
    <h2>Projects</h2>
    @foreach($projects as $p)
      <div class="item">
        <p class="t">{{ $p['name'] ?? '' }}</p>
        @if(! empty($p['bullets']) && is_array($p['bullets']))
          <ul>@foreach($p['bullets'] as $b)@if(is_string($b))<li>{{ $b }}</li>@endif @endforeach</ul>
        @endif
      </div>
    @endforeach
  @endif

  @if(count($education) > 0)
    <h2>Education</h2>
    @foreach($education as $ed)
      <div class="item">
        <p class="t">{{ $ed['name'] ?? '' }}</p>
        @if(! empty($ed['detail']))<p class="s">{{ is_string($ed['detail']) ? $ed['detail'] : '' }}</p>@endif
      </div>
    @endforeach
  @endif

  @if(count($certifications) > 0)
    <h2>Certifications</h2>
    <ul>@foreach($certifications as $c)<li>{{ is_string($c) ? $c : ($c['name'] ?? '') }}</li>@endforeach</ul>
  @endif
</body>
</html>
