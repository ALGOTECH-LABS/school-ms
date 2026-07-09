@extends('teacher.navigation')

@section('content')
@php
  $cls = \App\Models\Classes::find($course->class_id);
  $sub = \App\Models\Subject::find($course->subject_id);
  $platformMeta = [
    'zoom'  => ['label' => 'Zoom',            'icon' => 'bi-camera-video-fill', 'color' => '#2D8CFF'],
    'meet'  => ['label' => 'Google Meet',     'icon' => 'bi-camera-video-fill', 'color' => '#00897B'],
    'teams' => ['label' => 'Microsoft Teams', 'icon' => 'bi-microsoft-teams',   'color' => '#5059C9'],
    'other' => ['label' => 'Online',          'icon' => 'bi-link-45deg',        'color' => '#6c757d'],
  ];
@endphp

<style>
  .kh-course-hero{background:linear-gradient(120deg,#00955f,#00b877);border-radius:16px;padding:22px 26px;color:#fff;margin-bottom:18px;}
  .kh-course-hero h4{color:#fff;font-weight:800;margin:0;}
  .kh-course-hero .crumb a{color:rgba(255,255,255,.85);text-decoration:none;}
  .kh-stat{background:rgba(255,255,255,.16);border-radius:12px;padding:10px 16px;text-align:center;min-width:92px;}
  .kh-stat .n{font-size:20px;font-weight:800;line-height:1;}
  .kh-stat .l{font-size:11.5px;opacity:.9;text-transform:uppercase;letter-spacing:.4px;}
  .kh-tabs{border-bottom:2px solid #eef1f0;margin-bottom:18px;gap:4px;}
  .kh-tabs .nav-link{border:0;border-bottom:3px solid transparent;color:#69707d;font-weight:600;padding:10px 18px;border-radius:0;}
  .kh-tabs .nav-link.active{color:#00955f;border-bottom-color:#00955f;background:transparent;}
  .kh-tabs .nav-link .badge{font-size:11px;}
  .kh-card{background:#fff;border:1px solid #eef1f0;border-radius:14px;padding:20px 22px;margin-bottom:16px;}
  .kh-sess{border:1px solid #eef1f0;border-radius:12px;padding:14px 16px;margin-bottom:12px;display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;}
  .kh-sess.live{border-color:#00955f;box-shadow:0 0 0 2px rgba(0,149,95,.12);}
  .kh-plat{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;flex:0 0 auto;}
  .kh-pill{display:inline-block;font-size:11px;font-weight:700;padding:2px 10px;border-radius:20px;}
  .kh-pill.green{background:#e5f7ef;color:#00794c;}
  .kh-pill.red{background:#fdECEC;color:#c0392b;}
  .kh-pill.grey{background:#eef1f0;color:#5a6270;}
  .kh-progress{height:7px;border-radius:5px;background:#eef1f0;overflow:hidden;min-width:90px;}
  .kh-progress > span{display:block;height:100%;background:#00955f;}
</style>

{{-- ===== HERO ===== --}}
<div class="kh-course-hero">
  <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:16px;">
    <div>
      <ul class="d-flex align-items-center crumb mb-1" style="gap:6px;font-size:13px;list-style:none;padding:0;margin:0;">
        <li><a href="{{ route('teacher.addons.courses') }}"><i class="bi bi-arrow-left"></i> {{ get_phrase('Courses') }}</a></li>
        <li style="opacity:.6;">/</li>
        <li style="opacity:.9;">{{ $cls->name ?? '' }} &middot; {{ $sub->name ?? '' }}</li>
      </ul>
      <h4>{{ $course->title }}</h4>
    </div>
    <div class="d-flex" style="gap:10px;">
      <div class="kh-stat"><div class="n">{{ $students->count() }}</div><div class="l">{{ get_phrase('Students') }}</div></div>
      <div class="kh-stat"><div class="n">{{ $course->topics->count() }}</div><div class="l">{{ get_phrase('Topics') }}</div></div>
      <div class="kh-stat"><div class="n">{{ $coursework->count() }}</div><div class="l">{{ get_phrase('Coursework') }}</div></div>
      <div class="kh-stat"><div class="n">{{ $upcoming->count() }}</div><div class="l">{{ get_phrase('Live') }}</div></div>
    </div>
  </div>
</div>

{{-- ===== TABS ===== --}}
<ul class="nav kh-tabs" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-content" type="button">
    <i class="bi bi-collection-play"></i> {{ get_phrase('Content') }}</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-students" type="button">
    <i class="bi bi-people"></i> {{ get_phrase('Students') }} <span class="badge bg-secondary">{{ $students->count() }}</span></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-coursework" type="button">
    <i class="bi bi-journal-check"></i> {{ get_phrase('Coursework') }} <span class="badge bg-secondary">{{ $coursework->count() }}</span></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sessions" type="button">
    <i class="bi bi-camera-video"></i> {{ get_phrase('Online Sessions') }} <span class="badge bg-success">{{ $upcoming->count() }}</span></button></li>
</ul>

<div class="tab-content">

  {{-- ============================ CONTENT TAB ============================ --}}
  <div class="tab-pane fade show active" id="tab-content">
    <div class="kh-card">
      <form method="POST" action="{{ route('teacher.addons.course.topic.store') }}" class="d-flex flex-wrap align-items-end" style="gap:10px;">
        @csrf
        <input type="hidden" name="course_id" value="{{ $course->id }}">
        <div style="flex:1; min-width:240px;">
          <label class="eForm-label">{{ get_phrase('New topic / chapter') }}</label>
          <input type="text" name="title" class="form-control eForm-control" placeholder="{{ get_phrase('e.g. Week 1 - Introduction') }}" required>
        </div>
        <button class="eBtn btn-primary" type="submit"><i class="bi bi-plus"></i> {{ get_phrase('Add topic') }}</button>
      </form>
    </div>

    @forelse($course->topics as $topic)
      <div class="kh-card">
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
      <div class="kh-card text-center text-muted">{{ get_phrase('Start by adding a topic above.') }}</div>
    @endforelse
  </div>

  {{-- ============================ STUDENTS TAB ============================ --}}
  <div class="tab-pane fade" id="tab-students">
    <div class="kh-card">
      <div class="d-flex justify-content-between align-items-center flex-wrap mb-2" style="gap:10px;">
        <h5 class="mb-0"><i class="bi bi-people-fill me-2" style="color:#00955f;"></i>{{ get_phrase('Enrolled students') }}
          <span class="badge bg-primary">{{ $students->count() }}</span></h5>
        <span class="text-muted" style="font-size:12.5px;">
          {{ get_phrase('Everyone in') }} <b>{{ $className ?? '' }}</b> {{ get_phrase('is enrolled. Manage the roster in Admissions.') }}
        </span>
      </div>
      @if($students->count())
        <div class="table-responsive">
          <table class="table eTable eTable-2 mb-0" style="font-size:13.5px;">
            <thead><tr>
              <th>#</th><th>{{ get_phrase('Student') }}</th><th>{{ get_phrase('Section') }}</th>
              <th>{{ get_phrase('Email') }}</th><th style="min-width:150px;">{{ get_phrase('Coursework progress') }}</th>
            </tr></thead>
            <tbody>
              @foreach($students as $i => $s)
                @php $pct = $s->total_coursework ? round($s->submitted_count / $s->total_coursework * 100) : 0; @endphp
                <tr>
                  <td>{{ $i+1 }}</td>
                  <td style="font-weight:600;">{{ $s->name }}</td>
                  <td><span class="badge bg-secondary">{{ $s->section_name }}</span></td>
                  <td class="text-muted">{{ $s->email }}</td>
                  <td>
                    @if($s->total_coursework)
                      <div class="d-flex align-items-center" style="gap:8px;">
                        <div class="kh-progress" style="flex:1;"><span style="width:{{ $pct }}%;"></span></div>
                        <small class="text-muted">{{ $s->submitted_count }}/{{ $s->total_coursework }}</small>
                      </div>
                    @else
                      <small class="text-muted">{{ get_phrase('No coursework yet') }}</small>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mb-0">{{ get_phrase('No students are enrolled in this class yet. Admissions → assign students to') }} <b>{{ $className ?? '' }}</b>.</p>
      @endif
    </div>
  </div>

  {{-- ============================ COURSEWORK TAB ============================ --}}
  <div class="tab-pane fade" id="tab-coursework">
    <div class="kh-card">
      <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
        <h5 class="mb-0"><i class="bi bi-journal-check me-2" style="color:#00955f;"></i>{{ get_phrase('Coursework & assignments') }}</h5>
        <a class="eBtn btn-primary" href="javascript:;"
           onclick="rightModal('{{ route('teacher.assignment.create_modal') }}', '{{ get_phrase('Add coursework') }}')">
           <i class="bi bi-plus"></i> {{ get_phrase('Add coursework') }}</a>
      </div>
      <p class="text-muted" style="font-size:12.5px;margin-top:-8px;">
        {{ get_phrase('Coursework set for') }} <b>{{ $cls->name ?? '' }} &middot; {{ $sub->name ?? '' }}</b>.
        {{ get_phrase('When adding, pick this class and subject so it appears here.') }}
      </p>

      @if($coursework->count())
        <div class="table-responsive">
          <table class="table eTable eTable-2 mb-0" style="font-size:13.5px;">
            <thead><tr>
              <th>{{ get_phrase('Title') }}</th><th>{{ get_phrase('Type') }}</th><th>{{ get_phrase('Deadline') }}</th>
              <th class="text-center">{{ get_phrase('Submissions') }}</th><th class="text-center">{{ get_phrase('Graded') }}</th>
              <th class="text-end">{{ get_phrase('Action') }}</th>
            </tr></thead>
            <tbody>
              @foreach($coursework as $cw)
                <tr>
                  <td style="font-weight:600;">{{ $cw->title }}</td>
                  <td>
                    @if($cw->is_quiz)<span class="kh-pill green">{{ get_phrase('Quiz') }}</span>
                    @else<span class="kh-pill grey">{{ get_phrase('Assignment') }}</span>@endif
                  </td>
                  <td>{{ $cw->deadline ? date('d M Y, H:i', strtotime($cw->deadline)) : '—' }}</td>
                  <td class="text-center"><span class="badge bg-primary">{{ $cw->submission_count }}</span></td>
                  <td class="text-center"><span class="badge bg-success">{{ $cw->graded_count }}</span></td>
                  <td class="text-end">
                    <a class="eBtn btn-secondary" href="{{ route('teacher.assignment.show', $cw->id) }}">
                      <i class="bi bi-eye"></i> {{ get_phrase('Open & grade') }}</a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="text-center text-muted py-4">
          <i class="bi bi-journal-x" style="font-size:34px;opacity:.4;"></i>
          <p class="mb-0 mt-2">{{ get_phrase('No coursework yet. Click “Add coursework” to set an assignment or quiz for this class.') }}</p>
        </div>
      @endif
    </div>
  </div>

  {{-- ============================ ONLINE SESSIONS TAB ============================ --}}
  <div class="tab-pane fade" id="tab-sessions">
    <div class="row">
      <div class="col-lg-5">
        <div class="kh-card">
          <h5 class="mb-3"><i class="bi bi-calendar-plus me-2" style="color:#00955f;"></i>{{ get_phrase('Schedule an online session') }}</h5>
          <form method="POST" action="{{ route('teacher.addons.course.session.store') }}">
            @csrf
            <input type="hidden" name="course_id" value="{{ $course->id }}">
            <div class="mb-2">
              <label class="eForm-label">{{ get_phrase('Session title') }}</label>
              <input type="text" name="title" class="form-control eForm-control" placeholder="{{ get_phrase('e.g. Live tutorial - Cardiac cycle') }}" required>
            </div>
            <div class="row">
              <div class="col-6 mb-2">
                <label class="eForm-label">{{ get_phrase('Platform') }}</label>
                <select name="platform" class="form-select eForm-select" required>
                  <option value="zoom">Zoom</option>
                  <option value="meet">Google Meet</option>
                  <option value="teams">Microsoft Teams</option>
                  <option value="other">{{ get_phrase('Other') }}</option>
                </select>
              </div>
              <div class="col-6 mb-2">
                <label class="eForm-label">{{ get_phrase('Duration (min)') }}</label>
                <input type="number" name="duration_minutes" class="form-control eForm-control" value="60" min="5" max="600" required>
              </div>
            </div>
            <div class="mb-2">
              <label class="eForm-label">{{ get_phrase('Date & time') }}</label>
              <input type="datetime-local" name="session_date" class="form-control eForm-control" required>
            </div>
            <div class="mb-2">
              <label class="eForm-label">{{ get_phrase('Meeting link') }}</label>
              <input type="url" name="meeting_url" class="form-control eForm-control" placeholder="https://zoom.us/j/...">
            </div>
            <div class="mb-3">
              <label class="eForm-label">{{ get_phrase('Notes (optional)') }}</label>
              <textarea name="description" class="form-control eForm-control" rows="2" placeholder="{{ get_phrase('What will be covered, prep, passcode…') }}"></textarea>
            </div>
            <button class="eBtn btn-primary w-100" type="submit"><i class="bi bi-camera-video"></i> {{ get_phrase('Schedule session') }}</button>
          </form>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="kh-card">
          <h5 class="mb-3"><i class="bi bi-broadcast me-2" style="color:#00955f;"></i>{{ get_phrase('Upcoming sessions') }}
            <span class="badge bg-success">{{ $upcoming->count() }}</span></h5>
          @forelse($upcoming as $s)
            @php $p = $platformMeta[$s->platform] ?? $platformMeta['other']; @endphp
            <div class="kh-sess {{ $s->is_live ? 'live' : '' }}">
              <div class="d-flex" style="gap:12px;">
                <div class="kh-plat" style="background:{{ $p['color'] }};"><i class="bi {{ $p['icon'] }}"></i></div>
                <div>
                  <div style="font-weight:700;">{{ $s->title }}
                    @if($s->is_live)<span class="kh-pill red">● {{ get_phrase('LIVE NOW') }}</span>@endif
                  </div>
                  <div class="text-muted" style="font-size:12.5px;">
                    <i class="bi bi-calendar-event"></i> {{ $s->session_date->format('D, d M Y · H:i') }}
                    · {{ $s->duration_minutes }} {{ get_phrase('min') }} · {{ $p['label'] }}
                  </div>
                  @if($s->description)<div class="text-muted mt-1" style="font-size:12px;">{{ $s->description }}</div>@endif
                </div>
              </div>
              <div class="d-flex align-items-center" style="gap:8px;">
                @if($s->meeting_url)
                  <a class="eBtn btn-primary" target="_blank" href="{{ $s->meeting_url }}"><i class="bi bi-box-arrow-up-right"></i> {{ get_phrase('Join') }}</a>
                @endif
                <a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this session?') }}')) postDelete('{{ route('teacher.addons.course.session.delete', $s->id) }}')"><i class="bi bi-trash"></i></a>
              </div>
            </div>
          @empty
            <div class="text-center text-muted py-4">
              <i class="bi bi-camera-video-off" style="font-size:34px;opacity:.4;"></i>
              <p class="mb-0 mt-2">{{ get_phrase('No upcoming sessions. Schedule one on the left.') }}</p>
            </div>
          @endforelse

          @if($past->count())
            <h6 class="text-muted mt-4 mb-2" style="text-transform:uppercase;letter-spacing:.4px;font-size:11.5px;">{{ get_phrase('Past & cancelled') }}</h6>
            @foreach($past as $s)
              @php $p = $platformMeta[$s->platform] ?? $platformMeta['other']; @endphp
              <div class="kh-sess" style="opacity:.7;">
                <div class="d-flex" style="gap:12px;">
                  <div class="kh-plat" style="background:#adb5bd;"><i class="bi {{ $p['icon'] }}"></i></div>
                  <div>
                    <div style="font-weight:700;">{{ $s->title }}
                      @if($s->status==='cancelled')<span class="kh-pill red">{{ get_phrase('Cancelled') }}</span>
                      @else<span class="kh-pill grey">{{ get_phrase('Ended') }}</span>@endif
                    </div>
                    <div class="text-muted" style="font-size:12.5px;">
                      <i class="bi bi-calendar-event"></i> {{ $s->session_date->format('D, d M Y · H:i') }} · {{ $p['label'] }}
                    </div>
                  </div>
                </div>
                <a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this session?') }}')) postDelete('{{ route('teacher.addons.course.session.delete', $s->id) }}')"><i class="bi bi-trash"></i></a>
              </div>
            @endforeach
          @endif
        </div>
      </div>
    </div>
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
