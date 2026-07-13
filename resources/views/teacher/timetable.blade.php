@extends('teacher.navigation')

@section('content')
<style>
  .tt-hero{ background:linear-gradient(120deg,#00955f,#007a4d); color:#fff; border-radius:14px;
    padding:22px 26px; margin-bottom:18px; display:flex; justify-content:space-between;
    align-items:center; flex-wrap:wrap; gap:12px; }
  .tt-hero h4{ color:#fff; font-weight:800; margin:0 0 4px; }
  .tt-hero .sub{ font-size:13px; opacity:.9; }
  .tt-pill{ display:inline-block; font-size:11px; font-weight:700; padding:3px 10px;
    border-radius:20px; background:rgba(255,255,255,.2); }
</style>

<div class="tt-hero">
  <div>
    <h4><i class="bi bi-calendar-week me-2"></i>{{ get_phrase('My Timetable') }}</h4>
    <div class="sub">
      <span class="tt-pill">{{ $routines->count() }} {{ get_phrase('sessions this week') }}</span>
      <span class="tt-pill">{{ $routines->pluck('class_id')->unique()->count() }} {{ get_phrase('classes') }}</span>
    </div>
  </div>
  <a class="eBtn" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.4);" href="{{ route('teacher.routine') }}">
    <i class="bi bi-grid-3x3-gap"></i> {{ get_phrase('Class Routine') }}
  </a>
</div>

@include('partials.timetable', ['routines' => $routines, 'cellShow' => 'class', 'admin' => false])
@endsection
