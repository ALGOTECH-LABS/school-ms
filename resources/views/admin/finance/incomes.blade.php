@extends(auth()->user()->role_id == 4 ? 'accountant.navigation' : 'admin.navigation')

@section('content')
@php $cur = get_settings('system_currency') ?: 'USD'; @endphp
<div class="mainSection-title">
  <div class="row"><div class="col-12"><div class="d-flex flex-column">
    <h4>{{ get_phrase('Other income') }}</h4>
    <ul class="d-flex align-items-center eBreadcrumb-2"><li><a href="#">{{ get_phrase('Finance') }}</a></li><li><a href="#">{{ get_phrase('Other income') }}</a></li></ul>
  </div></div></div>
</div>

<div class="row">
  <div class="col-lg-4 mb-3"><div class="eSection-wrap">
    <h6 class="mb-3">{{ get_phrase('Record income') }}</h6>
    <form method="POST" action="{{ route('admin.finance.income.store') }}">
      @csrf
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Source') }}</label>
        <input type="text" name="source" list="incsrc" class="form-control eForm-control" placeholder="{{ get_phrase('Donation, Grant, Rental, Hall hire...') }}" required>
        <datalist id="incsrc">@foreach($sources as $s)<option value="{{ $s }}">@endforeach</datalist></div>
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Payer / from') }}</label>
        <input type="text" name="payer" class="form-control eForm-control"></div>
      <div class="fpb-7 d-flex" style="gap:10px;">
        <div style="flex:1;"><label class="eForm-label">{{ get_phrase('Amount') }} ({{ $cur }})</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control eForm-control" required></div>
        <div style="flex:1;"><label class="eForm-label">{{ get_phrase('Date') }}</label><input type="date" name="income_date" class="form-control eForm-control" value="{{ date('Y-m-d') }}"></div>
      </div>
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Description') }}</label>
        <input type="text" name="description" class="form-control eForm-control"></div>
      @if($accounts->count())
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Deposit to account') }}</label>
        <select name="account_id" class="form-select eForm-select"><option value="">{{ get_phrase('— unassigned —') }}</option>
          @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></div>
      @endif
      <button class="btn-form mt-2" type="submit">{{ get_phrase('Record income') }}</button>
    </form>
  </div></div>

  <div class="col-lg-8 mb-3"><div class="eSection-wrap">
    <div class="d-flex justify-content-between mb-2"><h6 class="mb-0">{{ get_phrase('Income records') }}</h6><span>{{ get_phrase('Total') }}: <b class="text-success">{{ $cur }} {{ number_format($total,2) }}</b></span></div>
    <div class="table-responsive">
      <table class="table eTable eTable-2">
        <thead><tr><th>{{ get_phrase('Date') }}</th><th>{{ get_phrase('Source') }}</th><th>{{ get_phrase('Payer') }}</th><th class="text-end">{{ get_phrase('Amount') }}</th><th class="text-end">{{ get_phrase('Options') }}</th></tr></thead>
        <tbody>
          @forelse($incomes as $i)
            <tr>
              <td>{{ $i->income_date ? date('d M Y',$i->income_date) : '' }}</td>
              <td>{{ $i->source }}<br>@if($i->description)<small class="text-muted">{{ $i->description }}</small>@endif</td>
              <td>{{ $i->payer ?: '—' }}</td>
              <td class="text-end"><b class="text-success">{{ $cur }} {{ number_format($i->amount,2) }}</b></td>
              <td class="text-end"><a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this income?') }}')) postDelete('{{ route('admin.finance.income.delete', $i->id) }}')">{{ get_phrase('Delete') }}</a></td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted">{{ get_phrase('No other income recorded yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-2">{{ $incomes->links() }}</div>
  </div></div>
</div>
@endsection
