@extends(auth()->user()->role_id == 4 ? 'accountant.navigation' : 'admin.navigation')

@section('content')
@php $cur = get_settings('system_currency') ?: 'USD'; $badge=['unpaid'=>'bg-danger','partial'=>'bg-primary','paid'=>'bg-success']; @endphp
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ $invoice->invoice_no }} <span class="badge {{ $badge[$invoice->status] ?? 'bg-secondary' }}">{{ ucfirst($invoice->status) }}</span></h4>
        <ul class="d-flex align-items-center eBreadcrumb-2">
          <li><a href="{{ route('admin.finance.invoices') }}">{{ get_phrase('Invoices') }}</a></li>
          <li><a href="#">{{ $invoice->title }}</a></li>
        </ul>
      </div>
      <div class="d-flex" style="gap:8px;">
        <a class="eBtn btn-secondary" href="{{ route('admin.finance.statement', $invoice->student_id) }}">{{ get_phrase('Statement') }}</a>
        <a class="eBtn btn-secondary" href="{{ route('admin.finance.invoices') }}">{{ get_phrase('Back') }}</a>
      </div>
    </div>
  </div></div>
</div>

<div class="row">
  <div class="col-lg-7 mb-3">
    <div class="eSection-wrap mb-3">
      <div class="d-flex justify-content-between flex-wrap">
        <div>
          <p class="mb-1"><b>{{ $student->name ?? '-' }}</b></p>
          <p class="mb-1 text-muted" style="font-size:13px;">{{ $class->name ?? '' }}{{ $section ? ' · '.$section->name : '' }}</p>
          <p class="mb-0 text-muted" style="font-size:13px;">{{ get_phrase('Due') }}: {{ $invoice->due_date ? date('d M Y', $invoice->due_date) : '—' }}</p>
        </div>
      </div>
      <table class="table eTable eTable-2 mt-3">
        <thead><tr><th>{{ get_phrase('Item') }}</th><th class="text-end">{{ get_phrase('Amount') }}</th></tr></thead>
        <tbody>
          @foreach($invoice->items as $it)<tr><td>{{ $it->title }}</td><td class="text-end">{{ $cur }} {{ number_format($it->amount,2) }}</td></tr>@endforeach
          @if($invoice->discount>0)<tr><td class="text-success">{{ get_phrase('Discount') }}</td><td class="text-end text-success">- {{ $cur }} {{ number_format($invoice->discount,2) }}</td></tr>@endif
          @if($invoice->fine>0)<tr><td class="text-danger">{{ get_phrase('Fine') }}</td><td class="text-end text-danger">+ {{ $cur }} {{ number_format($invoice->fine,2) }}</td></tr>@endif
        </tbody>
        <tfoot>
          <tr><th>{{ get_phrase('Total') }}</th><th class="text-end">{{ $cur }} {{ number_format($invoice->total_amount + $invoice->fine - $invoice->discount,2) }}</th></tr>
          <tr><td>{{ get_phrase('Paid') }}</td><td class="text-end text-success">{{ $cur }} {{ number_format($invoice->paid_amount,2) }}</td></tr>
          <tr><th>{{ get_phrase('Balance') }}</th><th class="text-end" style="color:#f04b24;">{{ $cur }} {{ number_format($invoice->balance,2) }}</th></tr>
        </tfoot>
      </table>
    </div>

    <div class="eSection-wrap">
      <h6 class="mb-2">{{ get_phrase('Payment history') }}</h6>
      @forelse($invoice->payments as $p)
        <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px dashed #eef0f4;">
          <div>
            <b>{{ $cur }} {{ number_format($p->amount,2) }}</b> · <span class="text-muted">{{ ucfirst($p->method) }}</span>
            <small class="text-muted d-block">{{ $p->receipt_no }} · {{ $p->paid_on ? date('d M Y', $p->paid_on) : '' }}</small>
          </div>
          <a class="eBtn btn-secondary" target="_blank" href="{{ route('admin.finance.receipt', $p->id) }}">{{ get_phrase('Receipt') }}</a>
        </div>
      @empty
        <p class="text-muted mb-0">{{ get_phrase('No payments yet.') }}</p>
      @endforelse
    </div>
  </div>

  <div class="col-lg-5">
    <div class="eSection-wrap" style="position:sticky; top:20px;">
      <h6 class="mb-3">{{ get_phrase('Record a payment') }}</h6>
      @if($invoice->balance <= 0)
        <div class="text-center text-success py-3"><i class="bi bi-check-circle" style="font-size:26px;"></i><p class="mb-0 mt-2">{{ get_phrase('This invoice is fully paid.') }}</p></div>
      @else
      <form method="POST" action="{{ route('admin.finance.invoice.pay', $invoice->id) }}">
        @csrf
        <div class="fpb-7">
          <label class="eForm-label">{{ get_phrase('Amount') }} ({{ $cur }})</label>
          <input type="number" step="0.01" min="0.01" max="{{ $invoice->balance }}" name="amount" class="form-control eForm-control" value="{{ $invoice->balance }}" required>
        </div>
        <div class="fpb-7">
          <label class="eForm-label">{{ get_phrase('Method') }}</label>
          <select name="method" class="form-select eForm-select">
            @foreach($methods as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach
          </select>
        </div>
        @if($accounts->count())
        <div class="fpb-7">
          <label class="eForm-label">{{ get_phrase('Deposit to account') }}</label>
          <select name="account_id" class="form-select eForm-select"><option value="">{{ get_phrase('— unassigned —') }}</option>
            @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
        </div>
        @endif
        <div class="fpb-7 d-flex" style="gap:10px;">
          <div style="flex:1;"><label class="eForm-label">{{ get_phrase('Discount') }}</label><input type="number" step="0.01" min="0" name="discount" class="form-control eForm-control" value="{{ $invoice->discount }}"></div>
          <div style="flex:1;"><label class="eForm-label">{{ get_phrase('Fine') }}</label><input type="number" step="0.01" min="0" name="fine" class="form-control eForm-control" value="{{ $invoice->fine }}"></div>
        </div>
        <div class="fpb-7">
          <label class="eForm-label">{{ get_phrase('Reference / note') }}</label>
          <input type="text" name="reference" class="form-control eForm-control" placeholder="{{ get_phrase('Txn ref, cheque no, etc.') }}">
        </div>
        <button class="btn-form w-100 mt-2" type="submit"><i class="bi bi-cash-coin"></i> {{ get_phrase('Record payment') }}</button>
      </form>
      @endif
    </div>
  </div>
</div>
@endsection
