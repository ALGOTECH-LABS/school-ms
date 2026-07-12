@extends('admin.navigation')

@section('content')
<style>
  .kh-stat{background:#fff;border:1px solid #eef1f0;border-radius:12px;padding:16px 18px;text-align:center;}
  .kh-stat .n{font-size:22px;font-weight:800;color:#00955f;}
  .kh-stat .l{font-size:12px;color:#69707d;text-transform:uppercase;letter-spacing:.4px;}
  .kh-kv{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px dashed #eef1f0;font-size:13.5px;}
  .kh-kv:last-child{border:0;}
  .kh-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px;}
</style>

<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ get_phrase('Koha Library Integration') }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2">
          <li><a href="#">{{ get_phrase('Home') }}</a></li>
          <li><a href="#">{{ get_phrase('Library') }}</a></li>
          <li><a href="#">{{ get_phrase('Koha') }}</a></li>
        </ul>
      </div>
      <a class="eBtn btn-secondary" href="{{ route('admin.koha.catalog') }}"><i class="bi bi-search"></i> {{ get_phrase('Catalog search') }}</a>
    </div>
  </div></div>
</div>

<div class="row">
  <div class="col-md-5">
    <div class="eSection-wrap mb-3">
      <h5 class="mb-3"><i class="bi bi-hdd-network me-2" style="color:#00955f;"></i>{{ get_phrase('Connection') }}</h5>
      <div class="kh-kv"><span class="text-muted">{{ get_phrase('Status') }}</span>
        <span>
          <span class="kh-dot" style="background:{{ $online ? '#00955f' : '#c0392b' }};"></span>
          {{ $configured ? ($online ? get_phrase('Connected') : get_phrase('Configured — not responding')) : get_phrase('Not configured') }}
        </span></div>
      <div class="kh-kv"><span class="text-muted">{{ get_phrase('Staff API') }}</span><span>{{ $settings['base'] ?: '—' }}</span></div>
      <div class="kh-kv"><span class="text-muted">{{ get_phrase('OPAC') }}</span><span>{{ $settings['opac'] ?: '—' }}</span></div>
      <div class="kh-kv"><span class="text-muted">{{ get_phrase('API user') }}</span><span>{{ $settings['user'] ?: '—' }}</span></div>
      <div class="kh-kv"><span class="text-muted">{{ get_phrase('Branch') }}</span><span>{{ $settings['branch'] ?: '—' }}</span></div>
      <p class="text-muted mt-2" style="font-size:12px;">{{ get_phrase('Edit connection details in Super Admin → System settings → Library (Koha).') }}</p>
    </div>
  </div>

  <div class="col-md-7">
    <div class="row mb-3">
      <div class="col-6 col-lg-3 mb-2"><div class="kh-stat"><div class="n">{{ $stats['mapped'] }}</div><div class="l">{{ get_phrase('Patrons') }}</div></div></div>
      <div class="col-6 col-lg-3 mb-2"><div class="kh-stat"><div class="n">{{ $stats['books_pushed'] }}</div><div class="l">{{ get_phrase('Bib pushed') }}</div></div></div>
      <div class="col-6 col-lg-3 mb-2"><div class="kh-stat"><div class="n">{{ $stats['koha_issues'] }}</div><div class="l">{{ get_phrase('Checkouts') }}</div></div></div>
      <div class="col-6 col-lg-3 mb-2"><div class="kh-stat"><div class="n">{{ $stats['koha_fines'] }}</div><div class="l">{{ get_phrase('Fines') }}</div></div></div>
    </div>

    <div class="eSection-wrap">
      <h5 class="mb-1"><i class="bi bi-arrow-repeat me-2" style="color:#00955f;"></i>{{ get_phrase('Sync') }}</h5>
      <p class="text-muted" style="font-size:12.5px;">{{ get_phrase('Each button calls the Koha REST API. Large syncs may take a while.') }}</p>
      <div class="d-flex flex-wrap" style="gap:10px;">
        @foreach([
          ['patrons','Sync patrons','bi-people'],
          ['catalog','Push catalog','bi-journals'],
          ['circulation','Sync circulation','bi-arrow-left-right'],
          ['fines','Sync fines','bi-cash-coin'],
        ] as $b)
          <form method="POST" action="{{ route('admin.koha.sync') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='<i class=\'bi bi-hourglass-split\'></i> {{ get_phrase('Running') }}';">
            @csrf
            <input type="hidden" name="job" value="{{ $b[0] }}">
            <button class="eBtn btn-primary" type="submit"><i class="bi {{ $b[2] }}"></i> {{ get_phrase($b[1]) }}</button>
          </form>
        @endforeach
      </div>
    </div>
  </div>
</div>
@endsection
