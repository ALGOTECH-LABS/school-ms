@extends(auth()->user()->role_id == 4 ? 'accountant.navigation' : 'admin.navigation')

@section('content')
@php $cur = get_settings('system_currency') ?: 'USD'; @endphp
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ get_phrase('School projects') }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2"><li><a href="#">{{ get_phrase('Finance') }}</a></li><li><a href="#">{{ get_phrase('Projects') }}</a></li></ul>
      </div>
    </div>
  </div></div>
</div>

<div class="row">
  <div class="col-lg-4 mb-3"><div class="eSection-wrap">
    <h6 class="mb-3">{{ get_phrase('New project') }}</h6>
    <form method="POST" action="{{ route('admin.finance.project.store') }}">
      @csrf
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Name') }}</label>
        <input type="text" name="name" class="form-control eForm-control" placeholder="{{ get_phrase('e.g. New Library Block') }}" required></div>
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Description') }}</label>
        <textarea name="description" class="form-control eForm-control" rows="2"></textarea></div>
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Budget') }} ({{ $cur }})</label>
        <input type="number" step="0.01" min="0" name="budget_amount" class="form-control eForm-control" required></div>
      <div class="fpb-7 d-flex" style="gap:10px;">
        <div style="flex:1;"><label class="eForm-label">{{ get_phrase('Start') }}</label><input type="date" name="start_date" class="form-control eForm-control"></div>
        <div style="flex:1;"><label class="eForm-label">{{ get_phrase('End') }}</label><input type="date" name="end_date" class="form-control eForm-control"></div>
      </div>
      <div class="fpb-7"><label class="eForm-label">{{ get_phrase('Status') }}</label>
        <select name="status" class="form-select eForm-select">
          <option value="planning">{{ get_phrase('Planning') }}</option>
          <option value="ongoing" selected>{{ get_phrase('Ongoing') }}</option>
          <option value="completed">{{ get_phrase('Completed') }}</option>
        </select></div>
      <button class="btn-form mt-2" type="submit">{{ get_phrase('Create project') }}</button>
    </form>
  </div></div>

  <div class="col-lg-8 mb-3">
    @forelse($projects as $p)
      @php
        $funded = (float)\App\Models\ProjectTransaction::where('project_id',$p->id)->where('type','funding')->sum('amount');
        $spent = (float)\App\Models\ProjectTransaction::where('project_id',$p->id)->where('type','expense')->sum('amount');
        $pct = $p->budget_amount>0 ? min(100, round($spent*100/$p->budget_amount)) : 0;
        $sc = ['planning'=>'bg-secondary','ongoing'=>'bg-primary','completed'=>'bg-success'];
      @endphp
      <div class="eSection-wrap mb-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:8px;">
          <div>
            <h5 class="mb-1">{{ $p->name }} <span class="badge {{ $sc[$p->status] ?? 'bg-secondary' }}">{{ ucfirst($p->status) }}</span></h5>
            <p class="text-muted mb-2" style="font-size:13px;">{{ \Illuminate\Support\Str::limit($p->description, 90) }}</p>
          </div>
          <div class="d-flex" style="gap:6px;">
            <a class="eBtn btn-primary" href="{{ route('admin.finance.project.show', $p->id) }}">{{ get_phrase('Open') }}</a>
            <a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this project?') }}')) postDelete('{{ route('admin.finance.project.delete', $p->id) }}')">{{ get_phrase('Delete') }}</a>
          </div>
        </div>
        <div class="d-flex flex-wrap" style="gap:24px; font-size:13px;">
          <span>{{ get_phrase('Budget') }}: <b>{{ $cur }} {{ number_format($p->budget_amount,2) }}</b></span>
          <span class="text-success">{{ get_phrase('Funded') }}: <b>{{ $cur }} {{ number_format($funded,2) }}</b></span>
          <span style="color:#f04b24;">{{ get_phrase('Spent') }}: <b>{{ $cur }} {{ number_format($spent,2) }}</b></span>
        </div>
        <div style="height:8px;border-radius:5px;background:#eef0f4;overflow:hidden;margin-top:8px;">
          <span style="display:block;height:100%;width:{{ $pct }}%;background:{{ $spent>$p->budget_amount ? '#f04b24':'#00955f' }};"></span>
        </div>
        <small class="text-muted">{{ $pct }}% {{ get_phrase('of budget spent') }}</small>
      </div>
    @empty
      <div class="eSection-wrap text-center text-muted py-5">{{ get_phrase('No projects yet. Create one on the left (e.g. a new building, bus, lab upgrade).') }}</div>
    @endforelse
  </div>
</div>
@endsection
