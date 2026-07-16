<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Tax Invoice {{ $number }}</title>
<style>
  :root { --ink:#0A1220; --trust:#1B6DF0; --muted:#5A6B85; --line:#DCE6F5; --paper:#F6F9FE; }
  * { box-sizing: border-box; }
  body { font-family: 'Inter', Arial, sans-serif; color: var(--ink); margin: 0; padding: 40px; background:#fff; }
  .doc { max-width: 720px; margin: 0 auto; }
  .head { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid var(--ink); padding-bottom:16px; }
  .brand { font-weight:800; font-size:22px; letter-spacing:-0.02em; }
  .kicker { font-family:'IBM Plex Mono', monospace; text-transform:uppercase; letter-spacing:.15em; font-size:11px; color:var(--trust); }
  .muted { color:var(--muted); font-size:12px; }
  .mono { font-family:'IBM Plex Mono', monospace; }
  table { width:100%; border-collapse:collapse; margin-top:24px; }
  th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--line); font-size:14px; }
  td.amt, th.amt { text-align:right; font-family:'IBM Plex Mono', monospace; }
  .total td { font-weight:700; border-top:2px solid var(--ink); border-bottom:none; }
  .meta { display:flex; gap:48px; margin-top:24px; }
  .foot { margin-top:32px; font-size:11px; color:var(--muted); border-top:1px solid var(--line); padding-top:12px; }
</style>
</head>
<body>
<div class="doc">
  <div class="head">
    <div>
      <div class="brand">{{ $tenantName }}</div>
      <div class="muted">Whitefield, Bengaluru · hello@browsejobs.ai</div>
      <div class="muted mono">GSTIN: [GST] · CIN: [CIN]</div>
    </div>
    <div style="text-align:right;">
      <div class="kicker">Tax Invoice</div>
      <div class="mono" style="font-size:18px; font-weight:700;">{{ $number }}</div>
      <div class="muted mono">{{ $date?->format('d M Y') }}</div>
    </div>
  </div>

  <div class="meta">
    <div>
      <div class="kicker" style="color:var(--muted);">Billed to</div>
      <div style="font-weight:600;">{{ $studentName }}</div>
    </div>
    <div>
      <div class="kicker" style="color:var(--muted);">Item</div>
      <div style="font-weight:600;">{{ $itemName }}</div>
    </div>
  </div>

  <table>
    <thead>
      <tr><th>Description</th><th class="amt">Amount (₹)</th></tr>
    </thead>
    <tbody>
      <tr><td>Taxable value</td><td class="amt">{{ $taxable }}</td></tr>
      <tr><td>CGST @ 9%</td><td class="amt">{{ $cgst }}</td></tr>
      <tr><td>SGST @ 9%</td><td class="amt">{{ $sgst }}</td></tr>
      <tr class="total"><td>Total paid (incl. GST)</td><td class="amt">{{ $total }}</td></tr>
    </tbody>
  </table>

  <div class="foot">
    Every promise in writing · Every call recorded &amp; AI-monitored.<br>
    This is a computer-generated tax invoice. GSTIN and CIN are pending finalisation ([GST]/[CIN]).
  </div>
</div>
</body>
</html>
