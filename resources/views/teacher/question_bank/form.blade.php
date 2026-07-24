@php
  $isEdit = $question !== null;
  $attachQuiz = $attachQuiz ?? null;
  $opts = $isEdit && is_array($question->options) ? $question->options : ['', '', '', ''];
  while (count($opts) < 4) $opts[] = '';
@endphp
<form method="POST" class="d-block"
      action="{{ $isEdit ? route('teacher.qbank.update', $question->id) : route('teacher.qbank.store') }}">
  @csrf
  @if($attachQuiz)
    <input type="hidden" name="attach_quiz_id" value="{{ $attachQuiz->id }}">
    <div class="p-2 mb-3" style="background:#f2f9f6;border:1px solid #d9efe6;border-radius:10px;font-size:13px;">
      {{ get_phrase('This question will be saved to the bank AND added to') }} <b>{{ $attachQuiz->title }}</b>.
    </div>
  @endif
  <div class="form-row">
    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Question type') }}</label>
      <select class="form-select eForm-select" name="type" id="q_type" onchange="qTypeToggle()">
        <option value="mcq"       {{ $isEdit && $question->type=='mcq'?'selected':'' }}>{{ get_phrase('Multiple choice') }}</option>
        <option value="truefalse" {{ $isEdit && $question->type=='truefalse'?'selected':'' }}>{{ get_phrase('True / False') }}</option>
        <option value="short"     {{ $isEdit && $question->type=='short'?'selected':'' }}>{{ get_phrase('Short answer') }}</option>
        <option value="essay"     {{ $isEdit && $question->type=='essay'?'selected':'' }}>{{ get_phrase('Essay') }}</option>
      </select>
    </div>

    @if($attachQuiz)
      {{-- class + subject fixed by the CAT --}}
      <input type="hidden" name="class_id" value="{{ $attachQuiz->class_id }}">
      <input type="hidden" name="subject_id" value="{{ $attachQuiz->subject_id }}">
    @else
    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Class') }}</label>
      <select class="form-select eForm-select" id="q_class" name="class_id" onchange="qClassSubjects(this.value)" required>
        <option value="">{{ get_phrase('Select a class') }}</option>
        @foreach($classes as $class)
          <option value="{{ $class->id }}" {{ $isEdit && $question->class_id==$class->id?'selected':'' }}>{{ $class->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Subject') }}</label>
      <select class="form-select eForm-select" id="q_subject" name="subject_id" required>
        <option value="">{{ get_phrase('Select a subject') }}</option>
      </select>
    </div>
    @endif

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Question') }}</label>
      <textarea class="form-control eForm-control" name="question" rows="2" required>{{ $isEdit ? $question->question : '' }}</textarea>
    </div>

    {{-- MCQ options --}}
    <div class="fpb-7 q-block q-mcq">
      <label class="eForm-label">{{ get_phrase('Options (tick the correct one)') }}</label>
      @foreach([0,1,2,3] as $i)
        <div class="d-flex align-items-center mb-2" style="gap:8px;">
          <input type="radio" name="correct_option" value="{{ $i }}" {{ $isEdit && (string)$question->correct_answer===(string)$i ? 'checked':'' }}>
          <input type="text" class="form-control eForm-control" name="options[]" value="{{ $opts[$i] ?? '' }}" placeholder="{{ get_phrase('Option') }} {{ $i+1 }}">
        </div>
      @endforeach
    </div>

    {{-- True/False --}}
    <div class="fpb-7 q-block q-truefalse">
      <label class="eForm-label">{{ get_phrase('Correct answer') }}</label>
      <select class="form-select eForm-select" name="correct_tf">
        <option value="true"  {{ $isEdit && $question->correct_answer=='true'?'selected':'' }}>{{ get_phrase('True') }}</option>
        <option value="false" {{ $isEdit && $question->correct_answer=='false'?'selected':'' }}>{{ get_phrase('False') }}</option>
      </select>
    </div>

    {{-- Short answer --}}
    <div class="fpb-7 q-block q-short">
      <label class="eForm-label">{{ get_phrase('Expected answer (optional — enables auto-grading)') }}</label>
      <input type="text" class="form-control eForm-control" name="expected_answer" value="{{ $isEdit && $question->type=='short' ? $question->correct_answer : '' }}" placeholder="{{ get_phrase('Leave blank to grade manually') }}">
    </div>

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Topic (optional)') }}</label>
      <input type="text" class="form-control eForm-control" name="topic" value="{{ $isEdit ? $question->topic : '' }}" placeholder="{{ get_phrase('e.g. Skeletal system') }}">
    </div>

    <div class="fpb-7 d-flex" style="gap:10px;">
      <div style="flex:1;">
        <label class="eForm-label">{{ get_phrase('Difficulty') }}</label>
        <select class="form-select eForm-select" name="difficulty">
          @foreach(['easy','medium','hard'] as $d)
            <option value="{{ $d }}" {{ $isEdit && $question->difficulty==$d?'selected':'' }}>{{ ucfirst($d) }}</option>
          @endforeach
        </select>
      </div>
      <div style="flex:1;">
        <label class="eForm-label">{{ get_phrase('Marks') }}</label>
        <input type="number" min="1" class="form-control eForm-control" name="marks" value="{{ $isEdit ? $question->marks : 1 }}">
      </div>
    </div>

    <div class="fpb-7 pt-2">
      <button class="btn-form" type="submit">{{ $isEdit ? get_phrase('Update question') : get_phrase('Add question') }}</button>
    </div>
  </div>
</form>

<script type="text/javascript">
  "use strict";
  function qTypeToggle() {
    var t = document.getElementById('q_type').value;
    document.querySelectorAll('.q-block').forEach(function (b) { b.style.display = 'none'; });
    var show = document.querySelector('.q-' + t);
    if (show) show.style.display = '';
  }
  function qClassSubjects(classId, preselect) {
    $.ajax({
      url: '{{ route('teacher.qbank.class_subjects') }}',
      data: { class_id: classId },
      success: function (r) {
        $('#q_subject').html(r);
        if (preselect) $('#q_subject').val(preselect);
      }
    });
  }
  $(function () {
    qTypeToggle();
    @if($isEdit)
      qClassSubjects('{{ $question->class_id }}', '{{ $question->subject_id }}');
    @endif
  });
</script>
