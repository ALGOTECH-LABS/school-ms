@php
  $rid = auth()->user()->role_id;
  $nav = [7=>'student.navigation', 5=>'librarian.navigation', 3=>'teacher.navigation', 6=>'parent.navigation'][$rid] ?? 'admin.navigation';
@endphp
@extends($nav)

@section('content')
<style>
  .kh-cat-hero{background:linear-gradient(120deg,#00955f,#007a4d);color:#fff;border-radius:14px;padding:22px 26px;margin-bottom:18px;}
  .kh-cat-hero h4{color:#fff;font-weight:800;margin:0 0 4px;}
  .kh-cat-hero p{color:rgba(255,255,255,.9);margin:0;font-size:13.5px;}
  .kh-search{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0 6px;}
  .kh-search input{flex:1;min-width:240px;}
  .kh-badge{display:inline-block;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;background:#e5f7ef;color:#00794c;}
  .kh-off{background:#fdECEC;color:#c0392b;}
</style>

<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex flex-column">
      <h4>{{ get_phrase('Library Catalog') }}</h4>
      <ul class="d-flex align-items-center eBreadcrumb-2">
        <li><a href="#">{{ get_phrase('Home') }}</a></li>
        <li><a href="#">{{ get_phrase('Library') }}</a></li>
        <li><a href="#">{{ get_phrase('Catalog') }}</a></li>
      </ul>
    </div>
  </div></div>
</div>

<div class="kh-cat-hero d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
  <div>
    <h4><i class="bi bi-search me-2"></i>{{ get_phrase('Search the library catalog') }}</h4>
    <p>{{ get_phrase('Powered by Koha') }}
      @if($configured)<span class="kh-badge">{{ get_phrase('Connected') }}</span>@else<span class="kh-badge kh-off">{{ get_phrase('Not configured') }}</span>@endif
    </p>
  </div>
  @if($opac)<a class="eBtn" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.4);" target="_blank" href="{{ $opac }}"><i class="bi bi-box-arrow-up-right"></i> {{ get_phrase('Open OPAC') }}</a>@endif
</div>

<div class="eSection-wrap">
  <form method="GET" class="kh-search">
    <input type="text" name="q" value="{{ $q }}" class="form-control eForm-control" placeholder="{{ get_phrase('Title, author, keyword…') }}" autofocus>
    <button class="eBtn btn-primary" type="submit"><i class="bi bi-search"></i> {{ get_phrase('Search') }}</button>
    @if(!$browse)<a class="eBtn btn-secondary" href="{{ url()->current() }}"><i class="bi bi-list-ul"></i> {{ get_phrase('All titles') }}</a>@endif
  </form>

  @if(!$configured)
    <p class="text-muted mt-3">{{ get_phrase('The library catalog is not connected yet.') }}</p>
  @elseif(count($results) === 0)
    <div class="text-center text-muted py-4">
      <i class="bi bi-journal-x" style="font-size:34px;opacity:.4;"></i>
      <p class="mb-0 mt-2">{{ $browse ? get_phrase('The catalog is empty.') : get_phrase('No records found for').' “'.$q.'”.' }}</p>
    </div>
  @else
    <p class="text-muted mt-2 mb-2">
      @if($browse){{ get_phrase('All titles in the catalog') }} ({{ count($results) }})@else{{ count($results) }} {{ get_phrase('result(s) for') }} “{{ $q }}”@endif
    </p>
    <div class="table-responsive">
      <table class="table eTable eTable-2 mb-0" style="font-size:13.5px;">
        <thead><tr>
          <th>#</th><th>{{ get_phrase('Title') }}</th><th>{{ get_phrase('Author') }}</th>
          <th>{{ get_phrase('ISBN') }}</th><th class="text-end">{{ get_phrase('Action') }}</th>
        </tr></thead>
        <tbody>
          @foreach($results as $i => $r)
            <tr>
              <td>{{ $i+1 }}</td>
              <td style="font-weight:600;">{{ $r['title'] }}</td>
              <td>{{ $r['author'] }}</td>
              <td class="text-muted">{{ $r['isbn'] }}</td>
              <td class="text-end">
                @if($r['opac_url'])
                  <a class="eBtn btn-secondary" target="_blank" href="{{ $r['opac_url'] }}"><i class="bi bi-eye"></i> {{ get_phrase('View in catalog') }}</a>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
