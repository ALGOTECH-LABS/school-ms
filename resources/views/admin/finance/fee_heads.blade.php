@extends(auth()->user()->role_id == 4 ? 'accountant.navigation' : 'admin.navigation')

@section('content')
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex flex-column">
      <h4>{{ get_phrase('Fee heads') }}</h4>
      <ul class="d-flex align-items-center eBreadcrumb-2">
        <li><a href="#">{{ get_phrase('Finance') }}</a></li>
        <li><a href="#">{{ get_phrase('Fee heads') }}</a></li>
      </ul>
    </div>
  </div></div>
</div>

<div class="row">
  <div class="col-lg-5 mb-3">
    <div class="eSection-wrap">
      <h6 class="mb-3">{{ get_phrase('Add fee head') }}</h6>
      <form method="POST" action="{{ route('admin.finance.fee_head.store') }}">
        @csrf
        <div class="fpb-7">
          <label class="eForm-label">{{ get_phrase('Name') }}</label>
          <input type="text" name="name" class="form-control eForm-control" placeholder="{{ get_phrase('e.g. Tuition, Lab, Library') }}" required>
        </div>
        <div class="fpb-7">
          <label class="eForm-label">{{ get_phrase('Description (optional)') }}</label>
          <input type="text" name="description" class="form-control eForm-control">
        </div>
        <button class="btn-form mt-2" type="submit">{{ get_phrase('Add fee head') }}</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7 mb-3">
    <div class="eSection-wrap">
      <h6 class="mb-3">{{ get_phrase('Fee heads') }} ({{ $fee_heads->count() }})</h6>
      <div class="table-responsive">
        <table class="table eTable eTable-2">
          <thead><tr><th>{{ get_phrase('Name') }}</th><th>{{ get_phrase('Description') }}</th><th class="text-end">{{ get_phrase('Options') }}</th></tr></thead>
          <tbody>
            @forelse($fee_heads as $h)
              <tr>
                <td>{{ $h->name }}</td>
                <td class="text-muted">{{ $h->description ?: '—' }}</td>
                <td class="text-end"><a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this fee head?') }}')) postDelete('{{ route('admin.finance.fee_head.delete', $h->id) }}')">{{ get_phrase('Delete') }}</a></td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted">{{ get_phrase('No fee heads yet. Add Tuition, Exam, Lab, etc.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
