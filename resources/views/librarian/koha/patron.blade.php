@extends('librarian.navigation')
@section('content')
@php $cur = get_settings('system_currency') ?: 'KES'; @endphp
<style>
  .lib-hero{background:linear-gradient(120deg,#00955f,#007a4d);color:#fff;border-radius:14px;padding:20px 24px;margin-bottom:16px;}
  .lib-hero h4{color:#fff;font-weight:800;margin:0;}
  .pcard{background:#f7fbf9;border:1px solid #d9efe6;border-radius:12px;padding:16px 18px;}
  .pcard .nm{font-weight:800;font-size:17px;color:#14202b;}
  .pkv{font-size:12.5px;color:#5a6270;}
  .pill{display:inline-block;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;background:#e5f7ef;color:#00794c;}
  .pill.red{background:#fdECEC;color:#c0392b;}
</style>

<div class="lib-hero"><h4><i class="bi bi-person-badge me-2"></i>{{ get_phrase('Patron Lookup & Issue') }}</h4></div>

<div class="eSection-wrap mb-3">
  <form method="GET" class="d-flex flex-wrap" style="gap:10px;">
    <input type="text" name="q" value="{{ $q }}" class="form-control eForm-control" style="flex:1;min-width:240px;" placeholder="{{ get_phrase('Card number or name…') }}" autofocus>
    <button class="eBtn btn-primary" type="submit"><i class="bi bi-search"></i> {{ get_phrase('Find borrower') }}</button>
  </form>
  @if(!$configured)<p class="text-muted mt-2 mb-0">{{ get_phrase('Koha is not connected.') }}</p>@endif
  @if($notFound)<p class="text-danger mt-2 mb-0">{{ get_phrase('No borrower found for') }} “{{ $q }}”.</p>@endif
</div>

@if($patron)
  @php $balance = (float)($patron['account_balance'] ?? 0); @endphp
  <div class="row">
    <div class="col-lg-4 mb-3">
      <div class="pcard">
        <div class="nm">{{ trim(($patron['firstname'] ?? '').' '.($patron['surname'] ?? '')) }}</div>
        <div class="pkv mt-1">{{ get_phrase('Card') }}: <b>{{ $patron['cardnumber'] ?? '—' }}</b></div>
        <div class="pkv">{{ get_phrase('Category') }}: {{ $patron['category_id'] ?? '—' }} · {{ get_phrase('Library') }}: {{ $patron['library_id'] ?? '—' }}</div>
        <div class="pkv">{{ get_phrase('Email') }}: {{ $patron['email'] ?? '—' }}</div>
        <div class="mt-2">
          @if(count($fines))<span class="pill red">{{ count($fines) }} {{ get_phrase('fine(s)') }}</span>@else<span class="pill">{{ get_phrase('No fines') }}</span>@endif
          <span class="pill">{{ count($checkouts) }} {{ get_phrase('on loan') }}</span>
        </div>
      </div>

      <div class="eSection-wrap mt-3">
        <h6 class="mb-2"><i class="bi bi-box-arrow-right me-2" style="color:#00955f;"></i>{{ get_phrase('Issue a book') }}</h6>
        <form method="POST" action="{{ route('librarian.koha.issue') }}">
          @csrf
          <input type="hidden" name="cardnumber" value="{{ $patron['cardnumber'] }}">
          <div class="mb-2">
            <label class="eForm-label">{{ get_phrase('Item barcode') }}</label>
            <input type="text" name="barcode" class="form-control eForm-control" placeholder="{{ get_phrase('Scan or type barcode') }}" required autocomplete="off">
          </div>
          <button class="eBtn btn-primary w-100" type="submit"><i class="bi bi-check2-circle"></i> {{ get_phrase('Check out') }}</button>
        </form>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="eSection-wrap mb-3">
        <h6 class="mb-2"><i class="bi bi-journal-bookmark me-2" style="color:#00955f;"></i>{{ get_phrase('Current loans') }} ({{ count($checkouts) }})</h6>
        @if(count($checkouts))
        <div class="table-responsive"><table class="table eTable eTable-2 mb-0" style="font-size:13px;">
          <thead><tr><th>{{ get_phrase('Title') }}</th><th>{{ get_phrase('Barcode') }}</th><th>{{ get_phrase('Due') }}</th><th class="text-end">{{ get_phrase('Return') }}</th></tr></thead>
          <tbody>
            @foreach($checkouts as $c)
              <tr>
                <td style="font-weight:600;">{{ $c['title'] ?? ('biblio '.($c['item_id'] ?? '')) }}</td>
                <td>{{ $c['barcode'] ?? '—' }}</td>
                <td>{{ isset($c['due_date']) ? date('d M Y', strtotime($c['due_date'])) : '—' }}</td>
                <td class="text-end"><a class="eBtn btn-secondary" target="_blank" href="{{ $checkinBase }}?barcode={{ urlencode($c['barcode'] ?? '') }}"><i class="bi bi-box-arrow-in-left"></i> {{ get_phrase('Return in Koha') }}</a></td>
              </tr>
            @endforeach
          </tbody>
        </table></div>
        <small class="text-muted d-block mt-2">{{ get_phrase('Returns are processed in Koha (REST has no check-in); the app updates on the next circulation sync.') }}</small>
        @else<p class="text-muted mb-0">{{ get_phrase('No items on loan.') }}</p>@endif
      </div>

      <div class="eSection-wrap">
        <h6 class="mb-2"><i class="bi bi-cash-coin me-2" style="color:#c0392b;"></i>{{ get_phrase('Outstanding fines') }}</h6>
        @if(count($fines))
        <div class="table-responsive"><table class="table eTable eTable-2 mb-0" style="font-size:13px;">
          <thead><tr><th>{{ get_phrase('Description') }}</th><th>{{ get_phrase('Type') }}</th><th class="text-end">{{ get_phrase('Outstanding') }}</th></tr></thead>
          <tbody>
            @foreach($fines as $f)
              <tr>
                <td>{{ $f['description'] ?: '—' }}</td>
                <td>{{ $f['debit_type'] ?? '—' }}</td>
                <td class="text-end" style="font-weight:700;color:#c0392b;">{{ $cur }} {{ number_format($f['amount_outstanding'] ?? 0, 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table></div>
        <small class="text-muted d-block mt-2">{{ get_phrase('Fines appear as payable invoices on the student’s fee page; paying there settles them in Koha.') }}</small>
        @else<p class="text-muted mb-0">{{ get_phrase('No outstanding fines.') }}</p>@endif
      </div>
    </div>
  </div>
@endif
@endsection
