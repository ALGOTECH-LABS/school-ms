@extends('student.navigation')
@section('content')

@php
  function kh_grade($avg, $grades) {
    if ($avg === null) return null;
    foreach ($grades as $g) { if ($avg >= $g->mark_from && $avg <= $g->mark_upto) return $g; }
    return null;
  }
@endphp

<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex flex-column">
      <h4>{{ get_phrase('Academic History') }}</h4>
      <ul class="d-flex align-items-center eBreadcrumb-2">
        <li><a href="{{ route('student.dashboard') }}">{{ get_phrase('Home') }}</a></li>
        <li><a href="#">{{ get_phrase('Results by year') }}</a></li>
      </ul>
    </div>
  </div></div>
</div>

@forelse($history as $yr)
  @php
    // year overall average = mean of all subject averages
    $subjAvgs = [];
    foreach ($yr['subjects'] as $sub) {
      $vals = [];
      foreach ($yr['cats'] as $c) { if (isset($yr['map'][$sub->id][$c->id])) $vals[] = (float) $yr['map'][$sub->id][$c->id]; }
      $subjAvgs[$sub->id] = count($vals) ? round(array_sum($vals)/count($vals), 1) : null;
    }
    $present = array_filter($subjAvgs, fn($v)=>$v!==null);
    $yearAvg = count($present) ? round(array_sum($present)/count($present), 1) : null;
    $yearGrade = kh_grade($yearAvg, $grades);
  @endphp

  <div class="eSection-wrap mb-4" style="background:#fff;border:1px solid #eef1f0;border-radius:14px;padding:20px 22px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
      <h5 class="mb-0" style="font-weight:700;color:#2b2f3a;">
        <i class="bi bi-calendar3 me-2" style="color:#00955f;"></i>{{ get_phrase('Academic Year') }} {{ $yr['session']->session_title }}
      </h5>
      @if($yearAvg !== null)
        <span style="display:inline-flex;align-items:center;gap:10px;">
          <span class="text-muted" style="font-size:13px;">{{ get_phrase('Year average') }}</span>
          <span style="font-size:18px;font-weight:800;color:#00955f;">{{ $yearAvg }}%</span>
          @if($yearGrade)<span class="badge" style="background:#00955f;font-size:13px;">{{ $yearGrade->name }}</span>@endif
        </span>
      @endif
    </div>

    <div class="table-responsive">
      <table class="table eTable eTable-2 mb-0 text-nowrap" style="font-size:13.5px;">
        <thead>
          <tr>
            <th>{{ get_phrase('Subject') }}</th>
            @foreach($yr['cats'] as $c)<th class="text-center">{{ $c->name }}</th>@endforeach
            <th class="text-center">{{ get_phrase('Average') }}</th>
            <th class="text-center">{{ get_phrase('Grade') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($yr['subjects'] as $sub)
            @php $avg = $subjAvgs[$sub->id]; $g = kh_grade($avg, $grades); @endphp
            <tr>
              <td style="font-weight:600;">{{ $sub->name }}</td>
              @foreach($yr['cats'] as $c)
                <td class="text-center">{{ $yr['map'][$sub->id][$c->id] ?? '—' }}</td>
              @endforeach
              <td class="text-center" style="font-weight:700;">{{ $avg !== null ? $avg : '—' }}</td>
              <td class="text-center">
                @if($g)<span class="badge" style="background:{{ $g->grade_point >= 2 ? '#00955f' : '#c0392b' }};">{{ $g->name }}</span>@else — @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@empty
  <div class="eSection-wrap text-center" style="padding:40px;">
    <p class="text-muted mb-0">{{ get_phrase('No results have been recorded yet.') }}</p>
  </div>
@endforelse

@endsection
