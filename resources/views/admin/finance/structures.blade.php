@extends(auth()->user()->role_id == 4 ? 'accountant.navigation' : 'admin.navigation')

@section('content')
@php $cur = get_settings('system_currency') ?: 'USD'; @endphp
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ get_phrase('Fee structures') }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2">
          <li><a href="#">{{ get_phrase('Finance') }}</a></li>
          <li><a href="#">{{ get_phrase('Fee structures') }}</a></li>
        </ul>
      </div>
      <div class="export-btn-area">
        <a href="{{ route('admin.finance.structure.create') }}" class="export_btn"><i class="bi bi-plus"></i>{{ get_phrase('New structure') }}</a>
      </div>
    </div>
  </div></div>
</div>

<div class="row"><div class="col-12"><div class="eSection-wrap">
  <div class="table-responsive">
    <table class="table eTable eTable-2">
      <thead><tr>
        <th>{{ get_phrase('Title') }}</th><th>{{ get_phrase('Class') }}</th>
        <th>{{ get_phrase('Heads') }}</th><th>{{ get_phrase('Total') }}</th>
        <th>{{ get_phrase('Due date') }}</th><th class="text-end">{{ get_phrase('Options') }}</th>
      </tr></thead>
      <tbody>
        @forelse($structures as $s)
          @php
            $cls = \App\Models\Classes::find($s->class_id);
            $items = \App\Models\FeeStructureItem::where('structure_id',$s->id)->get();
          @endphp
          <tr>
            <td>{{ $s->title }}</td>
            <td>{{ $cls->name ?? '-' }}</td>
            <td>{{ $items->count() }}</td>
            <td><b>{{ $cur }} {{ number_format($items->sum('amount'),2) }}</b></td>
            <td>{{ $s->due_date ? date('d M Y', $s->due_date) : '—' }}</td>
            <td class="text-end">
              <a class="eBtn btn-primary" href="{{ route('admin.finance.structure.show', $s->id) }}">{{ get_phrase('Open') }}</a>
              <a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this structure?') }}')) postDelete('{{ route('admin.finance.structure.delete', $s->id) }}')">{{ get_phrase('Delete') }}</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted">{{ get_phrase('No fee structures yet. Create one to bill a class.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div></div></div>
@endsection
