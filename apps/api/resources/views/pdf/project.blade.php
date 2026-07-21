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
  h2.section { font-size: 13px; color: #0E3FA9; margin: 20px 0 8px; text-transform: uppercase; letter-spacing: 1px; }
  p.summary { margin: 6px 0; }
  .stack { margin: 4px 0; }
  .chip { display: inline-block; background: #E7F1FE; color: #0E3FA9; border: 1px solid #DCE6F5;
          border-radius: 999px; padding: 3px 10px; font-size: 10.5px; margin: 0 4px 5px 0;
          font-family: DejaVu Sans Mono, monospace; }
  .layer { border: 1px solid #DCE6F5; border-radius: 10px; margin: 0 0 8px; }
  .layer-name { background: #0A1220; color: #fff; font-size: 10px; letter-spacing: 1px; text-transform: uppercase;
                padding: 5px 10px; border-radius: 10px 10px 0 0; font-weight: bold; }
  .comp-row { padding: 8px 10px; }
  .comp { display: inline-block; background: #F6F9FE; border: 1px solid #DCE6F5; border-radius: 8px;
          padding: 5px 9px; margin: 0 6px 6px 0; vertical-align: top; }
  .comp .name { font-weight: bold; font-family: DejaVu Sans Mono, monospace; font-size: 11px; color: #0A1220; }
  .comp .role { font-size: 9.5px; color: #5A6B85; }
  table.flows { border-collapse: collapse; width: 100%; margin: 6px 0; font-size: 11px; }
  table.flows td { border: 1px solid #DCE6F5; padding: 5px 8px; }
  table.flows .from, table.flows .to { font-family: DejaVu Sans Mono, monospace; color: #0A1220; white-space: nowrap; }
  table.flows .arrow { color: #1B6DF0; text-align: center; font-weight: bold; }
  table.flows .label { color: #1B2A44; }
  .foot { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8.5px; color: #5A6B85;
          border-top: 1px solid #DCE6F5; padding-top: 6px; }
</style>
</head>
<body>
  <div class="head">
    <span class="brand">{{ $tenantName }}<span class="ai">.ai</span></span>
    <p class="kicker" style="margin-top:10px">Capstone project · {{ $courseName }}</p>
    <h1 class="title">{{ $title }}</h1>
    <p class="meta">Project brief &amp; architecture · Generated {{ $generatedOn }}</p>
  </div>

  @if ($summary !== '')
    <h2 class="section">The brief</h2>
    <p class="summary">{{ $summary }}</p>
  @endif

  @if (count($techStack) > 0)
    <h2 class="section">Tech stack</h2>
    <div class="stack">
      @foreach ($techStack as $tech)
        <span class="chip">{{ $tech }}</span>
      @endforeach
    </div>
  @endif

  @if (($architecture['overview'] ?? '') !== '' || count($architecture['layers'] ?? []) > 0)
    <h2 class="section">Architecture</h2>
    @if (($architecture['overview'] ?? '') !== '')
      <p class="summary">{{ $architecture['overview'] }}</p>
    @endif

    @foreach ($architecture['layers'] ?? [] as $layer)
      <div class="layer">
        <div class="layer-name">{{ $layer['name'] }}</div>
        <div class="comp-row">
          @foreach ($layer['components'] ?? [] as $c)
            <span class="comp">
              <span class="name">{{ $c['name'] }}</span>
              @if (($c['role'] ?? '') !== '')<br><span class="role">{{ $c['role'] }}</span>@endif
            </span>
          @endforeach
        </div>
      </div>
    @endforeach

    @if (count($architecture['flows'] ?? []) > 0)
      <h2 class="section">Data flow</h2>
      <table class="flows">
        @foreach ($architecture['flows'] as $flow)
          <tr>
            <td class="from">{{ $flow['from'] }}</td>
            <td class="arrow">&rarr;</td>
            <td class="to">{{ $flow['to'] }}</td>
            <td class="label">{{ $flow['label'] ?? '' }}</td>
          </tr>
        @endforeach
      </table>
    @endif
  @endif

  <div class="foot">
    {{ $tenantName }} · Built from real interviews · Capstone brief for enrolled students. Not for redistribution.
  </div>
</body>
</html>
