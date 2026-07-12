@php
  $rid = auth()->user()->role_id;
  $nav = $rid == 5 ? 'librarian.navigation' : 'admin.navigation';
  $backList = $rid == 5 ? route('librarian.book.book_list') : route('admin.book.book_list');
  $onLoanNow = collect($history)->where('status', 0)->count();
@endphp
@extends($nav)

@section('content')
<style>
  .bh-hero{background:linear-gradient(120deg,#00955f,#007a4d);color:#fff;border-radius:14px;padding:22px 26px;margin-bottom:18px;}
  .bh-hero h4{color:#fff;font-weight:800;margin:0 0 4px;}
  .bh-meta{font-size:13px;opacity:.92;}
  .bh-stat{background:#fff;border:1px solid #eef1f0;border-radius:12px;padding:14px 16px;text-align:center;}
  .bh-stat .n{font-size:20px;font-weight:800;color:#00955f;}
  .bh-stat .l{font-size:11.5px;color:#69707d;text-transform:uppercase;letter-spacing:.4px;}
</style>

<div class="mainSection-title"><div class="row"><div class="col-12">
  <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
    <div class="d-flex flex-column">
      <h4>{{ get_phrase('Book') }}</h4>
      <ul class="d-flex align-items-center eBreadcrumb-2">
        <li><a href="#">{{ get_phrase('Library') }}</a></li>
        <li><a href="{{ $backList }}">{{ get_phrase('Books') }}</a></li>
        <li><a href="#">{{ get_phrase('History') }}</a></li>
      </ul>
    </div>
    <a class="eBtn btn-secondary" href="{{ $backList }}"><i class="bi bi-arrow-left"></i> {{ get_phrase('Back to books') }}</a>
  </div>
</div></div></div>

<div class="bh-hero d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
  <div>
    <h4><i class="bi bi-book me-2"></i>{{ $book->name }}</h4>
    <div class="bh-meta">{{ $book->author ?: get_phrase('Unknown author') }}
      @if($book->isbn) · ISBN {{ $book->isbn }}@endif
      @if($book->source === 'koha') · <span style="background:rgba(255,255,255,.2);padding:2px 8px;border-radius:10px;">Koha</span>@endif
    </div>
  </div>
  @if($opac)<a class="eBtn" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.4);" target="_blank" href="{{ $opac }}"><i class="bi bi-box-arrow-up-right"></i> {{ get_phrase('View in OPAC') }}</a>@endif
</div>

<div class="row mb-2">
  <div class="col-6 col-lg-3 mb-3"><div class="bh-stat"><div class="n">{{ $total ?? $book->copies }}</div><div class="l">{{ get_phrase('Copies') }}</div></div></div>
  <div class="col-6 col-lg-3 mb-3"><div class="bh-stat"><div class="n">{{ $available ?? ($book->copies - $onLoanNow) }}</div><div class="l">{{ get_phrase('Available') }}</div></div></div>
  <div class="col-6 col-lg-3 mb-3"><div class="bh-stat"><div class="n">{{ $onLoanNow }}</div><div class="l">{{ get_phrase('On loan now') }}</div></div></div>
  <div class="col-6 col-lg-3 mb-3"><div class="bh-stat"><div class="n">{{ count($history) }}</div><div class="l">{{ get_phrase('Total issues') }}</div></div></div>
</div>

<div class="eSection-wrap">
  <h5 class="mb-3"><i class="bi bi-clock-history me-2" style="color:#00955f;"></i>{{ get_phrase('Borrowing history') }}</h5>
  @if(count($history))
    <div class="table-responsive">
      <table class="table eTable eTable-2 mb-0" style="font-size:13.5px;">
        <thead><tr>
          <th>#</th><th>{{ get_phrase('Borrower') }}</th><th>{{ get_phrase('Class') }}</th>
          <th>{{ get_phrase('Issued') }}</th><th>{{ get_phrase('Due') }}</th><th>{{ get_phrase('Status') }}</th>
        </tr></thead>
        <tbody>
          @foreach($history as $i => $h)
            <tr>
              <td>{{ $i+1 }}</td>
              <td style="font-weight:600;">{{ $h->borrower ?: '—' }}
                @if($h->borrower_code)<br><small class="text-muted" style="font-weight:400;">{{ $h->borrower_code }}</small>@endif
              </td>
              <td>{{ $h->class_name ?: '—' }}</td>
              <td>{{ $h->issue_date ? date('d M Y', (int)$h->issue_date) : '—' }}</td>
              <td>{{ $h->due_date ? date('d M Y', (int)$h->due_date) : '—' }}</td>
              <td>
                @if($h->status == 1)
                  <span class="eBadge ebg-soft-success">{{ get_phrase('Returned') }}</span>
                @elseif($h->due_date && (int)$h->due_date < time())
                  <span class="eBadge ebg-soft-danger">{{ get_phrase('Overdue') }}</span>
                @else
                  <span class="eBadge ebg-soft-warning">{{ get_phrase('On loan') }}</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="text-center text-muted py-4">
      <i class="bi bi-journal" style="font-size:34px;opacity:.4;"></i>
      <p class="mb-0 mt-2">{{ get_phrase('This book has never been issued.') }}</p>
    </div>
  @endif
</div>
@endsection
