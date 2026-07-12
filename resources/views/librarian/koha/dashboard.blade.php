@extends('librarian.navigation')
@section('content')
@php $cur = get_settings('system_currency') ?: 'KES'; @endphp
<style>
  .lib-hero{background:linear-gradient(120deg,#00955f,#007a4d);color:#fff;border-radius:14px;padding:22px 26px;margin-bottom:18px;}
  .lib-hero h4{color:#fff;font-weight:800;margin:0 0 4px;}
  .lib-stat{background:#fff;border:1px solid #eef1f0;border-radius:12px;padding:18px;text-align:center;}
  .lib-stat .n{font-size:24px;font-weight:800;color:#00955f;}
  .lib-stat.warn .n{color:#c0392b;}
  .lib-stat .l{font-size:12px;color:#69707d;text-transform:uppercase;letter-spacing:.4px;}
  .lib-badge{display:inline-block;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;}
</style>

<div class="lib-hero d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
  <div>
    <h4><i class="bi bi-book-half me-2"></i>{{ get_phrase('Library Dashboard') }}</h4>
    <div style="font-size:13px;opacity:.9;">{{ get_phrase('Powered by Koha') }}
      <span class="lib-badge" style="background:{{ $online ? 'rgba(255,255,255,.2)' : '#fdECEC' }};color:{{ $online ? '#fff' : '#c0392b' }};">{{ $online ? get_phrase('Connected') : get_phrase('Offline') }}</span>
    </div>
  </div>
  <div class="d-flex" style="gap:8px;">
    <a class="eBtn" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.4);" href="{{ route('librarian.koha.patron') }}"><i class="bi bi-person-badge"></i> {{ get_phrase('Patron lookup') }}</a>
    <a class="eBtn" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.4);" href="{{ route('librarian.koha.catalog') }}"><i class="bi bi-search"></i> {{ get_phrase('Catalog') }}</a>
  </div>
</div>

<div class="row mb-2">
  <div class="col-6 col-lg-3 mb-3"><div class="lib-stat"><div class="n">{{ $onLoan }}</div><div class="l">{{ get_phrase('On loan') }}</div></div></div>
  <div class="col-6 col-lg-3 mb-3"><div class="lib-stat warn"><div class="n">{{ $overdue }}</div><div class="l">{{ get_phrase('Overdue') }}</div></div></div>
  <div class="col-6 col-lg-3 mb-3"><div class="lib-stat"><div class="n">{{ $titles }}</div><div class="l">{{ get_phrase('Titles') }}</div></div></div>
  <div class="col-6 col-lg-3 mb-3"><div class="lib-stat"><div class="n">{{ $copies }}</div><div class="l">{{ get_phrase('Copies') }}</div></div></div>
</div>
<div class="row mb-3">
  <div class="col-12"><div class="lib-stat warn" style="text-align:left;display:flex;justify-content:space-between;align-items:center;">
    <div class="l" style="margin:0;">{{ get_phrase('Outstanding library fines') }}</div>
    <div class="n">{{ $cur }} {{ number_format($finesOut, 2) }}</div>
  </div></div>
</div>

<div class="eSection-wrap">
  <h5 class="mb-3"><i class="bi bi-clock-history me-2" style="color:#00955f;"></i>{{ get_phrase('Recent Koha checkouts') }}</h5>
  @if(count($recent))
  <div class="table-responsive">
    <table class="table eTable eTable-2 mb-0" style="font-size:13.5px;">
      <thead><tr><th>{{ get_phrase('Book') }}</th><th>{{ get_phrase('Borrower') }}</th><th>{{ get_phrase('Issued') }}</th><th>{{ get_phrase('Due') }}</th><th>{{ get_phrase('Status') }}</th></tr></thead>
      <tbody>
        @foreach($recent as $r)
          <tr>
            <td style="font-weight:600;">{{ $r->book }}</td>
            <td>{{ $r->student }}</td>
            <td>{{ $r->issue_date ? date('d M Y', (int)$r->issue_date) : '—' }}</td>
            <td>{{ $r->due_date ? date('d M Y', (int)$r->due_date) : '—' }}</td>
            <td>@if($r->status==1)<span class="eBadge ebg-soft-success">{{ get_phrase('Returned') }}</span>@else<span class="eBadge ebg-soft-warning">{{ get_phrase('On loan') }}</span>@endif</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @else
    <p class="text-muted mb-0">{{ get_phrase('No Koha checkouts mirrored yet.') }}</p>
  @endif
</div>
@endsection
