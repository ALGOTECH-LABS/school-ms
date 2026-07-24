@extends('teacher.navigation')

@section('content')
@php
  $present = collect($existing)->filter(fn($v) => $v == 1)->count();
  $absent  = $students->count() - $present;
@endphp
<style>
  .at-hero{ background:linear-gradient(120deg,#00955f,#007a4d); color:#fff; border-radius:14px;
    padding:20px 24px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
  .at-hero h4{ color:#fff; font-weight:800; margin:0 0 4px; }
  .at-pill{ display:inline-block; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; background:rgba(255,255,255,.2); margin-right:6px; }
  .at-hero .eBtn{ display:inline-flex; align-items:center; gap:6px; width:auto; }
  .at-card{ background:#fff; border:1px solid #eef1f0; border-radius:14px; padding:18px 22px; }
  .at-radio{ display:inline-flex; align-items:center; gap:6px; margin-right:16px; font-weight:600; cursor:pointer; }
  .at-radio.p{ color:#00794c; } .at-radio.a{ color:#c0392b; }
</style>

<div class="at-hero">
  <div>
    <h4><i class="bi bi-calendar-check me-2"></i>{{ get_phrase('Daily attendance') }}</h4>
    <div>
      <span class="at-pill">{{ $course->title }}</span>
      <span class="at-pill">{{ $className ?? '—' }}</span>
      <span class="at-pill">{{ $students->count() }} {{ get_phrase('students') }}</span>
    </div>
  </div>
  <a class="eBtn" style="background:rgba(255,255,255,.28);color:#fff;border:1px solid rgba(255,255,255,.5);font-weight:700;"
     href="{{ route('teacher.addons.course.manage', $course->id) }}"><i class="bi bi-arrow-left"></i> {{ get_phrase('Back to course') }}</a>
</div>

<div class="at-card">
  {{-- date picker (reloads roster for that day) --}}
  <form method="GET" action="{{ route('teacher.addons.course.attendance', $course->id) }}" class="d-flex align-items-end flex-wrap mb-3" style="gap:12px;">
    <div>
      <label class="eForm-label">{{ get_phrase('Date') }}</label>
      <input type="date" name="date" value="{{ $date }}" class="form-control eForm-control" onchange="this.form.submit()" style="min-width:180px;">
    </div>
    @if($anyMarked)
      <span class="eBadge ebg-soft-success mb-2">{{ get_phrase('Already marked') }}: {{ $present }} {{ get_phrase('present') }} · {{ $absent }} {{ get_phrase('absent') }}</span>
    @else
      <span class="eBadge ebg-soft-warning mb-2">{{ get_phrase('Not yet marked for this day') }}</span>
    @endif
  </form>

  @if($students->count())
    <form method="POST" action="{{ route('teacher.addons.course.attendance.save') }}">
      @csrf
      <input type="hidden" name="course_id" value="{{ $course->id }}">
      <input type="hidden" name="date" value="{{ $date }}">

      <div class="d-flex mb-3" style="gap:8px;">
        <button type="button" class="eBtn btn-secondary" onclick="setAll(1)"><i class="bi bi-check2-all"></i> {{ get_phrase('Mark all present') }}</button>
        <button type="button" class="eBtn btn-secondary" onclick="setAll(0)"><i class="bi bi-x-octagon"></i> {{ get_phrase('Mark all absent') }}</button>
      </div>

      <div class="table-responsive">
        <table class="table eTable eTable-2 mb-0" style="font-size:13.5px;">
          <thead><tr>
            <th style="width:40px;">#</th><th>{{ get_phrase('Student') }}</th><th>{{ get_phrase('Section') }}</th>
            <th class="text-end" style="width:260px;">{{ get_phrase('Status') }}</th>
          </tr></thead>
          <tbody>
            @foreach($students as $i => $s)
              @php $st = $existing[$s->id] ?? 1; @endphp   {{-- default present --}}
              <tr>
                <td>{{ $i + 1 }}</td>
                <td style="font-weight:600;">{{ $s->name }}@if($s->code)<br><small class="text-muted">{{ $s->code }}</small>@endif</td>
                <td>{{ $s->section_name }}</td>
                <td class="text-end">
                  <input type="hidden" name="student_id[]" value="{{ $s->id }}">
                  <label class="at-radio p"><input type="radio" name="status-{{ $s->id }}" value="1" class="att-1" {{ $st == 1 ? 'checked' : '' }}> {{ get_phrase('Present') }}</label>
                  <label class="at-radio a"><input type="radio" name="status-{{ $s->id }}" value="0" class="att-0" {{ $st == 0 ? 'checked' : '' }}> {{ get_phrase('Absent') }}</label>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="mt-3 d-flex justify-content-end">
        <button class="eBtn btn-primary" type="submit"><i class="bi bi-save"></i> {{ get_phrase('Save attendance') }}</button>
      </div>
    </form>
  @else
    <div class="text-center text-muted py-4">{{ get_phrase('No students enrolled in this class yet.') }}</div>
  @endif
</div>

<script type="text/javascript">
  "use strict";
  function setAll(val){ document.querySelectorAll('.att-' + val).forEach(function(r){ r.checked = true; }); }
</script>
@endsection
