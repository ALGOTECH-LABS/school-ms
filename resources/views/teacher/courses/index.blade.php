@extends('teacher.navigation')

@section('content')
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ get_phrase('Courses') }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2">
          <li><a href="#">{{ get_phrase('Home') }}</a></li>
          <li><a href="#">{{ get_phrase('Courses') }}</a></li>
        </ul>
      </div>
      <div class="export-btn-area">
        <a href="javascript:;" class="export_btn"
          onclick="rightModal('{{ route('teacher.addons.course.create_modal') }}', '{{ get_phrase('Create course') }}')">
          <i class="bi bi-plus"></i>{{ get_phrase('Add course') }}</a>
      </div>
    </div>
  </div></div>
</div>

<div class="row">
  @forelse($courses as $course)
    @php
      $cls = \App\Models\Classes::find($course->class_id);
      $sub = \App\Models\Subject::find($course->subject_id);
      $topicCount = \App\Models\CourseTopic::where('course_id',$course->id)->count();
      $lessonCount = \App\Models\CourseLesson::where('course_id',$course->id)->count();
    @endphp
    <div class="col-xl-4 col-md-6 mb-4">
      <div class="eSection-wrap h-100 click-card"
           onclick="window.location.href='{{ route('teacher.addons.course.manage', $course->id) }}'">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h5 class="mb-0">{{ $course->title }}</h5>
          <span class="badge {{ $course->status=='published' ? 'bg-success':'bg-secondary' }}">{{ ucfirst($course->status) }}</span>
        </div>
        <p class="text-muted mb-2" style="font-size:13px;">
          {{ $cls->name ?? '-' }} &middot; {{ $sub->name ?? '-' }}
        </p>
        <p class="mb-3" style="font-size:13px;">
          <span class="badge bg-primary">{{ $topicCount }} {{ get_phrase('topics') }}</span>
          <span class="badge bg-primary">{{ $lessonCount }} {{ get_phrase('lessons') }}</span>
        </p>
        <div class="d-flex" style="gap:8px;" onclick="event.stopPropagation()">
          <a class="eBtn btn-primary" href="{{ route('teacher.addons.course.manage', $course->id) }}">{{ get_phrase('Manage content') }}</a>
          <a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this course and all its content?') }}')) postDelete('{{ route('teacher.addons.course.delete', $course->id) }}')">{{ get_phrase('Delete') }}</a>
        </div>
      </div>
    </div>
  @empty
    <div class="col-12"><div class="eSection-wrap text-center">
      {{ get_phrase('No courses yet. Click "Add course" to create your first one.') }}
    </div></div>
  @endforelse
</div>

<div class="mt-2">{{ $courses->links() }}</div>
@endsection
