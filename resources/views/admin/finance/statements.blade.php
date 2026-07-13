@extends(auth()->user()->role_id == 4 ? 'accountant.navigation' : 'admin.navigation')

@section('content')
@php
  $cur = get_settings('system_currency') ?: 'USD';
  $months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
  $pdfParams = ['period'=>$period,'year'=>$year,'month'=>$month,'quarter'=>$quarter];
@endphp
<style>@media print{ .no-print{display:none!important;} .mainSection-title{display:none;} }</style>
<div class="mainSection-title no-print">
  <div class="row"><div class="col-12"><div class="d-flex flex-column">
    <h4>{{ get_phrase('Financial statements') }}</h4>
    <ul class="d-flex align-items-center eBreadcrumb-2"><li><a href="#">{{ get_phrase('Finance') }}</a></li><li><a href="#">{{ get_phrase('Statements') }}</a></li></ul>
  </div></div></div>
</div>

<div class="no-print">@include('admin.finance._tabs')</div>

<div class="row"><div class="col-lg-10 offset-lg-0"><div class="eSection-wrap">

  <form method="GET" class="row g-2 mb-3 no-print" action="{{ route('admin.finance.statements') }}">
    <div class="col-md-3">
      <label class="eForm-label">{{ get_phrase('Period') }}</label>
      <select name="period" id="stmt-period" class="form-control eForm-control" onchange="stmtToggle()">
        <option value="month"   {{ $period=='month'   ? 'selected':'' }}>{{ get_phrase('Monthly') }}</option>
        <option value="quarter" {{ $period=='quarter' ? 'selected':'' }}>{{ get_phrase('Quarterly') }}</option>
        <option value="year"    {{ $period=='year'    ? 'selected':'' }}>{{ get_phrase('Yearly') }}</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="eForm-label">{{ get_phrase('Year') }}</label>
      <select name="year" class="form-control eForm-control">
        @foreach($years as $y)<option value="{{ $y }}" {{ $year==$y ? 'selected':'' }}>{{ $y }}</option>@endforeach
      </select>
    </div>
    <div class="col-md-2" id="stmt-month-wrap">
      <label class="eForm-label">{{ get_phrase('Month') }}</label>
      <select name="month" class="form-control eForm-control">
        @foreach($months as $mi=>$mn)<option value="{{ $mi }}" {{ $month==$mi ? 'selected':'' }}>{{ get_phrase($mn) }}</option>@endforeach
      </select>
    </div>
    <div class="col-md-2" id="stmt-quarter-wrap">
      <label class="eForm-label">{{ get_phrase('Quarter') }}</label>
      <select name="quarter" class="form-control eForm-control">
        @for($q=1;$q<=4;$q++)<option value="{{ $q }}" {{ $quarter==$q ? 'selected':'' }}>Q{{ $q }}</option>@endfor
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end" style="gap:8px;">
      <button class="eBtn btn-primary" type="submit">{{ get_phrase('Apply') }}</button>
      <button type="button" class="eBtn btn-secondary" onclick="window.print()"><i class="bi bi-printer"></i> {{ get_phrase('Print') }}</button>
      <a class="eBtn btn-secondary" href="{{ route('admin.finance.statements.pdf', $pdfParams) }}"><i class="bi bi-file-earmark-pdf"></i> {{ get_phrase('PDF') }}</a>
    </div>
  </form>

  {{-- ---------------- Income & Expenditure statement for the selected period ---------------- --}}
  <h5 class="text-center mb-1">{{ get_phrase('Income & Expenditure Statement') }}</h5>
  <p class="text-center text-muted mb-4" style="font-size:13px;">
    <b>{{ $label }}</b> &nbsp;·&nbsp; {{ date('d M Y',$from) }} — {{ date('d M Y',$to) }}
  </p>

  <table class="table eTable eTable-2">
    <thead><tr><th style="background:#e8f5ef;">{{ get_phrase('Income') }}</th><th class="text-end" style="background:#e8f5ef;"></th></tr></thead>
    <tbody>
      @forelse($income as $c)<tr><td>{{ $c->category ?: get_phrase('Uncategorised') }}</td><td class="text-end">{{ $cur }} {{ number_format($c->t,2) }}</td></tr>@empty<tr><td colspan="2" class="text-muted">{{ get_phrase('No income in this period.') }}</td></tr>@endforelse
    </tbody>
    <tfoot><tr><th>{{ get_phrase('Total income') }}</th><th class="text-end text-success">{{ $cur }} {{ number_format($totalIncome,2) }}</th></tr></tfoot>
  </table>

  <table class="table eTable eTable-2 mt-3">
    <thead><tr><th style="background:#fdeee9;">{{ get_phrase('Expenses') }}</th><th class="text-end" style="background:#fdeee9;"></th></tr></thead>
    <tbody>
      @forelse($expense as $c)<tr><td>{{ $c->category ?: get_phrase('Uncategorised') }}</td><td class="text-end">{{ $cur }} {{ number_format($c->t,2) }}</td></tr>@empty<tr><td colspan="2" class="text-muted">{{ get_phrase('No expenses in this period.') }}</td></tr>@endforelse
    </tbody>
    <tfoot><tr><th>{{ get_phrase('Total expenses') }}</th><th class="text-end" style="color:#f04b24;">{{ $cur }} {{ number_format($totalExpense,2) }}</th></tr></tfoot>
  </table>

  <div class="d-flex justify-content-between align-items-center mt-3 p-3" style="background:{{ $net>=0 ? '#e8f5ef':'#fdeee9' }}; border-radius:10px;">
    <b style="font-size:16px;">{{ $net>=0 ? get_phrase('Net surplus') : get_phrase('Net deficit') }}</b>
    <b style="font-size:18px; color:{{ $net>=0 ? '#00955f':'#f04b24' }};">{{ $cur }} {{ number_format($net,2) }}</b>
  </div>

  {{-- ---------------- Periodic breakdown: month → quarter → year ---------------- --}}
  <h6 class="mt-5 mb-2"><i class="bi bi-calendar3 me-1"></i> {{ get_phrase('Periodic breakdown') }} — {{ $year }}</h6>
  <div class="table-responsive">
  <table class="table eTable eTable-2" style="font-size:13.5px;">
    <thead><tr>
      <th>{{ get_phrase('Period') }}</th>
      <th class="text-end">{{ get_phrase('Income') }}</th>
      <th class="text-end">{{ get_phrase('Expenses') }}</th>
      <th class="text-end">{{ get_phrase('Net') }}</th>
    </tr></thead>
    <tbody>
      @foreach($months as $mi=>$mn)
        @php $r = $monthly[$mi]; @endphp
        <tr>
          <td>{{ get_phrase($mn) }}</td>
          <td class="text-end">{{ number_format($r['income'],2) }}</td>
          <td class="text-end">{{ number_format($r['expense'],2) }}</td>
          <td class="text-end" style="color:{{ $r['net']>=0 ? '#00955f':'#f04b24' }};">{{ number_format($r['net'],2) }}</td>
        </tr>
        @if($mi % 3 == 0)
          @php $q = intdiv($mi,3); $qr = $quarterly[$q]; @endphp
          <tr style="background:#f4f8fb; font-weight:700;">
            <td>{{ get_phrase('Quarter') }} {{ $q }} {{ get_phrase('subtotal') }}</td>
            <td class="text-end">{{ number_format($qr['income'],2) }}</td>
            <td class="text-end">{{ number_format($qr['expense'],2) }}</td>
            <td class="text-end" style="color:{{ $qr['net']>=0 ? '#00955f':'#f04b24' }};">{{ number_format($qr['net'],2) }}</td>
          </tr>
        @endif
      @endforeach
    </tbody>
    <tfoot>
      <tr style="font-weight:800; border-top:2px solid #00955f;">
        <th>{{ get_phrase('Year total') }} {{ $year }}</th>
        <th class="text-end text-success">{{ $cur }} {{ number_format($yearTotals['income'],2) }}</th>
        <th class="text-end" style="color:#f04b24;">{{ $cur }} {{ number_format($yearTotals['expense'],2) }}</th>
        <th class="text-end" style="color:{{ $yearTotals['net']>=0 ? '#00955f':'#f04b24' }};">{{ $cur }} {{ number_format($yearTotals['net'],2) }}</th>
      </tr>
    </tfoot>
  </table>
  </div>

</div></div></div>

<script>
  function stmtToggle(){
    var p = document.getElementById('stmt-period').value;
    document.getElementById('stmt-month-wrap').style.display   = (p === 'month')   ? '' : 'none';
    document.getElementById('stmt-quarter-wrap').style.display = (p === 'quarter') ? '' : 'none';
  }
  stmtToggle();
</script>
@endsection
