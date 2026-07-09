@extends(auth()->user()->role_id == 4 ? 'accountant.navigation' : 'admin.navigation')

@section('content')
@php $cur = get_settings('system_currency') ?: 'USD'; @endphp
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ get_phrase('Payslips') }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2"><li><a href="#">{{ get_phrase('Finance') }}</a></li><li><a href="{{ route('admin.finance.payroll') }}">{{ get_phrase('Payroll') }}</a></li><li><a href="#">{{ get_phrase('Payslips') }}</a></li></ul>
      </div>
      <a class="eBtn btn-secondary" href="{{ route('admin.finance.payroll') }}">{{ get_phrase('Salary structures') }}</a>
    </div>
  </div></div>
</div>

<div class="row"><div class="col-12"><div class="eSection-wrap">
  <div class="d-flex justify-content-between align-items-end flex-wrap mb-3" style="gap:10px;">
    <form method="GET" action="{{ route('admin.finance.payslips') }}" class="d-flex align-items-end" style="gap:8px;">
      <div><label class="eForm-label">{{ get_phrase('Month') }}</label><input type="month" name="month" value="{{ $month }}" class="form-control eForm-control"></div>
      <button class="eBtn btn-primary" type="submit">{{ get_phrase('View') }}</button>
    </form>
    <form method="POST" action="{{ route('admin.finance.payslips.generate') }}">
      @csrf<input type="hidden" name="month" value="{{ $month }}">
      <button class="eBtn btn-secondary" type="submit" onclick="return confirm('{{ get_phrase('Generate payslips for all staff with a salary structure for') }} {{ $month }}?')">
        <i class="bi bi-magic"></i> {{ get_phrase('Generate for') }} {{ $month }}</button>
    </form>
  </div>

  <div class="d-flex mb-2" style="gap:24px; font-size:14px;">
    <span>{{ get_phrase('Payslips') }}: <b>{{ $slips->count() }}</b></span>
    <span>{{ get_phrase('Total net') }}: <b>{{ $cur }} {{ number_format($totals['net'],2) }}</b></span>
    <span>{{ get_phrase('Paid') }}: <b class="text-success">{{ $cur }} {{ number_format($totals['paid'],2) }}</b></span>
  </div>

  <div class="table-responsive">
    <table class="table eTable eTable-2">
      <thead><tr><th>{{ get_phrase('Staff') }}</th><th class="text-end">{{ get_phrase('Basic') }}</th><th class="text-end">{{ get_phrase('Allowances') }}</th><th class="text-end">{{ get_phrase('Deductions') }}</th><th class="text-end">{{ get_phrase('Net pay') }}</th><th>{{ get_phrase('Status') }}</th><th class="text-end">{{ get_phrase('Options') }}</th></tr></thead>
      <tbody>
        @forelse($slips as $p)
          @php $s = $staffById[$p->staff_id] ?? null; @endphp
          <tr>
            <td>{{ optional($s)->name ?? '—' }}</td>
            <td class="text-end">{{ number_format($p->basic,2) }}</td>
            <td class="text-end text-success">{{ number_format($p->allowances_total,2) }}</td>
            <td class="text-end" style="color:#f04b24;">{{ number_format($p->deductions_total,2) }}</td>
            <td class="text-end"><b>{{ $cur }} {{ number_format($p->net_pay,2) }}</b></td>
            <td><span class="badge {{ $p->status==='paid'?'bg-success':'bg-secondary' }}">{{ ucfirst($p->status) }}</span></td>
            <td class="text-end">
              <a class="eBtn btn-secondary" target="_blank" href="{{ route('admin.finance.payslip.show', $p->id) }}">{{ get_phrase('Slip') }}</a>
              @if($p->status!=='paid')<a class="eBtn btn-primary" href="{{ route('admin.finance.payslip.pay', $p->id) }}" onclick="return confirm('{{ get_phrase('Mark as paid? This posts to the finance ledger.') }}')">{{ get_phrase('Pay') }}</a>@endif
              <a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete payslip?') }}')) postDelete('{{ route('admin.finance.payslip.delete', $p->id) }}')">{{ get_phrase('Delete') }}</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted">{{ get_phrase('No payslips for this month. Click "Generate" to create them.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div></div></div>
@endsection
