<form method="POST" class="d-block" action="{{ route('teacher.addons.course.sitting_cat.store') }}">
  @csrf
  <input type="hidden" name="course_id" value="{{ $course->id }}">

  {{-- Class + subject are fixed by the course context — shown, not asked. --}}
  <div class="p-2 mb-3" style="background:#fff8ec;border:1px solid #f2e2b3;border-radius:10px;">
    <div class="d-flex flex-wrap" style="gap:18px;font-size:13px;">
      <span><span class="text-muted">{{ get_phrase('Class') }}:</span> <b>{{ $class->name ?? '—' }}</b></span>
      <span><span class="text-muted">{{ get_phrase('Subject') }}:</span> <b>{{ $subject->name ?? '—' }}</b></span>
    </div>
    <small class="text-muted">{{ get_phrase('A sitting CAT is a physical paper — students sit it in a room and you enter the marks afterwards.') }}</small>
  </div>

  <div class="form-row">
    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('CAT / exam title') }}</label>
      <input type="text" class="form-control eForm-control" name="title" placeholder="{{ get_phrase('e.g. CAT 2 — Nursing Practice (sitting)') }}" required>
    </div>

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Exam category') }}</label>
      <select name="exam_category_id" class="form-select eForm-select" required>
        <option value="new">{{ get_phrase('New category (named after the title) — recommended') }}</option>
        @foreach($categories as $cat)
          <option value="{{ $cat->id }}">{{ $cat->name }}</option>
        @endforeach
      </select>
      <small class="text-muted">{{ get_phrase('Marks are stored per category — use a fresh category per CAT so papers never overwrite each other.') }}</small>
    </div>

    <div class="row">
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Starts at') }}</label>
        <input type="datetime-local" class="form-control eForm-control" name="starting_at" required>
      </div>
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Ends at') }}</label>
        <input type="datetime-local" class="form-control eForm-control" name="ending_at" required>
      </div>
    </div>

    <div class="row">
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Room / venue') }}</label>
        <input type="text" class="form-control eForm-control" name="room_number" placeholder="{{ get_phrase('e.g. Lecture Hall A') }}">
      </div>
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Total marks') }}</label>
        <input type="number" class="form-control eForm-control" name="total_marks" value="30" min="1" max="1000" required>
      </div>
    </div>

    <div class="fpb-7 pt-2">
      <button class="btn-form" type="submit">{{ get_phrase('Schedule sitting CAT') }}</button>
    </div>
  </div>
</form>
