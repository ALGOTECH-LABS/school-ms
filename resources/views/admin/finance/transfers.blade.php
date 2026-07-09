@extends(auth()->user()->role_id == 4 ? 'accountant.navigation' : 'admin.navigation')

@section('content')
@php $cur = get_settings('system_currency') ?: 'USD'; @endphp
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ get_phrase('Transfers') }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2"><li><a href="#">{{ get_phrase('Finance') }}</a></li><li><a href="{{ route('admin.finance.accounts') }}">{{ get_phrase('Accounts') }}</a></li><li><a href="#">{{ get_phrase('Transfers') }}</a></li></ul>
      </div>
      <a class="eBtn btn-secondary" href="{{ route('admin.finance.accounts') }}">{{ get_phrase('Accounts') }}</a>
    </div>
  </div></div>
</div>

<div class="row">
  <div class="col-lg-4 mb-3"><div class="eSection-wrap">
    <h6 class="mb-3">{{ get_phrase('New transfer') }}</h6>
    @if($accounts->count() < 2)
      <p class="text-muted">{{ get_phrase('You need at least two accounts to transfer between them.') }}</p>
    @else
    <form method="POST" action="{{ route('admin.finance.transfer.store') }}">
      @csrf
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('From account') }}</label>
        <select name="from_account_id" class="form-select eForm-select" required>
          @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></div>
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('To account') }}</label>
        <select name="to_account_id" class="form-select eForm-select" required>
          @foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></div>
      <div class="fpb-7 d-flex" style="gap:10px;">
        <div style="flex:1;"><label class="eForm-label">{{ get_phrase('Amount') }} ({{ $cur }})</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control eForm-control" required></div>
        <div style="flex:1;"><label class="eForm-label">{{ get_phrase('Date') }}</label><input type="date" name="transfer_date" class="form-control eForm-control" value="{{ date('Y-m-d') }}"></div>
      </div>
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Description') }}</label>
        <input type="text" name="description" class="form-control eForm-control"></div>
      <button class="btn-form mt-2" type="submit">{{ get_phrase('Record transfer') }}</button>
    </form>
    @endif
  </div></div>

  <div class="col-lg-8 mb-3"><div class="eSection-wrap">
    <h6 class="mb-3">{{ get_phrase('Transfer history') }}</h6>
    <div class="table-responsive">
      <table class="table eTable eTable-2">
        <thead><tr><th>{{ get_phrase('Date') }}</th><th>{{ get_phrase('From') }}</th><th>{{ get_phrase('To') }}</th><th class="text-end">{{ get_phrase('Amount') }}</th><th class="text-end">{{ get_phrase('Options') }}</th></tr></thead>
        <tbody>
          @forelse($transfers as $t)
            @php $fa=\App\Models\Account::find($t->from_account_id); $ta=\App\Models\Account::find($t->to_account_id); @endphp
            <tr>
              <td>{{ $t->transfer_date ? date('d M Y',$t->transfer_date) : '' }}<br>@if($t->description)<small class="text-muted">{{ $t->description }}</small>@endif</td>
              <td>{{ optional($fa)->name ?? '—' }}</td>
              <td><i class="bi bi-arrow-right text-success"></i> {{ optional($ta)->name ?? '—' }}</td>
              <td class="text-end"><b>{{ $cur }} {{ number_format($t->amount,2) }}</b></td>
              <td class="text-end"><a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this transfer?') }}')) postDelete('{{ route('admin.finance.transfer.delete', $t->id) }}')">{{ get_phrase('Delete') }}</a></td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted">{{ get_phrase('No transfers yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-2">{{ $transfers->links() }}</div>
  </div></div>
</div>
@endsection
