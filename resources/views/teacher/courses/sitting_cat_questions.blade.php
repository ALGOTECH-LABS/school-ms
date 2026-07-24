@extends('teacher.navigation')

@section('content')
@php $typeLabels = ['mcq'=>'MCQ','truefalse'=>'True/False','short'=>'Short','essay'=>'Essay']; @endphp
<style>
  .qq-hero{ background:linear-gradient(120deg,#a37b1d,#7a5c12); color:#fff; border-radius:14px;
    padding:20px 24px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
  .qq-hero h4{ color:#fff; font-weight:800; margin:0 0 4px; }
  .qq-pill{ display:inline-block; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; background:rgba(255,255,255,.2); margin-right:6px; }
  .qq-hero .eBtn{ display:inline-flex; align-items:center; gap:6px; width:auto; min-width:0; }
  .qq-card{ background:#fff; border:1px solid #eef1f0; border-radius:14px; padding:18px 20px; margin-bottom:16px; }
  .qq-bank-item{ display:flex; align-items:center; gap:12px; padding:11px 4px; border-bottom:1px solid #f0f2f4; }
  .qq-bank-item:last-child{ border-bottom:0; }
  .qq-bank-q{ flex:1; font-size:13px; color:#232a33; line-height:1.4; }
  .qq-bank-meta{ margin-top:4px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .qq-add-btn{ width:34px; height:34px; border-radius:9px; border:0; background:#a37b1d; color:#fff;
    display:flex; align-items:center; justify-content:center; font-size:16px; cursor:pointer; }
  .qq-add-btn:hover{ background:#7a5c12; }
</style>

<div class="qq-hero">
  <div>
    <h4><i class="bi bi-building me-2"></i>{{ $exam->name }} <small style="opacity:.85;font-size:13px;">({{ get_phrase('sitting exam') }})</small></h4>
    <div>
      <span class="qq-pill">{{ $className ?? '—' }}</span>
      <span class="qq-pill">{{ $subjectName ?? '—' }}</span>
      <span class="qq-pill">{{ $categoryName ?? '—' }}</span>
      <span class="qq-pill">{{ $attached->count() }} {{ get_phrase('questions') }}</span>
      <span class="qq-pill">{{ (int) $exam->total_marks }} {{ get_phrase('marks') }}</span>
    </div>
  </div>
  <div class="d-flex flex-wrap" style="gap:8px;">
    <a class="eBtn" style="background:rgba(255,255,255,.28);color:#fff;border:1px solid rgba(255,255,255,.5);font-weight:700;"
       href="{{ $course ? route('teacher.addons.course.manage', $course->id).'#tab-cats' : route('teacher.addons.courses') }}"><i class="bi bi-arrow-left"></i> {{ get_phrase('Back to course') }}</a>
    <a class="eBtn" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.4);"
       href="{{ route('teacher.addons.course.sitting_cat.paper', $exam->id) }}" target="_blank"><i class="bi bi-printer"></i> {{ get_phrase('Preview / Print paper') }}</a>
    <a class="eBtn" style="background:#fff;color:#7a5c12;"
       href="{{ route('teacher.addons.course.sitting_cat.marks', $exam->id) }}"><i class="bi bi-pencil-square"></i> {{ get_phrase('Enter marks') }}</a>
  </div>
</div>

<div class="row">
  <div class="col-lg-7">
    <div class="qq-card">
      <h5 class="mb-3"><i class="bi bi-journal-text me-2" style="color:#a37b1d;"></i>{{ get_phrase('Questions on this paper') }}</h5>
      @if($attached->count())
        <div class="table-responsive">
          <table class="table eTable eTable-2 mb-0" style="font-size:13.5px;">
            <thead><tr>
              <th style="width:36px;">#</th><th>{{ get_phrase('Question') }}</th>
              <th>{{ get_phrase('Type') }}</th><th class="text-center">{{ get_phrase('Marks') }}</th>
              <th class="text-end">{{ get_phrase('Remove') }}</th>
            </tr></thead>
            <tbody>
              @foreach($attached as $i => $eq)
                <tr>
                  <td>{{ $i + 1 }}</td>
                  <td style="max-width:380px;">{{ \Illuminate\Support\Str::limit($eq->q->question, 110) }}</td>
                  <td><span class="badge bg-primary">{{ $typeLabels[$eq->q->type] ?? $eq->q->type }}</span></td>
                  <td class="text-center">{{ $eq->marks }}</td>
                  <td class="text-end">
                    <form method="POST" action="{{ route('teacher.addons.course.sitting_cat.question.remove') }}" style="display:inline;"
                          onsubmit="return confirm('{{ get_phrase('Remove this question from the paper?') }}')">
                      @csrf
                      <input type="hidden" name="exam_id" value="{{ $exam->id }}">
                      <input type="hidden" name="question_id" value="{{ $eq->question_id }}">
                      <button class="eBtn btn-secondary" style="padding:4px 10px;" type="submit"><i class="bi bi-x-lg"></i></button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="text-center text-muted py-4">
          <i class="bi bi-journal-x" style="font-size:32px;opacity:.4;"></i>
          <p class="mb-0 mt-2">{{ get_phrase('No questions on this paper yet — add some from the bank on the right.') }}</p>
        </div>
      @endif
    </div>
  </div>

  <div class="col-lg-5">
    <div class="qq-card">
      <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:8px;">
        <h5 class="mb-0"><i class="bi bi-collection me-2" style="color:#a37b1d;"></i>{{ get_phrase('Add from the bank') }} <small class="text-muted">({{ $subjectName }})</small></h5>
        <span class="badge bg-secondary">{{ $bankTotal }} {{ get_phrase('available') }}</span>
      </div>
      <form method="GET" class="d-flex mb-3" style="gap:8px;" action="{{ route('teacher.addons.course.sitting_cat.questions', $exam->id) }}">
        <input type="text" class="form-control eForm-control" name="q" value="{{ $search }}" placeholder="{{ get_phrase('Search this subject’s questions...') }}">
        <button class="eBtn btn-secondary" type="submit"><i class="bi bi-search"></i></button>
        @if($search)<a class="eBtn btn-secondary" href="{{ route('teacher.addons.course.sitting_cat.questions', $exam->id) }}"><i class="bi bi-x-lg"></i></a>@endif
      </form>

      @if($bank->count())
        <div class="qq-bank">
          @foreach($bank as $b)
            <div class="qq-bank-item">
              <div class="qq-bank-q">
                {{ \Illuminate\Support\Str::limit($b->question, 96) }}
                <div class="qq-bank-meta">
                  <span class="badge bg-primary">{{ $typeLabels[$b->type] ?? $b->type }}</span>
                  <small class="text-muted">{{ $b->marks }} {{ get_phrase('marks') }} · {{ ucfirst($b->difficulty) }}{{ $b->topic ? ' · '.$b->topic : '' }}</small>
                </div>
              </div>
              <form method="POST" action="{{ route('teacher.addons.course.sitting_cat.question.add') }}" style="margin:0;">
                @csrf
                <input type="hidden" name="exam_id" value="{{ $exam->id }}">
                <input type="hidden" name="question_id" value="{{ $b->id }}">
                <button class="qq-add-btn" type="submit" title="{{ get_phrase('Add to paper') }}"><i class="bi bi-plus-lg"></i></button>
              </form>
            </div>
          @endforeach
        </div>
        <div class="mt-3 d-flex justify-content-center">{{ $bank->links() }}</div>
      @elseif($bankTotal == 0 && !$search)
        <div class="text-center text-muted py-4" style="font-size:13px;">
          <i class="bi bi-inboxes" style="font-size:30px;opacity:.4;"></i>
          <p class="mb-2 mt-2">{{ get_phrase('No questions in the bank for this subject yet.') }}</p>
          <a class="eBtn btn-primary" href="{{ route('teacher.qbank') }}" target="_blank"><i class="bi bi-plus-lg"></i> {{ get_phrase('Write questions in the Question bank') }}</a>
        </div>
      @else
        <div class="text-center text-muted py-4" style="font-size:13px;">
          <p class="mb-1">{{ get_phrase('No questions match') }} “{{ $search }}”.</p>
          <a href="{{ route('teacher.addons.course.sitting_cat.questions', $exam->id) }}">{{ get_phrase('Clear search') }}</a>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
