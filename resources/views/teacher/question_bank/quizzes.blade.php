@extends('teacher.navigation')

@section('content')
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ get_phrase('Online CATs') }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2">
          <li><a href="#">{{ get_phrase('Home') }}</a></li>
          <li><a href="#">{{ get_phrase('Online CATs') }}</a></li>
        </ul>
      </div>
      <div class="export-btn-area d-flex" style="gap:8px;">
        <a href="{{ route('teacher.qbank') }}" class="eBtn btn-secondary"><i class="bi bi-collection"></i> {{ get_phrase('Question bank') }}</a>
        <a href="javascript:;" class="export_btn"
           onclick="rightModal('{{ route('teacher.qbank.generate_modal') }}', '{{ get_phrase('Create Online CAT') }}')"><i class="bi bi-plus"></i>{{ get_phrase('Create Online CAT') }}</a>
      </div>
    </div>
  </div></div>
</div>

<div class="row"><div class="col-12"><div class="eSection-wrap">

  @if($quizzes->count() === 0)
    <div style="text-align:center; padding:48px 20px; color:#9aa1b0;">
      <i class="bi bi-journal-text" style="font-size:40px; color:#d3d8e0;"></i>
      <p class="mb-1 mt-3" style="font-weight:600; color:#6c7385;">{{ get_phrase('No online CATs yet.') }}</p>
      <p class="mb-3" style="font-size:13px;">{{ get_phrase('Create your first CAT from your question bank — pick a subject, class and number of questions.') }}</p>
      <a href="javascript:;" class="eBtn btn-primary"
         onclick="rightModal('{{ route('teacher.qbank.generate_modal') }}', '{{ get_phrase('Create Online CAT') }}')"><i class="bi bi-plus"></i> {{ get_phrase('Create Online CAT') }}</a>
      <div class="mt-2"><small>{{ get_phrase('Tip: add questions first in the') }} <a href="{{ route('teacher.qbank') }}">{{ get_phrase('Question bank') }}</a>.</small></div>
    </div>
  @else
  <div class="table-responsive">
    <table class="table eTable eTable-2">
      <thead><tr>
        <th>{{ get_phrase('CAT title') }}</th>
        <th>{{ get_phrase('Class / Section') }}</th>
        <th>{{ get_phrase('Subject') }}</th>
        <th class="text-center">{{ get_phrase('Questions') }}</th>
        <th class="text-center">{{ get_phrase('Marks') }}</th>
        <th class="text-center">{{ get_phrase('Time limit') }}</th>
        <th>{{ get_phrase('Deadline') }}</th>
        <th class="text-center">{{ get_phrase('Submissions') }}</th>
        <th class="text-end">{{ get_phrase('Action') }}</th>
      </tr></thead>
      <tbody>
        @foreach($quizzes as $q)
          @php
            $closed = $q->deadline && $q->deadline < time();
          @endphp
          <tr>
            <td style="font-weight:600; max-width:260px;">{{ $q->title }}</td>
            <td>{{ $classNames[$q->class_id] ?? '—' }} · {{ $sectionNames[$q->section_id] ?? '—' }}</td>
            <td>{{ $subjectNames[$q->subject_id] ?? '—' }}</td>
            <td class="text-center">{{ $qCounts[$q->id] ?? 0 }}</td>
            <td class="text-center">{{ $q->total_marks }}</td>
            <td class="text-center">{{ $q->duration_minutes ? $q->duration_minutes.' '.get_phrase('min') : get_phrase('None') }}</td>
            <td>
              @if($q->deadline)
                {{ date('d M Y, H:i', $q->deadline) }}
                @if($closed)<span class="eBadge ebg-soft-danger">{{ get_phrase('Closed') }}</span>@else<span class="eBadge ebg-soft-success">{{ get_phrase('Open') }}</span>@endif
              @else
                <span class="eBadge ebg-soft-warning">{{ get_phrase('No deadline') }}</span>
              @endif
            </td>
            <td class="text-center"><span class="badge bg-primary">{{ $subCounts[$q->id] ?? 0 }}</span></td>
            <td>
              <div class="kh-actions">
                <a class="eBtn btn-secondary kh-icon" href="{{ route('teacher.quiz.paper', $q->id) }}" target="_blank" title="{{ get_phrase('Preview / print paper') }}">
                  <i class="bi bi-printer"></i>
                </a>
                <a class="eBtn btn-secondary kh-icon" href="{{ route('teacher.quiz.questions', $q->id) }}" title="{{ get_phrase('Manage questions') }}">
                  <i class="bi bi-list-check"></i>
                </a>
                <a class="eBtn btn-primary" href="{{ route('teacher.quiz.review', $q->id) }}">
                  <i class="bi bi-clipboard-check"></i> {{ get_phrase('Grade') }}
                </a>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif

</div></div></div>
@endsection
