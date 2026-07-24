@extends('teacher.navigation')

@section('content')
<style>
  .mk-hero{ background:linear-gradient(120deg,#a37b1d,#7a5c12); color:#fff; border-radius:14px;
    padding:20px 24px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
  .mk-hero h4{ color:#fff; font-weight:800; margin:0 0 4px; }
  .mk-pill{ display:inline-block; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; background:rgba(255,255,255,.2); margin-right:6px; }
  .mk-hero .eBtn{ display:inline-flex; align-items:center; gap:6px; width:auto; }
  .mk-card{ background:#fff; border:1px solid #eef1f0; border-radius:14px; padding:18px 22px; }
  .mk-score{ width:90px; text-align:center; }
  .mk-total{ color:#8a92a5; font-size:13px; }
</style>

<div class="mk-hero">
  <div>
    <h4><i class="bi bi-pencil-square me-2"></i>{{ get_phrase('Enter marks') }} — {{ $exam->name }}</h4>
    <div>
      <span class="mk-pill">{{ $className ?? '—' }}</span>
      <span class="mk-pill">{{ $subjectName ?? '—' }}</span>
      <span class="mk-pill">{{ $categoryName ?? '—' }}</span>
      <span class="mk-pill">{{ get_phrase('Out of') }} {{ (int) $exam->total_marks }}</span>
    </div>
  </div>
  <div class="d-flex flex-wrap" style="gap:8px;">
    <a class="eBtn" style="background:rgba(255,255,255,.28);color:#fff;border:1px solid rgba(255,255,255,.5);font-weight:700;"
       href="{{ $course ? route('teacher.addons.course.manage', $course->id).'#tab-cats' : route('teacher.addons.courses') }}"><i class="bi bi-arrow-left"></i> {{ get_phrase('Back to course') }}</a>
    <a class="eBtn" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.4);"
       href="{{ route('teacher.addons.course.sitting_cat.paper', $exam->id) }}" target="_blank"><i class="bi bi-printer"></i> {{ get_phrase('Print paper') }}</a>
  </div>
</div>

<div class="mk-card">
  <p class="text-muted mb-3" style="font-size:12.5px;"><i class="bi bi-info-circle"></i>
    {{ get_phrase('Enter each student’s total score for the sat paper. These marks are saved to the gradebook and appear on report cards & transcripts. Leave a box blank to skip that student.') }}</p>

  @if($students->count())
    <form method="POST" action="{{ route('teacher.addons.course.sitting_cat.marks_save') }}">
      @csrf
      <input type="hidden" name="exam_id" value="{{ $exam->id }}">
      <div class="table-responsive">
        <table class="table eTable eTable-2 mb-0" style="font-size:13.5px;">
          <thead><tr>
            <th style="width:40px;">#</th>
            <th>{{ get_phrase('Student') }}</th>
            <th>{{ get_phrase('Section') }}</th>
            <th class="text-center" style="width:180px;">{{ get_phrase('Score') }}</th>
          </tr></thead>
          <tbody>
            @foreach($students as $i => $s)
              <tr>
                <td>{{ $i + 1 }}</td>
                <td style="font-weight:600;">{{ $s->name }}@if($s->code)<br><small class="text-muted">{{ $s->code }}</small>@endif</td>
                <td>{{ $s->section_name }}</td>
                <td class="text-center">
                  <div class="d-inline-flex align-items-center" style="gap:6px;">
                    <input type="number" step="0.01" min="0" max="{{ (int) $exam->total_marks }}"
                           name="score[{{ $s->id }}]" value="{{ $existing[$s->id] ?? '' }}"
                           class="form-control eForm-control mk-score" placeholder="—">
                    <span class="mk-total">/ {{ (int) $exam->total_marks }}</span>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="mt-3 d-flex justify-content-end">
        <button class="eBtn btn-primary" type="submit"><i class="bi bi-save"></i> {{ get_phrase('Save marks') }}</button>
      </div>
    </form>
  @else
    <div class="text-center text-muted py-4">{{ get_phrase('No students enrolled in this class yet.') }}</div>
  @endif
</div>
@endsection
