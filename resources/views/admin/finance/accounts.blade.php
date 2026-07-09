@extends(auth()->user()->role_id == 4 ? 'accountant.navigation' : 'admin.navigation')

@section('content')
@php $cur = get_settings('system_currency') ?: 'USD'; $icons=['cash'=>'bi-cash-stack','bank'=>'bi-bank','mobile'=>'bi-phone']; @endphp
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ get_phrase('Accounts') }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2"><li><a href="#">{{ get_phrase('Finance') }}</a></li><li><a href="#">{{ get_phrase('Accounts') }}</a></li></ul>
      </div>
      <a class="eBtn btn-primary" href="{{ route('admin.finance.transfers') }}"><i class="bi bi-arrow-left-right"></i> {{ get_phrase('Transfers') }}</a>
    </div>
  </div></div>
</div>

<div class="row">
  <div class="col-lg-4 mb-3"><div class="eSection-wrap">
    <h6 class="mb-3">{{ get_phrase('Add account') }}</h6>
    <form method="POST" action="{{ route('admin.finance.account.store') }}">
      @csrf
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Name') }}</label>
        <input type="text" name="name" class="form-control eForm-control" placeholder="{{ get_phrase('e.g. Equity Bank, Petty cash, M-Pesa till') }}" required></div>
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Type') }}</label>
        <select name="type" class="form-select eForm-select"><option value="bank">{{ get_phrase('Bank') }}</option><option value="cash">{{ get_phrase('Cash') }}</option><option value="mobile">{{ get_phrase('Mobile money') }}</option></select></div>
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Opening balance') }} ({{ $cur }})</label>
        <input type="number" step="0.01" name="opening_balance" class="form-control eForm-control" value="0"></div>
      <button class="btn-form mt-2" type="submit">{{ get_phrase('Add account') }}</button>
    </form>
  </div></div>

  <div class="col-lg-8 mb-3">
    <div class="eSection-wrap mb-3 d-flex justify-content-between align-items-center">
      <h6 class="mb-0">{{ get_phrase('Total cash position') }}</h6>
      <b style="font-size:22px; color:#00955f;">{{ $cur }} {{ number_format($totalCash,2) }}</b>
    </div>
    <div class="row">
      @forelse($accounts as $a)
        <div class="col-md-6 mb-3"><div class="eSection-wrap h-100">
          <div class="d-flex justify-content-between align-items-start">
            <div><i class="bi {{ $icons[$a->type] ?? 'bi-wallet2' }}" style="font-size:22px;color:#00955f;"></i>
              <div style="font-weight:600;">{{ $a->name }}</div>
              <small class="text-muted">{{ ucfirst($a->type) }}</small></div>
            <a class="text-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this account?') }}')) postDelete('{{ route('admin.finance.account.delete', $a->id) }}')"><i class="bi bi-x-circle"></i></a>
          </div>
          <div class="mt-2" style="font-size:20px; font-weight:700; color:{{ $a->balance>=0 ? '#181c32':'#f04b24' }};">{{ $cur }} {{ number_format($a->balance,2) }}</div>
          <small class="text-muted">{{ get_phrase('Opening') }}: {{ number_format($a->opening_balance,2) }}</small>
        </div></div>
      @empty
        <div class="col-12"><div class="eSection-wrap text-center text-muted py-4">{{ get_phrase('No accounts yet. Add your bank, cash and mobile-money accounts.') }}</div></div>
      @endforelse
    </div>
  </div>
</div>
@endsection
