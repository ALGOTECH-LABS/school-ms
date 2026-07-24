<form method="POST" class="d-block" action="{{ route('teacher.addons.course.cat.store') }}">
  @csrf
  <input type="hidden" name="course_id" value="{{ $course->id }}">

  {{-- Class + subject are fixed by the course context — shown, not asked. --}}
  <div class="p-2 mb-3" style="background:#f2f9f6;border:1px solid #d9efe6;border-radius:10px;">
    <div class="d-flex flex-wrap" style="gap:18px;font-size:13px;">
      <span><span class="text-muted">{{ get_phrase('Class') }}:</span> <b>{{ $class->name ?? '—' }}</b></span>
      <span><span class="text-muted">{{ get_phrase('Subject') }}:</span> <b>{{ $subject->name ?? '—' }}</b></span>
    </div>
    @if($bankCount > 0)
      <small class="text-muted">{{ $bankCount }} {{ get_phrase('questions in the bank for this subject — the CAT draws randomly from them.') }}</small>
    @else
      <small style="color:#c0392b;font-weight:600;">
        {{ get_phrase('No questions in the bank for this subject yet — add some in the') }}
        <a href="{{ route('teacher.qbank') }}" target="_blank">{{ get_phrase('Question bank') }}</a> {{ get_phrase('first.') }}
      </small>
    @endif
  </div>

  <div class="form-row">
    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('CAT title') }}</label>
      <input type="text" class="form-control eForm-control" name="title" placeholder="{{ get_phrase('e.g. CAT 1 — Anatomy') }}" required>
    </div>

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Section') }}</label>
      <select name="section_id" class="form-select eForm-select" required>
        <option value="all">{{ get_phrase('All sections (same paper for each)') }}</option>
        @foreach($sections as $sec)
          <option value="{{ $sec->id }}">{{ $sec->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="row">
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Number of questions') }}</label>
        <input type="number" class="form-control eForm-control" name="count" value="10" min="1" max="100" required>
      </div>
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Difficulty') }}</label>
        <select name="difficulty" class="form-select eForm-select">
          <option value="">{{ get_phrase('Any') }}</option>
          @foreach(['easy','medium','hard'] as $d)
            <option value="{{ $d }}">{{ ucfirst($d) }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="row">
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Question type') }}</label>
        <select name="qtype" class="form-select eForm-select">
          <option value="">{{ get_phrase('Any') }}</option>
          <option value="mcq">MCQ</option>
          <option value="truefalse">True/False</option>
          <option value="short">{{ get_phrase('Short answer') }}</option>
          <option value="essay">{{ get_phrase('Essay') }}</option>
        </select>
      </div>
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Topic filter (optional)') }}</label>
        <input type="text" class="form-control eForm-control" name="topic" placeholder="{{ get_phrase('Leave blank for all topics') }}">
      </div>
    </div>

    <div class="row">
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Time limit (minutes)') }}</label>
        <input type="number" class="form-control eForm-control" name="duration_minutes" min="1" max="600" placeholder="{{ get_phrase('optional') }}">
      </div>
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Deadline / closes at') }}</label>
        <input type="datetime-local" class="form-control eForm-control" name="deadline">
      </div>
    </div>
    <small class="text-muted d-block mb-2">{{ get_phrase('A time limit starts a countdown when the student opens the CAT; the deadline hard-closes it for everyone.') }}</small>

    <div class="fpb-7">
      <label class="d-flex align-items-center" style="gap:8px;cursor:pointer;">
        <input type="checkbox" name="start_empty" value="1">
        <span>{{ get_phrase('Start empty — I will pick / write the questions myself') }}</span>
      </label>
      <small class="text-muted">{{ get_phrase('Skips the random draw and opens the question picker after creating.') }}</small>
    </div>

    <div class="fpb-7 pt-2">
      <button class="btn-form" type="submit">{{ get_phrase('Create CAT') }}</button>
    </div>
  </div>
</form>
