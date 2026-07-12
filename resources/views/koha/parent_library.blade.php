@extends('parent.navigation')
@php $cur = get_settings('system_currency') ?: 'KES'; @endphp

@section('content')
<style>
  .pl-hero{background:linear-gradient(120deg,#00955f,#007a4d);color:#fff;border-radius:14px;padding:20px 24px;margin-bottom:16px;}
  .pl-hero h4{color:#fff;font-weight:800;margin:0;}
  .pl-child{background:#f7fbf9;border:1px solid #d9efe6;border-radius:12px;padding:10px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;}
  .pl-child .nm{font-weight:800;color:#14202b;}
  .pl-pill{display:inline-block;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;background:#e5f7ef;color:#00794c;}
  .pl-pill.red{background:#fdECEC;color:#c0392b;}
  .pl-empty{text-align:center;color:#9aa1b0;padding:14px;}
</style>

<div class="pl-hero d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
  <div><h4><i class="bi bi-bookshelf me-2"></i>{{ get_phrase("Children's Library") }}</h4></div>
  <a class="eBtn" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.4);" href="{{ route('parent.koha.catalog') }}"><i class="bi bi-search"></i> {{ get_phrase('Browse catalog') }}</a>
</div>

@if(!$configured)
  <div class="eSection-wrap"><p class="text-muted mb-0">{{ get_phrase('The library is not connected yet.') }}</p></div>
@elseif(!count($kids))
  <div class="eSection-wrap"><p class="text-muted mb-0">{{ get_phrase('No children linked to your account.') }}</p></div>
@else
  @foreach($kids as $k)
    @php $finesTotal = collect($k['fines'])->sum(fn($f)=>(float)($f['amount_outstanding'] ?? 0)); @endphp
    <div class="eSection-wrap mb-3">
      <div class="pl-child">
        <div><span class="nm">{{ $k['child']->name }}</span>
          @if($k['child']->code)<small class="text-muted"> · {{ $k['child']->code }}</small>@endif</div>
        <div>
          <span class="pl-pill">{{ count($k['loans']) }} {{ get_phrase('on loan') }}</span>
          @if(count($k['fines']))<span class="pl-pill red">{{ $cur }} {{ number_format($finesTotal,2) }} {{ get_phrase('fines') }}</span>@else<span class="pl-pill">{{ get_phrase('No fines') }}</span>@endif
        </div>
      </div>

      @if(count($k['loans']))
        <div class="table-responsive"><table class="table eTable eTable-2 mb-0" style="font-size:13px;">
          <thead><tr><th>{{ get_phrase('Title') }}</th><th>{{ get_phrase('Borrowed') }}</th><th>{{ get_phrase('Due') }}</th><th>{{ get_phrase('Status') }}</th></tr></thead>
          <tbody>
            @foreach($k['loans'] as $l)
              @php $due = isset($l['due_date']) ? strtotime($l['due_date']) : null; $overdue = $due && $due < time(); @endphp
              <tr>
                <td style="font-weight:600;">{{ $l['title'] ?? '—' }}</td>
                <td>{{ isset($l['checkout_date']) ? date('d M Y', strtotime($l['checkout_date'])) : '—' }}</td>
                <td>{{ $due ? date('d M Y', $due) : '—' }}</td>
                <td>@if($overdue)<span class="eBadge ebg-soft-danger">{{ get_phrase('Overdue') }}</span>@else<span class="eBadge ebg-soft-warning">{{ get_phrase('On loan') }}</span>@endif</td>
              </tr>
            @endforeach
          </tbody>
        </table></div>
      @else
        <div class="pl-empty">{{ get_phrase('No books on loan.') }}</div>
      @endif
    </div>
  @endforeach
@endif
@endsection
