@extends('teacher.navigation')

@section('content')
@php $typeLabels = ['mcq'=>'MCQ','truefalse'=>'True/False','short'=>'Short','essay'=>'Essay']; @endphp
<style>
  .qq-hero{ background:linear-gradient(120deg,#00955f,#007a4d); color:#fff; border-radius:14px;
    padding:20px 24px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
  .qq-hero h4{ color:#fff; font-weight:800; margin:0 0 4px; }
  .qq-pill{ display:inline-block; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; background:rgba(255,255,255,.2); margin-right:6px; }
  .qq-lock{ background:#fdecec; border:1px solid #f5c9c4; color:#c0392b; border-radius:12px; padding:12px 16px; font-weight:600; font-size:13.5px; margin-bottom:16px; }
  .qq-card{ background:#fff; border:1px solid #eef1f0; border-radius:14px; padding:18px 20px; margin-bottom:16px; }
  .qq-bank-item{ display:flex; align-items:center; gap:12px; padding:11px 4px; border-bottom:1px solid #f0f2f4; }
  .qq-bank-item:last-child{ border-bottom:0; }
  .qq-bank-q{ flex:1; font-size:13px; color:#232a33; line-height:1.4; }
  .qq-bank-meta{ margin-top:4px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .qq-bank-add{ flex:0 0 auto; margin:0; }
  .qq-add-btn{ width:34px; height:34px; border-radius:9px; border:0; background:#00955f; color:#fff;
    display:flex; align-items:center; justify-content:center; font-size:16px; cursor:pointer; transition:.15s; }
  .qq-add-btn:hover{ background:#007a4d; transform:translateY(-1px); }
  .qq-hero .eBtn{ display:inline-flex; align-items:center; gap:6px; width:auto; min-width:0; }
</style>

<div class="qq-hero">
  <div>
    <h4><i class="bi bi-list-check me-2"></i>{{ $quiz->title }}</h4>
    <div>
      <span class="qq-pill">{{ $className ?? '—' }} · {{ $sectionName ?? '—' }}</span>
      <span class="qq-pill">{{ $subjectName ?? '—' }}</span>
      <span class="qq-pill">{{ $attached->count() }} {{ get_phrase('questions') }}</span>
      <span class="qq-pill">{{ $quiz->total_marks }} {{ get_phrase('marks') }}</span>
      <span class="qq-pill">{{ $submissionCount }} {{ get_phrase('submissions') }}</span>
    </div>
  </div>
  <div class="d-flex flex-wrap" style="gap:8px;">
    <a class="eBtn" style="background:rgba(255,255,255,.28);color:#fff;border:1px solid rgba(255,255,255,.5);font-weight:700;"
       href="{{ $course ? route('teacher.addons.course.manage', $course->id).'#tab-cats' : route('teacher.qbank.quizzes') }}">
       <i class="bi bi-arrow-left"></i> {{ $course ? get_phrase('Back to course') : get_phrase('Back to CATs') }}</a>
    <a class="eBtn" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.4);"
       href="{{ route('teacher.quiz.paper', $quiz->id) }}" target="_blank"><i class="bi bi-printer"></i> {{ get_phrase('Preview / Print paper') }}</a>
    <a class="eBtn" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.4);"
       href="{{ route('teacher.quiz.review', $quiz->id) }}"><i class="bi bi-clipboard-check"></i> {{ get_phrase('Mark / Review') }}</a>
    @if(!$locked)
    <a class="eBtn" style="background:#fff;color:#00794c;"
       href="javascript:;" onclick="rightModal('{{ route('teacher.qbank.create_modal') }}?attach_quiz_id={{ $quiz->id }}', '{{ get_phrase('New question for this CAT') }}')">
       <i class="bi bi-plus-lg"></i> {{ get_phrase('New question') }}</a>
    @endif
  </div>
</div>

@if($locked)
  <div class="qq-lock"><i class="bi bi-lock-fill"></i>
    {{ get_phrase('Students have already submitted this CAT — the paper is locked and questions can no longer be changed.') }}
  </div>
@endif

<div class="row">
  <div class="col-lg-7">
    <div class="qq-card">
      <h5 class="mb-3"><i class="bi bi-journal-text me-2" style="color:#00955f;"></i>{{ get_phrase('Questions in this CAT') }}</h5>
      @if($attached->count())
        <div class="table-responsive">
          <table class="table eTable eTable-2 mb-0" style="font-size:13.5px;">
            <thead><tr>
              <th style="width:36px;">#</th><th>{{ get_phrase('Question') }}</th>
              <th>{{ get_phrase('Type') }}</th><th class="text-center">{{ get_phrase('Marks') }}</th>
              @if(!$locked)<th class="text-end">{{ get_phrase('Remove') }}</th>@endif
            </tr></thead>
            <tbody>
              @foreach($attached as $i => $aq)
                <tr>
                  <td>{{ $i + 1 }}</td>
                  <td style="max-width:380px;">{{ \Illuminate\Support\Str::limit($aq->q->question, 110) }}</td>
                  <td><span class="badge bg-primary">{{ $typeLabels[$aq->q->type] ?? $aq->q->type }}</span></td>
                  <td class="text-center">{{ $aq->marks }}</td>
                  @if(!$locked)
                  <td class="text-end">
                    <form method="POST" action="{{ route('teacher.quiz.question.remove') }}" style="display:inline;"
                          onsubmit="return confirm('{{ get_phrase('Remove this question from the CAT?') }}')">
                      @csrf
                      <input type="hidden" name="assignment_id" value="{{ $quiz->id }}">
                      <input type="hidden" name="question_id" value="{{ $aq->question_id }}">
                      <button class="eBtn btn-secondary" style="padding:4px 10px;" type="submit"><i class="bi bi-x-lg"></i></button>
                    </form>
                  </td>
                  @endif
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="text-center text-muted py-4">
          <i class="bi bi-journal-x" style="font-size:32px;opacity:.4;"></i>
          <p class="mb-0 mt-2">{{ get_phrase('This CAT has no questions yet — add some from the bank on the right, or create new ones.') }}</p>
        </div>
      @endif
    </div>
  </div>

  <div class="col-lg-5">
    <div class="qq-card">
      <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:8px;">
        <h5 class="mb-0"><i class="bi bi-collection me-2" style="color:#00955f;"></i>{{ get_phrase('Add from the bank') }} <small class="text-muted">({{ $subjectName }})</small></h5>
        <span class="badge bg-secondary">{{ $bankTotal }} {{ get_phrase('available') }}</span>
      </div>
      @if(!$locked)
        <form method="GET" class="d-flex mb-3" style="gap:8px;" action="{{ route('teacher.quiz.questions', $quiz->id) }}">
          <input type="text" class="form-control eForm-control" name="q" value="{{ $search }}" placeholder="{{ get_phrase('Search this subject’s questions...') }}">
          <button class="eBtn btn-secondary" type="submit"><i class="bi bi-search"></i></button>
          @if($search)<a class="eBtn btn-secondary" href="{{ route('teacher.quiz.questions', $quiz->id) }}" title="{{ get_phrase('Clear') }}"><i class="bi bi-x-lg"></i></a>@endif
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
                <form method="POST" action="{{ route('teacher.quiz.question.add') }}" class="qq-bank-add">
                  @csrf
                  <input type="hidden" name="assignment_id" value="{{ $quiz->id }}">
                  <input type="hidden" name="question_id" value="{{ $b->id }}">
                  <button class="qq-add-btn" type="submit" title="{{ get_phrase('Add to CAT') }}"><i class="bi bi-plus-lg"></i></button>
                </form>
              </div>
            @endforeach
          </div>
          <div class="mt-3 d-flex justify-content-center">{{ $bank->links() }}</div>
        @elseif($bankTotal == 0 && !$search)
          <div class="text-center text-muted py-4" style="font-size:13px;">
            <i class="bi bi-inboxes" style="font-size:30px;opacity:.4;"></i>
            <p class="mb-2 mt-2">{{ get_phrase('No questions in the bank for this subject yet.') }}</p>
            <a class="eBtn btn-primary" href="javascript:;"
               onclick="rightModal('{{ route('teacher.qbank.create_modal') }}?attach_quiz_id={{ $quiz->id }}', '{{ get_phrase('New question for this CAT') }}')">
               <i class="bi bi-plus-lg"></i> {{ get_phrase('Write a question') }}</a>
          </div>
        @else
          <div class="text-center text-muted py-4" style="font-size:13px;">
            <i class="bi bi-search" style="font-size:26px;opacity:.4;"></i>
            <p class="mb-1 mt-2">{{ get_phrase('No questions match') }} “{{ $search }}”.</p>
            <a href="{{ route('teacher.quiz.questions', $quiz->id) }}">{{ get_phrase('Clear search') }}</a>
          </div>
        @endif
      @else
        <p class="text-muted mb-0" style="font-size:13px;">{{ get_phrase('The paper is locked — no questions can be added.') }}</p>
      @endif
    </div>
  </div>
</div>
@endsection
