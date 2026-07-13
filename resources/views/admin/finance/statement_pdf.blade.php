<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
@php
  $cur = get_settings('system_currency') ?: 'USD';
  $months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
@endphp
<style>
  * { font-family: DejaVu Sans, sans-serif; }
  body { margin:0; color:#2b2f3a; font-size:11px; }
  .header { border-bottom:3px solid #00955f; padding-bottom:12px; margin-bottom:16px; }
  .header table { width:100%; }
  .header .logo { width:80px; }
  .header .org { font-size:15px; font-weight:bold; color:#00955f; }
  .header .addr { font-size:9px; color:#777; }
  .doc { text-align:right; }
  .doc .t { font-size:14px; font-weight:bold; }
  .doc .s { font-size:10px; color:#555; }
  .meta { background:#f5faf8; border:1px solid #e4efe9; border-radius:5px; padding:8px 12px; margin-bottom:14px; font-size:11px; }
  .meta span { margin-right:20px; } .meta b { color:#00955f; }
  table.st { width:100%; border-collapse:collapse; margin-bottom:12px; }
  table.st th, table.st td { border:1px solid #e0e6e2; padding:6px 8px; font-size:10px; }
  table.st td.r, table.st th.r { text-align:right; }
  table.st thead th { color:#fff; }
  table.st.inc thead th { background:#0a8f5b; }
  table.st.exp thead th { background:#c0563b; }
  table.st tfoot td { font-weight:bold; background:#f5faf8; }
  .netbox { padding:10px 12px; border-radius:6px; margin-bottom:18px; }
  .netbox .l { font-size:12px; font-weight:bold; } .netbox .v { font-size:14px; font-weight:bold; float:right; }
  table.bd { width:100%; border-collapse:collapse; }
  table.bd th, table.bd td { border:1px solid #e0e6e2; padding:5px 8px; font-size:9.5px; }
  table.bd th { background:#00955f; color:#fff; }
  table.bd td.r { text-align:right; }
  table.bd tr.q td { background:#eef4f9; font-weight:bold; }
  table.bd tfoot td { font-weight:bold; background:#f5faf8; border-top:2px solid #00955f; }
  .sec { font-size:12px; font-weight:bold; color:#00955f; margin:6px 0 6px; }
  .footer { position:fixed; bottom:0; left:0; right:0; text-align:center; font-size:8px; color:#999; border-top:1px solid #eee; padding-top:5px; }
</style>
</head>
<body>
  <div class="header"><table><tr>
    <td style="width:58%;">
      @if($logoPath)<img class="logo" src="{{ $logoPath }}">@endif
      <div class="org">{{ $school->title ?? 'School' }}</div>
      <div class="addr">{{ $school->address ?? '' }}{{ ($school->phone ?? '') ? ' · '.$school->phone : '' }}</div>
    </td>
    <td class="doc" style="width:42%;">
      <div class="t">FINANCIAL STATEMENT</div>
      <div class="s">{{ $label }}</div>
      <div class="s">{{ date('d M Y',$from) }} — {{ date('d M Y',$to) }}</div>
      <div class="s">Generated {{ date('d M Y') }}</div>
    </td>
  </tr></table></div>

  <div class="meta">
    <span><b>Statement:</b> Income &amp; Expenditure</span>
    <span><b>Period:</b> {{ $label }}</span>
    <span><b>Currency:</b> {{ $cur }}</span>
  </div>

  <div class="sec">Income</div>
  <table class="st inc">
    <thead><tr><th>Category</th><th class="r">Amount ({{ $cur }})</th></tr></thead>
    <tbody>
      @forelse($income as $c)<tr><td>{{ $c->category ?: 'Uncategorised' }}</td><td class="r">{{ number_format($c->t,2) }}</td></tr>@empty<tr><td colspan="2">No income in this period.</td></tr>@endforelse
    </tbody>
    <tfoot><tr><td>Total income</td><td class="r">{{ number_format($totalIncome,2) }}</td></tr></tfoot>
  </table>

  <div class="sec">Expenses</div>
  <table class="st exp">
    <thead><tr><th>Category</th><th class="r">Amount ({{ $cur }})</th></tr></thead>
    <tbody>
      @forelse($expense as $c)<tr><td>{{ $c->category ?: 'Uncategorised' }}</td><td class="r">{{ number_format($c->t,2) }}</td></tr>@empty<tr><td colspan="2">No expenses in this period.</td></tr>@endforelse
    </tbody>
    <tfoot><tr><td>Total expenses</td><td class="r">{{ number_format($totalExpense,2) }}</td></tr></tfoot>
  </table>

  <div class="netbox" style="background:{{ $net>=0 ? '#e8f5ef':'#fdeee9' }};">
    <span class="l">{{ $net>=0 ? 'Net surplus' : 'Net deficit' }}</span>
    <span class="v" style="color:{{ $net>=0 ? '#00955f':'#c0392b' }};">{{ $cur }} {{ number_format($net,2) }}</span>
    <div style="clear:both;"></div>
  </div>

  <div class="sec">Periodic breakdown — {{ $year }}</div>
  <table class="bd">
    <thead><tr><th>Period</th><th class="r">Income</th><th class="r">Expenses</th><th class="r">Net</th></tr></thead>
    <tbody>
      @foreach($months as $mi=>$mn)
        @php $r = $monthly[$mi]; @endphp
        <tr>
          <td>{{ $mn }}</td>
          <td class="r">{{ number_format($r['income'],2) }}</td>
          <td class="r">{{ number_format($r['expense'],2) }}</td>
          <td class="r">{{ number_format($r['net'],2) }}</td>
        </tr>
        @if($mi % 3 == 0)
          @php $q = intdiv($mi,3); $qr = $quarterly[$q]; @endphp
          <tr class="q">
            <td>Quarter {{ $q }} subtotal</td>
            <td class="r">{{ number_format($qr['income'],2) }}</td>
            <td class="r">{{ number_format($qr['expense'],2) }}</td>
            <td class="r">{{ number_format($qr['net'],2) }}</td>
          </tr>
        @endif
      @endforeach
    </tbody>
    <tfoot><tr>
      <td>Year total {{ $year }}</td>
      <td class="r">{{ number_format($yearTotals['income'],2) }}</td>
      <td class="r">{{ number_format($yearTotals['expense'],2) }}</td>
      <td class="r">{{ number_format($yearTotals['net'],2) }}</td>
    </tr></tfoot>
  </table>

  <div class="footer">{{ $school->title ?? '' }} — Financial Statement ({{ $label }}) · Generated {{ date('d M Y') }}</div>
</body>
</html>
