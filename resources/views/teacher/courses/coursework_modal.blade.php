<form method="POST" class="d-block" enctype="multipart/form-data"
      action="{{ route('teacher.addons.course.coursework.store') }}">
  @csrf
  <input type="hidden" name="course_id" value="{{ $course->id }}">

  {{-- Class + subject are fixed by the course context — shown, not asked. --}}
  <div class="p-2 mb-3" style="background:#f2f9f6;border:1px solid #d9efe6;border-radius:10px;">
    <div class="d-flex flex-wrap" style="gap:18px;font-size:13px;">
      <span><span class="text-muted">{{ get_phrase('Class') }}:</span> <b>{{ $class->name ?? '—' }}</b></span>
      <span><span class="text-muted">{{ get_phrase('Subject') }}:</span> <b>{{ $subject->name ?? '—' }}</b></span>
    </div>
    <small class="text-muted">{{ get_phrase('This coursework is tied to the course above — you only pick the section.') }}</small>
  </div>

  <div class="form-row">
    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Title') }}</label>
      <input type="text" class="form-control eForm-control" name="title" placeholder="{{ get_phrase('e.g. Week 3 case study') }}" required>
    </div>

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Section') }}</label>
      <select name="section_id" class="form-select eForm-select" required>
        <option value="all">{{ get_phrase('All sections') }}</option>
        @foreach($sections as $sec)
          <option value="{{ $sec->id }}">{{ $sec->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Instructions') }}</label>
      <textarea name="description" class="form-control eForm-control" rows="3" placeholder="{{ get_phrase('What should students do?') }}"></textarea>
    </div>

    <div class="row">
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Total Marks') }}</label>
        <input type="number" class="form-control eForm-control" name="total_marks" value="100" min="1" max="1000" required>
      </div>
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Deadline') }}</label>
        <input type="date" class="form-control eForm-control" name="deadline">
      </div>
    </div>

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Attachment (optional)') }}</label>
      <input type="file" class="form-control eForm-control-file" name="attachment">
    </div>

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Status') }}</label>
      <select name="status" class="form-select eForm-select" required>
        <option value="published">{{ get_phrase('Publish now') }}</option>
        <option value="draft">{{ get_phrase('Save as draft') }}</option>
      </select>
    </div>

    <div class="fpb-7 pt-2">
      <button class="btn-form" type="submit">{{ get_phrase('Add coursework') }}</button>
    </div>
  </div>
</form>
