@extends(($preview ?? false) ? 'teacher.navigation' : 'student.navigation')

@section('content')
@if($preview ?? false)
  <div class="d-flex justify-content-between align-items-center flex-wrap mb-3"
       style="gap:10px;background:#fff8e6;border:1px solid #f2e2b3;border-radius:12px;padding:12px 18px;">
    <div style="font-size:13.5px;color:#8a6d1a;font-weight:600;">
      <i class="bi bi-eye"></i> {{ get_phrase('Student preview — this is exactly what your class sees.') }}
    </div>
    <a class="eBtn btn-secondary" href="{{ route('teacher.addons.course.manage', $course->id) }}">
      <i class="bi bi-arrow-left"></i> {{ get_phrase('Back to manage course') }}
    </a>
  </div>
@endif
@php
  use Illuminate\Support\Str;
  $sub = \App\Models\Subject::find($course->subject_id);
  $cls = \App\Models\Classes::find($course->class_id);
  $teacher = \App\Models\User::find($course->teacher_id);

  $topicCount   = $course->topics->count();
  $lessonCount  = $course->topics->sum(fn($t) => $t->lessons->count());
  $materialCount = $course->topics->sum(fn($t) => $t->lessons->sum(fn($l) => $l->materials->count()));
  $syllabus = $syllabus->unique('title')->values();

  if (!function_exists('_ce_embed')) {
    function _ce_embed($url){
      if (preg_match('~youtu\.be/([\w-]+)~i',$url,$m)) return 'https://www.youtube.com/embed/'.$m[1];
      if (preg_match('~youtube\.com/watch\?v=([\w-]+)~i',$url,$m)) return 'https://www.youtube.com/embed/'.$m[1];
      if (preg_match('~vimeo\.com/(\d+)~i',$url,$m)) return 'https://player.vimeo.com/video/'.$m[1];
      return $url;
    }
    function _ce_isvid($url){ return preg_match('~youtube\.com|youtu\.be|vimeo\.com~i',$url); }
  }
@endphp

<style>
  .course-hero{
    background:linear-gradient(135deg,#00955f 0%,#007a4d 55%,#00693f 100%);
    color:#fff; border-radius:14px; padding:28px 30px; margin-bottom:22px;
    box-shadow:0 10px 30px rgba(0,149,95,.18);
  }
  .course-hero .eyebrow{ text-transform:uppercase; letter-spacing:.08em; font-size:12px; font-weight:600; opacity:.85; }
  .course-hero h3{ color:#fff; font-weight:700; margin:6px 0 8px; }
  .course-hero p{ color:rgba(255,255,255,.9); margin-bottom:16px; max-width:640px; }
  .course-hero .hero-meta{ display:flex; flex-wrap:wrap; gap:18px; font-size:13px; }
  .course-hero .hero-meta span{ display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,.14); padding:6px 12px; border-radius:20px; }
  .course-hero .hero-back{ background:rgba(255,255,255,.18); color:#fff; border:1px solid rgba(255,255,255,.35); }
  .course-hero .hero-back:hover{ background:rgba(255,255,255,.28); }

  .curr-title{ font-weight:700; color:#181c32; margin-bottom:14px; }
  .topic-card{ background:#fff; border:1px solid #eef0f4; border-radius:12px; overflow:hidden; margin-bottom:16px; }
  .topic-head{ display:flex; align-items:center; justify-content:space-between; gap:10px;
    padding:14px 18px; background:#f7fbf9; border-bottom:1px solid #eef0f4; }
  .topic-head .t-name{ font-weight:600; color:#181c32; display:flex; align-items:center; gap:9px; }
  .topic-head .t-name .n{ width:26px;height:26px;border-radius:7px;background:#00955f;color:#fff;
    display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:700; }
  .topic-head .t-count{ font-size:12px; color:#6c757d; }

  details.lesson{ border-bottom:1px solid #f1f3f6; }
  details.lesson:last-child{ border-bottom:none; }
  details.lesson > summary{ list-style:none; cursor:pointer; padding:13px 18px;
    display:flex; align-items:center; gap:10px; transition:background .12s; }
  details.lesson > summary::-webkit-details-marker{ display:none; }
  details.lesson > summary:hover{ background:#f8faf9; }
  details.lesson > summary .l-ico{ color:#00955f; font-size:18px; flex:none; }
  details.lesson > summary .l-title{ font-weight:500; color:#2b2f3a; flex:1; }
  details.lesson > summary .l-meta{ font-size:12px; color:#8a92a5; }
  details.lesson > summary .chev{ transition:transform .15s; color:#b7bdc8; }
  details.lesson[open] > summary .chev{ transform:rotate(90deg); }
  details.lesson[open] > summary{ background:#f8faf9; }
  .lesson-body{ padding:4px 18px 20px 46px; }
  .lesson-body .rich{ color:#495166; line-height:1.65; }
  .lesson-body .rich ul{ padding-left:18px; }
  .mat-row{ display:flex; align-items:center; gap:8px; padding:8px 0; border-top:1px dashed #eef0f4; }
  .mat-row .badge-type{ background:#eef6f2; color:#00794c; font-weight:600; font-size:11px; padding:3px 8px; border-radius:6px; }
  .mat-row a{ color:#2b2f3a; text-decoration:none; }
  .mat-row a:hover{ color:#00955f; }
  .mats-label{ font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#8a92a5; margin:14px 0 4px; }
  .vid-wrap{ max-width:560px; border-radius:10px; overflow:hidden; margin:8px 0; box-shadow:0 4px 14px rgba(0,0,0,.08); }

  .side-card{ background:#fff; border:1px solid #eef0f4; border-radius:12px; padding:18px; margin-bottom:16px; }
  .side-card h6{ font-weight:700; color:#181c32; display:flex; align-items:center; gap:8px; margin-bottom:14px; }
  .side-card .kv{ display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px dashed #f1f3f6; font-size:13px; }
  .side-card .kv:last-child{ border-bottom:none; }
  .side-card .kv .k{ color:#8a92a5; }
  .side-card .kv .v{ color:#2b2f3a; font-weight:600; }
  .side-link{ display:flex; justify-content:space-between; align-items:center; gap:8px; padding:9px 0; border-bottom:1px dashed #f1f3f6; text-decoration:none; }
  .side-link:last-child{ border-bottom:none; }
  .side-link .t{ color:#2b2f3a; font-size:13.5px; }
  .side-link:hover .t{ color:#00955f; }
  .side-link .d{ color:#adb3c0; font-size:12px; white-space:nowrap; }
  .side-empty{ color:#adb3c0; font-size:13px; }
</style>

<!-- Hero -->
<div class="course-hero">
  <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:14px;">
    <div>
      <div class="eyebrow">{{ $sub->name ?? get_phrase('Course') }} · {{ $cls->name ?? '' }}</div>
      <h3>{{ $course->title }}</h3>
      @if($course->description)<p>{{ $course->description }}</p>@endif
      <div class="hero-meta">
        <span><i class="bi bi-person-workspace"></i> {{ $teacher->name ?? get_phrase('Instructor') }}</span>
        <span><i class="bi bi-collection"></i> {{ $topicCount }} {{ get_phrase('topics') }}</span>
        <span><i class="bi bi-play-circle"></i> {{ $lessonCount }} {{ get_phrase('lessons') }}</span>
        <span><i class="bi bi-paperclip"></i> {{ $materialCount }} {{ get_phrase('materials') }}</span>
      </div>
    </div>
    <a class="eBtn hero-back" href="{{ ($preview ?? false) ? route('teacher.addons.course.manage', $course->id) : route('student.addons.courses') }}"><i class="bi bi-arrow-left"></i> {{ get_phrase('Back') }}</a>
  </div>
</div>

<div class="row">
  {{-- ================= curriculum ================= --}}
  <div class="col-lg-8">
    <h5 class="curr-title">{{ get_phrase('Course content') }}</h5>

    @forelse($course->topics as $ti => $topic)
      <div class="topic-card">
        <div class="topic-head">
          <span class="t-name"><span class="n">{{ $ti+1 }}</span> {{ $topic->title }}</span>
          <span class="t-count">{{ $topic->lessons->count() }} {{ get_phrase('lessons') }}</span>
        </div>
        <div class="topic-body">
          @forelse($topic->lessons as $li => $lesson)
            <details class="lesson" {{ $ti===0 && $li===0 ? 'open' : '' }}>
              <summary>
                <i class="bi bi-play-circle-fill l-ico"></i>
                <span class="l-title">{{ $lesson->title }}</span>
                @if($lesson->materials->count())<span class="l-meta">{{ $lesson->materials->count() }} {{ get_phrase('materials') }}</span>@endif
                <i class="bi bi-chevron-right chev"></i>
              </summary>
              <div class="lesson-body">
                @if($lesson->content)<div class="rich">{!! $lesson->content !!}</div>@endif

                @if($lesson->materials->count())
                  <div class="mats-label">{{ get_phrase('Materials') }}</div>
                  @foreach($lesson->materials as $m)
                    @if($m->type=='video' && _ce_isvid($m->url))
                      <div class="vid-wrap">
                        <div class="ratio ratio-16x9"><iframe src="{{ _ce_embed($m->url) }}" frameborder="0" allowfullscreen title="{{ $m->title }}"></iframe></div>
                      </div>
                      <div class="mat-row"><span class="badge-type">{{ get_phrase('Video') }}</span> <span>{{ $m->title }}</span></div>
                    @else
                      <div class="mat-row">
                        <span class="badge-type">{{ $m->type=='file' ? get_phrase('File') : get_phrase('Link') }}</span>
                        @if($m->type=='file')
                          <a target="_blank" href="{{ asset('assets/uploads/course_materials/'.$m->file) }}"><i class="bi bi-download"></i> {{ $m->title }}</a>
                        @else
                          <a target="_blank" href="{{ $m->url }}"><i class="bi bi-box-arrow-up-right"></i> {{ $m->title }}</a>
                        @endif
                      </div>
                    @endif
                  @endforeach
                @endif
              </div>
            </details>
          @empty
            <div class="p-3 side-empty">{{ get_phrase('No lessons in this topic yet.') }}</div>
          @endforelse
        </div>
      </div>
    @empty
      <div class="topic-card"><div class="p-4 text-center side-empty">{{ get_phrase('This course has no content yet.') }}</div></div>
    @endforelse
  </div>

  {{-- ================= sidebar ================= --}}
  <div class="col-lg-4">
    <div class="side-card">
      <h6><i class="bi bi-info-circle" style="color:#00955f;"></i> {{ get_phrase('Course info') }}</h6>
      <div class="kv"><span class="k">{{ get_phrase('Instructor') }}</span><span class="v">{{ $teacher->name ?? '—' }}</span></div>
      <div class="kv"><span class="k">{{ get_phrase('Subject') }}</span><span class="v">{{ $sub->name ?? '—' }}</span></div>
      <div class="kv"><span class="k">{{ get_phrase('Class') }}</span><span class="v">{{ $cls->name ?? '—' }}</span></div>
      <div class="kv"><span class="k">{{ get_phrase('Lessons') }}</span><span class="v">{{ $lessonCount }}</span></div>
    </div>

    <div class="side-card">
      <h6><i class="bi bi-camera-video" style="color:#00955f;"></i> {{ get_phrase('Live sessions') }}
        @if($upcoming->count())<span class="badge-type">{{ $upcoming->count() }}</span>@endif</h6>
      @forelse($upcoming as $s)
        @php
          $plat = ['zoom'=>'Zoom','meet'=>'Google Meet','teams'=>'Microsoft Teams','other'=>'Online'][$s->platform] ?? 'Online';
        @endphp
        <div class="side-link" style="flex-direction:column;align-items:stretch;gap:6px;">
          <div class="d-flex justify-content-between align-items-center" style="gap:8px;">
            <span class="t" style="font-weight:600;">{{ $s->title }}
              @if($s->is_live)<span class="badge-type" style="background:#fdECEC;color:#c0392b;">● {{ get_phrase('LIVE') }}</span>@endif
            </span>
          </div>
          <span class="d" style="white-space:normal;">
            <i class="bi bi-calendar-event"></i> {{ $s->session_date->format('D, d M · H:i') }} · {{ $plat }}
          </span>
          @if($s->description)<span class="d" style="white-space:normal;color:#8a92a5;">{{ $s->description }}</span>@endif
          @if($s->meeting_url)
            <a class="eBtn btn-primary" target="_blank" href="{{ $s->meeting_url }}" style="padding:6px 12px;font-size:12.5px;">
              <i class="bi bi-box-arrow-up-right"></i> {{ get_phrase('Join session') }}</a>
          @endif
        </div>
      @empty
        <span class="side-empty">{{ get_phrase('No upcoming live sessions.') }}</span>
      @endforelse
    </div>

    <div class="side-card">
      <h6><i class="bi bi-file-earmark-text" style="color:#00955f;"></i> {{ get_phrase('Syllabus') }}</h6>
      @forelse($syllabus as $s)
        <a class="side-link" target="_blank" href="{{ asset('assets/uploads/syllabus/'.$s->file) }}">
          <span class="t"><i class="bi bi-download"></i> {{ $s->title }}</span>
        </a>
      @empty
        <span class="side-empty">{{ get_phrase('No syllabus uploaded.') }}</span>
      @endforelse
    </div>

    <div class="side-card">
      <h6><i class="bi bi-award" style="color:#00955f;"></i> {{ get_phrase('My results') }}</h6>
      @forelse(($results ?? collect()) as $r)
        @php $pct = ($r['total'] && $r['score'] !== null) ? round($r['score'] / $r['total'] * 100) : null; @endphp
        <div class="side-link" style="cursor:default;">
          <span class="t">{{ $r['title'] }} <span class="badge-type" style="background:{{ $r['type']==='Online CAT' ? '#e8f5ef' : '#fdf6e6' }};color:{{ $r['type']==='Online CAT' ? '#00794c' : '#a37b1d' }};">{{ $r['type'] }}</span></span>
          <span class="d">
            @if($r['status']==='graded' && $r['score'] !== null)
              <b style="color:{{ $pct !== null && $pct>=50 ? '#00955f' : '#c0392b' }};">{{ rtrim(rtrim(number_format((float)$r['score'],2),'0'),'.') }}{{ $r['total'] ? '/'.$r['total'] : '' }}</b>@if($pct !== null) · {{ $pct }}%@endif
            @else
              <span style="color:#a37b1d;">{{ get_phrase('Awaiting result') }}</span>
            @endif
          </span>
        </div>
      @empty
        <span class="side-empty">{{ get_phrase('No results yet — they appear here after each CAT or exam is marked.') }}</span>
      @endforelse
    </div>

    <div class="side-card">
      <h6><i class="bi bi-journal-check" style="color:#00955f;"></i> {{ get_phrase('Assignments') }}</h6>
      @forelse($assignments as $a)
        @php
          $aHref = ($preview ?? false)
              ? ($a->is_quiz ? route('teacher.quiz.review',$a->id) : route('teacher.assignment.show',$a->id))
              : ($a->is_quiz ? route('student.quiz.take',$a->id) : route('student.assignment.show',$a->id));
        @endphp
        <a class="side-link" href="{{ $aHref }}">
          <span class="t">{{ $a->title }} @if($a->is_quiz)<span class="badge-type">{{ get_phrase('Quiz') }}</span>@endif</span>
          <span class="d">{{ $a->deadline ? date('d M', $a->deadline) : '' }}</span>
        </a>
      @empty
        <span class="side-empty">{{ get_phrase('No assignments for this subject.') }}</span>
      @endforelse
    </div>

    <div class="side-card">
      <h6><i class="bi bi-building" style="color:#a37b1d;"></i> {{ get_phrase('Sitting CATs & exams') }}</h6>
      @forelse(($sittingExams ?? collect()) as $ex)
        <div class="side-link" style="cursor:default;">
          <span class="t">{{ $ex->name }}</span>
          <span class="d">{{ date('d M, H:i', (int) $ex->starting_time) }}{{ $ex->room_number ? ' · '.$ex->room_number : '' }}</span>
        </div>
      @empty
        <span class="side-empty">{{ get_phrase('No upcoming sitting exams.') }}</span>
      @endforelse
    </div>
  </div>
</div>
@endsection
