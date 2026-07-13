@extends('admin.navigation')

@section('content')
@php
  // pre-compute school-wide clashes so the banner can sit above the grid
  $clashCount = 0;
  $grouped = $routines->groupBy(function ($r) {
      return $r->day.'|'.(int)$r->starting_hour.':'.(int)$r->starting_minute.'-'.(int)$r->ending_hour.':'.(int)$r->ending_minute;
  });
  foreach ($grouped as $cell) {
      $tClash = $cell->groupBy('teacher_id')->contains(fn($g) => $g->count() > 1);
      $rClash = $cell->filter(fn($r) => $r->room_id)->groupBy('room_id')->contains(fn($g) => $g->count() > 1);
      if ($tClash || $rClash) $clashCount++;
  }
@endphp
<style>
  .tt-hero{ background:linear-gradient(120deg,#00955f,#007a4d); color:#fff; border-radius:14px;
    padding:22px 26px; margin-bottom:14px; display:flex; justify-content:space-between;
    align-items:center; flex-wrap:wrap; gap:12px; }
  .tt-hero h4{ color:#fff; font-weight:800; margin:0 0 4px; }
  .tt-hero .sub{ font-size:13px; opacity:.9; }
  .tt-pill{ display:inline-block; font-size:11px; font-weight:700; padding:3px 10px;
    border-radius:20px; background:rgba(255,255,255,.2); }
  .tt-banner{ border-radius:12px; padding:12px 18px; margin-bottom:16px; font-weight:700;
    font-size:13.5px; display:flex; align-items:center; gap:10px; }
  .tt-banner.ok{ background:#e8f7ef; color:#0a7a4b; border:1px solid #c7ecd7; }
  .tt-banner.bad{ background:#fdecec; color:#c0392b; border:1px solid #f5c9c4; }
  .tt-legend{ display:flex; gap:16px; flex-wrap:wrap; font-size:12px; color:#6c7385; margin:10px 2px 0; }
  .tt-legend .sw{ display:inline-block; width:12px; height:12px; border-radius:3px; margin-right:5px; vertical-align:middle; }
  .tt-filters{ background:#fff; border:1px solid #eef0f4; border-radius:12px; padding:14px 16px; margin-bottom:16px;
    display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap; }
  .tt-filters .fg{ display:flex; flex-direction:column; gap:5px; }
  .tt-filters label{ font-size:11.5px; font-weight:700; color:#6c7385; text-transform:uppercase; letter-spacing:.3px; }
  .tt-filters select{ min-width:190px; border:1px solid #e2e6ee; border-radius:9px; padding:9px 12px;
    font-size:13.5px; color:#181c32; background:#fbfcfe; }
  .tt-filters .clear{ font-size:13px; color:#8a92a5; text-decoration:none; padding:9px 4px; }
  .tt-filters .clear:hover{ color:#f04b24; }
  .tt-filters .fcount{ margin-left:auto; font-size:12.5px; color:#8a92a5; align-self:center; }
</style>

<div class="tt-hero">
  <div>
    <h4><i class="bi bi-calendar3 me-2"></i>{{ get_phrase('Master Timetable') }}</h4>
    <div class="sub">
      <span class="tt-pill">{{ $routines->count() }} {{ get_phrase('sessions') }}</span>
      <span class="tt-pill">{{ $routines->pluck('class_id')->unique()->count() }} {{ get_phrase('classes') }}</span>
      <span class="tt-pill">{{ $routines->pluck('teacher_id')->unique()->count() }} {{ get_phrase('lecturers') }}</span>
    </div>
  </div>
  <a class="eBtn" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.4);" href="{{ route('admin.routine') }}">
    <i class="bi bi-pencil-square"></i> {{ get_phrase('Edit class routines') }}
  </a>
</div>

<form method="GET" action="{{ route('admin.timetable') }}" class="tt-filters" id="ttFilters">
  <div class="fg">
    <label for="f-class">{{ get_phrase('Course') }}</label>
    <select name="class_id" id="f-class" onchange="document.getElementById('ttFilters').submit()">
      <option value="">{{ get_phrase('All courses') }}</option>
      @foreach($classes as $c)
        <option value="{{ $c->id }}" {{ (string)$classId === (string)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
      @endforeach
    </select>
  </div>
  <div class="fg">
    <label for="f-teacher">{{ get_phrase('Lecturer') }}</label>
    <select name="teacher_id" id="f-teacher" onchange="document.getElementById('ttFilters').submit()">
      <option value="">{{ get_phrase('All lecturers') }}</option>
      @foreach($teachers as $t)
        <option value="{{ $t->id }}" {{ (string)$teacherId === (string)$t->id ? 'selected' : '' }}>{{ $t->name }}</option>
      @endforeach
    </select>
  </div>
  @if($classId || $teacherId)
    <a class="clear" href="{{ route('admin.timetable') }}"><i class="bi bi-x-circle"></i> {{ get_phrase('Clear filters') }}</a>
  @endif
  <span class="fcount">{{ $routines->count() }} {{ get_phrase('sessions shown') }}</span>
</form>

@if($clashCount > 0)
  <div class="tt-banner bad">
    <i class="bi bi-exclamation-triangle-fill" style="font-size:18px;"></i>
    {{ $clashCount }} {{ get_phrase('scheduling clash(es) detected — a lecturer or room is double-booked. Flagged cells are outlined in red below.') }}
  </div>
@else
  <div class="tt-banner ok">
    <i class="bi bi-check-circle-fill" style="font-size:18px;"></i>
    {{ get_phrase('No scheduling clashes — every lecturer and room is booked at most once per slot.') }}
  </div>
@endif

@include('partials.timetable_master', ['routines' => $routines])

<div class="tt-legend">
  <span><span class="sw" style="background:#e8f5ef;"></span>{{ get_phrase('Each colour = a class') }}</span>
  <span><span class="sw" style="background:#fff;border:1.5px solid #f04b24;"></span>{{ get_phrase('Red outline = double-booking') }}</span>
</div>
@endsection
