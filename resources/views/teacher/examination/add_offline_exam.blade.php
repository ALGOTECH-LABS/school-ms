@php $class_rooms = DB::table('class_rooms')->where('school_id', auth()->user()->school_id)->get(); @endphp
<form method="POST" class="d-block" action="{{ route('teacher.create.offline_exam') }}">
  @csrf
  <div class="p-2 mb-3" style="background:#f2f9f6;border:1px solid #d9efe6;border-radius:10px;">
    <small class="text-muted">{{ get_phrase('Pick the assessment (CAT 1 / CAT 2 / Final), then the class & subject. This unlocks the marks sheet under Examination → Marks.') }}</small>
  </div>

  <div class="form-row">
    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Exam / CAT') }}</label>
      <select name="exam_category_id" class="form-select eForm-select" required>
        <option value="">{{ get_phrase('Select exam / CAT') }}</option>
        @foreach($exam_categories as $ec)
          <option value="{{ $ec->id }}">{{ $ec->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="row">
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Class') }}</label>
        <select name="class_id" id="te_class_id" class="form-select eForm-select" required onchange="teClassWiseSubject(this.value)">
          <option value="">{{ get_phrase('Select a class') }}</option>
          @foreach($classes as $class)
            <option value="{{ $class->id }}">{{ $class->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Subject') }}</label>
        <select name="subject_id" id="te_subject_id" class="form-select eForm-select" required>
          <option value="">{{ get_phrase('First select a class') }}</option>
        </select>
      </div>
    </div>

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Class room') }}</label>
      <select name="class_room_id" class="form-select eForm-select">
        <option value="">{{ get_phrase('Select a class room (optional)') }}</option>
        @foreach($class_rooms as $room)
          <option value="{{ $room->id }}">{{ $room->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="row">
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Starting date') }}</label>
        <input type="date" name="starting_date" class="form-control eForm-control" value="{{ date('Y-m-d') }}" required>
      </div>
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Starting time') }}</label>
        <input type="time" name="starting_time" class="form-control eForm-control" value="09:00" required>
      </div>
    </div>

    <div class="row">
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Ending date') }}</label>
        <input type="date" name="ending_date" class="form-control eForm-control" value="{{ date('Y-m-d') }}" required>
      </div>
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Ending time') }}</label>
        <input type="time" name="ending_time" class="form-control eForm-control" value="11:00" required>
      </div>
    </div>

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Total marks') }}</label>
      <input type="number" name="total_marks" class="form-control eForm-control" value="100" min="1" max="1000" required>
    </div>

    <div class="fpb-7 pt-2">
      <button class="btn-form" type="submit">{{ get_phrase('Create exam') }}</button>
    </div>
  </div>
</form>

<script type="text/javascript">
  "use strict";
  function teClassWiseSubject(classId){
    if(!classId){ return; }
    var url = "{{ route('class_wise_subject', ['id' => ':cid']) }}".replace(':cid', classId);
    $.ajax({ url: url, success: function(res){ $('#te_subject_id').html(res); } });
  }
</script>
