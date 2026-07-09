@extends(auth()->user()->role_id == 4 ? 'accountant.navigation' : 'admin.navigation')

@section('content')
@php $cur = get_settings('system_currency') ?: 'USD'; @endphp
<div class="mainSection-title">
  <div class="row"><div class="col-12"><div class="d-flex flex-column">
    <h4>{{ get_phrase('Expenses') }}</h4>
    <ul class="d-flex align-items-center eBreadcrumb-2"><li><a href="#">{{ get_phrase('Finance') }}</a></li><li><a href="#">{{ get_phrase('Expenses') }}</a></li></ul>
  </div></div></div>
</div>

<div class="row">
  <div class="col-lg-4 mb-3"><div class="eSection-wrap">
    <h6 class="mb-3">{{ get_phrase('Record expense') }}</h6>
    <form method="POST" action="{{ route('admin.finance.expense.store') }}" enctype="multipart/form-data">
      @csrf
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Category') }}</label>
        <input type="text" name="category" list="expcats" class="form-control eForm-control" placeholder="{{ get_phrase('Salaries, Utilities, Supplies...') }}" required>
        <datalist id="expcats">@foreach($categories as $c)<option value="{{ $c }}">@endforeach</datalist></div>
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Payee / vendor') }}</label>
        <input type="text" name="vendor" class="form-control eForm-control"></div>
      <div class="fpb-7 d-flex" style="gap:10px;">
        <div style="flex:1;"><label class="eForm-label">{{ get_phrase('Amount') }} ({{ $cur }})</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control eForm-control" required></div>
        <div style="flex:1;"><label class="eForm-label">{{ get_phrase('Date') }}</label><input type="date" name="expense_date" class="form-control eForm-control" value="{{ date('Y-m-d') }}"></div>
      </div>
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Description') }}</label>
        <input type="text" name="description" class="form-control eForm-control"></div>
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Receipt (optional)') }}</label>
        <input type="file" name="attachment" class="form-control eForm-control-file"></div>
      @if($accounts->count())
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Paid from account') }}</label>
        <select name="account_id" class="form-select eForm-select"><option value="">{{ get_phrase('— unassigned —') }}</option>
          @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></div>
      @endif
      <button class="btn-form mt-2" type="submit">{{ get_phrase('Record expense') }}</button>
    </form>
  </div></div>

  <div class="col-lg-8 mb-3"><div class="eSection-wrap">
    <div class="d-flex justify-content-between mb-2"><h6 class="mb-0">{{ get_phrase('Expense records') }}</h6><span>{{ get_phrase('Total') }}: <b style="color:#f04b24;">{{ $cur }} {{ number_format($total,2) }}</b></span></div>
    <div class="table-responsive">
      <table class="table eTable eTable-2">
        <thead><tr><th>{{ get_phrase('Date') }}</th><th>{{ get_phrase('Category') }}</th><th>{{ get_phrase('Payee') }}</th><th class="text-end">{{ get_phrase('Amount') }}</th><th class="text-end">{{ get_phrase('Options') }}</th></tr></thead>
        <tbody>
          @forelse($expenses as $e)
            <tr>
              <td>{{ $e->expense_date ? date('d M Y',$e->expense_date) : '' }}</td>
              <td>{{ $e->category }}<br>@if($e->description)<small class="text-muted">{{ $e->description }}</small>@endif</td>
              <td>{{ $e->vendor ?: '—' }}</td>
              <td class="text-end"><b>{{ $cur }} {{ number_format($e->amount,2) }}</b></td>
              <td class="text-end">
                @if($e->attachment)<a class="eBtn btn-secondary" target="_blank" href="{{ asset('assets/uploads/expenses/'.$e->attachment) }}"><i class="bi bi-paperclip"></i></a>@endif
                <a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this expense?') }}')) postDelete('{{ route('admin.finance.expense.delete', $e->id) }}')">{{ get_phrase('Delete') }}</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted">{{ get_phrase('No expenses recorded yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-2">{{ $expenses->links() }}</div>
  </div></div>
</div>
@endsection
