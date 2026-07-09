@extends(auth()->user()->role_id == 4 ? 'accountant.navigation' : 'admin.navigation')

@section('content')
@php $cur = get_settings('system_currency') ?: 'USD'; @endphp
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ get_phrase('Budgets') }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2"><li><a href="#">{{ get_phrase('Finance') }}</a></li><li><a href="#">{{ get_phrase('Budgets') }}</a></li></ul>
      </div>
      <div class="export-btn-area"><a href="{{ route('admin.finance.budget.create') }}" class="export_btn"><i class="bi bi-plus"></i>{{ get_phrase('New budget') }}</a></div>
    </div>
  </div></div>
</div>

<div class="row"><div class="col-12"><div class="eSection-wrap">
  <div class="table-responsive">
    <table class="table eTable eTable-2">
      <thead><tr><th>{{ get_phrase('Budget') }}</th><th>{{ get_phrase('Lines') }}</th><th class="text-end">{{ get_phrase('Planned income') }}</th><th class="text-end">{{ get_phrase('Planned expense') }}</th><th class="text-end">{{ get_phrase('Options') }}</th></tr></thead>
      <tbody>
        @forelse($budgets as $b)
          @php
            $items = \App\Models\BudgetItem::where('budget_id',$b->id)->get();
            $inc = $items->where('type','income')->sum('planned_amount');
            $exp = $items->where('type','expense')->sum('planned_amount');
          @endphp
          <tr>
            <td>{{ $b->title }}<br><small class="text-muted">{{ $b->note }}</small></td>
            <td>{{ $items->count() }}</td>
            <td class="text-end text-success">{{ $cur }} {{ number_format($inc,2) }}</td>
            <td class="text-end" style="color:#f04b24;">{{ $cur }} {{ number_format($exp,2) }}</td>
            <td class="text-end">
              <a class="eBtn btn-primary" href="{{ route('admin.finance.budget.show', $b->id) }}">{{ get_phrase('Open') }}</a>
              <a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this budget?') }}')) postDelete('{{ route('admin.finance.budget.delete', $b->id) }}')">{{ get_phrase('Delete') }}</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted">{{ get_phrase('No budgets yet. Create one to plan income vs expenses.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div></div></div>
@endsection
