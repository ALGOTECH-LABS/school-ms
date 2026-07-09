@php
  $class_rooms = DB::table('class_rooms')->where('school_id', auth()->user()->school_id)->get();
  $sd = date('Y-m-d', $exam->starting_time); $st = date('H:i', $exam->starting_time);
  $ed = date('Y-m-d', $exam->ending_time);   $et = date('H:i', $exam->ending_time);
@endphp
<form method="POST" class="d-block" action="{{ route('teacher.offline_exam.update', $exam->id) }}">
  @csrf
  <div class="form-row">
    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Exam / CAT') }}</label>
      <select name="exam_category_id" class="form-select eForm-select" required>
        @foreach($exam_categories as $ec)
          <option value="{{ $ec->id }}" @selected($ec->id == $exam->exam_category_id)>{{ $ec->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="row">
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Class') }}</label>
        <select name="class_id" id="te_class_id_e" class="form-select eForm-select" required onchange="teClassWiseSubjectE(this.value)">
          @foreach($classes as $class)
            <option value="{{ $class->id }}" @selected($class->id == $exam->class_id)>{{ $class->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Subject') }}</label>
        <select name="subject_id" id="te_subject_id_e" class="form-select eForm-select" required>
          @foreach($subjects as $sub)
            <option value="{{ $sub->id }}" @selected($sub->id == $exam->subject_id)>{{ $sub->name }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Class room') }}</label>
      <select name="class_room_id" class="form-select eForm-select">
        <option value="">{{ get_phrase('Select a class room (optional)') }}</option>
        @foreach($class_rooms as $room)
          <option value="{{ $room->id }}" @selected($room->id == $exam->room_number)>{{ $room->name }}</option>
        @endforeach
      </select>
    </div>

    <div class="row">
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Starting date') }}</label>
        <input type="date" name="starting_date" class="form-control eForm-control" value="{{ $sd }}" required>
      </div>
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Starting time') }}</label>
        <input type="time" name="starting_time" class="form-control eForm-control" value="{{ $st }}" required>
      </div>
    </div>

    <div class="row">
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Ending date') }}</label>
        <input type="date" name="ending_date" class="form-control eForm-control" value="{{ $ed }}" required>
      </div>
      <div class="col-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Ending time') }}</label>
        <input type="time" name="ending_time" class="form-control eForm-control" value="{{ $et }}" required>
      </div>
    </div>

    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Total marks') }}</label>
      <input type="number" name="total_marks" class="form-control eForm-control" value="{{ $exam->total_marks }}" min="1" max="1000" required>
    </div>

    <div class="fpb-7 pt-2">
      <button class="btn-form" type="submit">{{ get_phrase('Update exam') }}</button>
    </div>
  </div>
</form>

<script type="text/javascript">
  "use strict";
  function teClassWiseSubjectE(classId){
    if(!classId){ return; }
    var url = "{{ route('class_wise_subject', ['id' => ':cid']) }}".replace(':cid', classId);
    $.ajax({ url: url, success: function(res){ $('#te_subject_id_e').html(res); } });
  }
</script>
