@extends('teacher.navigation')

@section('content')
@php
  $cls = \App\Models\Classes::find($course->class_id);
  $sub = \App\Models\Subject::find($course->subject_id);
@endphp
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ $course->title }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2">
          <li><a href="{{ route('teacher.addons.courses') }}">{{ get_phrase('Courses') }}</a></li>
          <li><a href="#">{{ $cls->name ?? '' }} &middot; {{ $sub->name ?? '' }}</a></li>
        </ul>
      </div>
      <a class="eBtn btn-secondary" href="{{ route('teacher.addons.courses') }}">{{ get_phrase('Back to courses') }}</a>
    </div>
  </div></div>
</div>

<div class="row">
  <div class="col-12">
    <div class="eSection-wrap mb-3">
      <form method="POST" action="{{ route('teacher.addons.course.topic.store') }}" class="d-flex flex-wrap align-items-end" style="gap:10px;">
        @csrf
        <input type="hidden" name="course_id" value="{{ $course->id }}">
        <div style="flex:1; min-width:240px;">
          <label class="eForm-label">{{ get_phrase('New topic / chapter') }}</label>
          <input type="text" name="title" class="form-control eForm-control" placeholder="{{ get_phrase('e.g. Week 1 — Introduction') }}" required>
        </div>
        <button class="eBtn btn-primary" type="submit"><i class="bi bi-plus"></i> {{ get_phrase('Add topic') }}</button>
      </form>
    </div>

    @forelse($course->topics as $topic)
      <div class="eSection-wrap mb-3">
        <details open>
          <summary style="cursor:pointer; list-style:none;">
            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
              <h5 class="mb-0">{{ $topic->title }} <span class="badge bg-primary">{{ $topic->lessons->count() }} {{ get_phrase('lessons') }}</span></h5>
              <div class="d-flex" style="gap:8px;">
                <a class="eBtn btn-secondary" href="javascript:;"
                   onclick="rightModal('{{ route('teacher.addons.course.lesson.create_modal', $topic->id) }}', '{{ get_phrase('Add lesson') }}')">
                   <i class="bi bi-plus"></i> {{ get_phrase('Add lesson') }}</a>
                <a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this topic and its lessons?') }}')) postDelete('{{ route('teacher.addons.course.topic.delete', $topic->id) }}')">{{ get_phrase('Delete topic') }}</a>
              </div>
            </div>
          </summary>

          <div class="mt-3">
            @forelse($topic->lessons as $lesson)
              <div class="p-3 mb-3" style="border:1px solid #eee; border-radius:8px;">
                <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                  <h6 class="mb-0"><i class="bi bi-journal-text"></i> {{ $lesson->title }}</h6>
                  <div class="d-flex" style="gap:6px;">
                    <a class="eBtn btn-secondary" href="javascript:;"
                       onclick="rightModal('{{ route('teacher.addons.course.lesson.edit_modal', $lesson->id) }}', '{{ get_phrase('Edit lesson') }}')">{{ get_phrase('Edit') }}</a>
                    <a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this lesson?') }}')) postDelete('{{ route('teacher.addons.course.lesson.delete', $lesson->id) }}')">{{ get_phrase('Delete') }}</a>
                  </div>
                </div>

                {{-- materials --}}
                <div class="mt-2">
                  @foreach($lesson->materials as $m)
                    <div class="d-flex align-items-center justify-content-between py-1" style="border-bottom:1px dashed #eee;">
                      <div>
                        <span class="badge bg-secondary">{{ ucfirst($m->type) }}</span>
                        @if($m->type=='file')
                          <a target="_blank" href="{{ asset('assets/uploads/course_materials/'.$m->file) }}">{{ $m->title }}</a>
                        @else
                          <a target="_blank" href="{{ $m->url }}">{{ $m->title }}</a>
                        @endif
                      </div>
                      <a class="text-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Remove material?') }}')) postDelete('{{ route('teacher.addons.course.material.delete', $m->id) }}')"><i class="bi bi-x-circle"></i></a>
                    </div>
                  @endforeach
                </div>

                {{-- add material --}}
                <form method="POST" action="{{ route('teacher.addons.course.material.store') }}" enctype="multipart/form-data"
                      class="material-form d-flex flex-wrap align-items-end mt-2" style="gap:8px;">
                  @csrf
                  <input type="hidden" name="lesson_id" value="{{ $lesson->id }}">
                  <input type="text" name="title" class="form-control eForm-control" placeholder="{{ get_phrase('Material title') }}" style="flex:1; min-width:160px;" required>
                  <select name="type" class="form-select eForm-select mat-type" style="max-width:130px;" onchange="toggleMat(this)">
                    <option value="file">{{ get_phrase('File') }}</option>
                    <option value="link">{{ get_phrase('Link') }}</option>
                    <option value="video">{{ get_phrase('Video URL') }}</option>
                  </select>
                  <input type="file" name="file" class="form-control eForm-control-file mat-file" style="max-width:210px;">
                  <input type="url" name="url" class="form-control eForm-control mat-url" placeholder="https://..." style="max-width:210px; display:none;">
                  <button class="eBtn btn-primary" type="submit">{{ get_phrase('Add material') }}</button>
                </form>
              </div>
            @empty
              <p class="text-muted mb-0">{{ get_phrase('No lessons in this topic yet.') }}</p>
            @endforelse
          </div>
        </details>
      </div>
    @empty
      <div class="eSection-wrap text-center">{{ get_phrase('Start by adding a topic above.') }}</div>
    @endforelse
  </div>
</div>

<script type="text/javascript">
  "use strict";
  function toggleMat(sel){
    var f = sel.closest('form');
    var isFile = sel.value === 'file';
    f.querySelector('.mat-file').style.display = isFile ? '' : 'none';
    f.querySelector('.mat-url').style.display  = isFile ? 'none' : '';
  }
</script>
@endsection
