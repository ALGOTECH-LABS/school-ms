@php
  $rid = auth()->user()->role_id;
  $nav = [7=>'student.navigation', 3=>'teacher.navigation'][$rid] ?? 'student.navigation';
  $catalogRoute = [7=>'student.koha.catalog', 3=>'teacher.koha.catalog'][$rid] ?? 'student.koha.catalog';
  $cur = get_settings('system_currency') ?: 'KES';
  $finesTotal = collect($fines)->sum(fn($f) => (float)($f['amount_outstanding'] ?? 0));
@endphp
@extends($nav)

@section('content')
<style>
  .ml-hero{background:linear-gradient(120deg,#00955f,#007a4d);color:#fff;border-radius:14px;padding:22px 26px;margin-bottom:18px;}
  .ml-hero h4{color:#fff;font-weight:800;margin:0 0 4px;}
  .ml-pill{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:rgba(255,255,255,.2);}
  .ml-empty{text-align:center;color:#9aa1b0;padding:34px 16px;}
  .ml-empty i{font-size:32px;color:#d3d8e0;}
</style>

<div class="ml-hero d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
  <div>
    <h4><i class="bi bi-bookshelf me-2"></i>{{ get_phrase('My Library') }}</h4>
    <div style="font-size:13px;opacity:.9;">
      <span class="ml-pill">{{ count($loans) }} {{ get_phrase('on loan') }}</span>
      @if(count($fines))<span class="ml-pill" style="background:rgba(255,90,90,.35);">{{ $cur }} {{ number_format($finesTotal,2) }} {{ get_phrase('fines') }}</span>@endif
    </div>
  </div>
  <a class="eBtn" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.4);" href="{{ route($catalogRoute) }}"><i class="bi bi-search"></i> {{ get_phrase('Browse catalog') }}</a>
</div>

<div class="eSection-wrap mb-3">
  <h5 class="mb-3"><i class="bi bi-journal-bookmark me-2" style="color:#00955f;"></i>{{ get_phrase('Books I have borrowed') }}</h5>
  @if(!$configured)
    <p class="text-muted mb-0">{{ get_phrase('The library is not connected yet.') }}</p>
  @elseif(count($loans))
    <div class="table-responsive"><table class="table eTable eTable-2 mb-0" style="font-size:13.5px;">
      <thead><tr><th>{{ get_phrase('Title') }}</th><th>{{ get_phrase('Barcode') }}</th><th>{{ get_phrase('Borrowed') }}</th><th>{{ get_phrase('Due') }}</th><th>{{ get_phrase('Status') }}</th></tr></thead>
      <tbody>
        @foreach($loans as $l)
          @php $due = isset($l['due_date']) ? strtotime($l['due_date']) : null; $overdue = $due && $due < time(); @endphp
          <tr>
            <td style="font-weight:600;">{{ $l['title'] ?? '—' }}</td>
            <td>{{ $l['barcode'] ?? '—' }}</td>
            <td>{{ isset($l['checkout_date']) ? date('d M Y', strtotime($l['checkout_date'])) : '—' }}</td>
            <td>{{ $due ? date('d M Y', $due) : '—' }}</td>
            <td>@if($overdue)<span class="eBadge ebg-soft-danger">{{ get_phrase('Overdue') }}</span>@else<span class="eBadge ebg-soft-warning">{{ get_phrase('On loan') }}</span>@endif</td>
          </tr>
        @endforeach
      </tbody>
    </table></div>
  @else
    <div class="ml-empty"><i class="bi bi-journal"></i><p class="mb-0 mt-2">{{ get_phrase('You have no books on loan.') }}</p></div>
  @endif
</div>

<div class="eSection-wrap">
  <h5 class="mb-3"><i class="bi bi-cash-coin me-2" style="color:#c0392b;"></i>{{ get_phrase('Library fines') }}</h5>
  @if(count($fines))
    <div class="table-responsive"><table class="table eTable eTable-2 mb-0" style="font-size:13.5px;">
      <thead><tr><th>{{ get_phrase('Description') }}</th><th class="text-end">{{ get_phrase('Amount due') }}</th></tr></thead>
      <tbody>
        @foreach($fines as $f)
          <tr><td>{{ $f['description'] ?: get_phrase('Library fine') }}</td>
              <td class="text-end" style="font-weight:700;color:#c0392b;">{{ $cur }} {{ number_format($f['amount_outstanding'] ?? 0, 2) }}</td></tr>
        @endforeach
      </tbody>
    </table></div>
    <small class="text-muted d-block mt-2">{{ get_phrase('Library fines are billed on your fees page — pay them there.') }}</small>
  @else
    <div class="ml-empty"><i class="bi bi-emoji-smile"></i><p class="mb-0 mt-2">{{ get_phrase('No outstanding library fines.') }}</p></div>
  @endif
</div>
@endsection
