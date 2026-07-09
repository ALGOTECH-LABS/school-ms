@extends('student.navigation')

@section('content')
@php
  $lessonCount = $course ? $course->topics->sum(fn($t) => $t->lessons->count()) : 0;
@endphp
<style>
  .sy-hero{ background:linear-gradient(135deg,#00955f,#007a4d); color:#fff; border-radius:14px;
    padding:22px 26px; margin-bottom:20px; box-shadow:0 10px 30px rgba(0,149,95,.16); }
  .sy-hero h4{ color:#fff; font-weight:700; margin:0; }
  .sy-hero p{ color:rgba(255,255,255,.9); margin:4px 0 0; font-size:14px; }
  .sy-hero .dl{ background:rgba(255,255,255,.16); color:#fff; border:1px solid rgba(255,255,255,.4); }
  .sy-hero .dl:hover{ background:rgba(255,255,255,.28); }
  .sy-topic{ background:#fff; border:1px solid #eef0f4; border-radius:12px; overflow:hidden; margin-bottom:16px; }
  .sy-topic .head{ display:flex; align-items:center; gap:9px; padding:14px 18px; background:#f7fbf9; border-bottom:1px solid #eef0f4; font-weight:600; color:#181c32; }
  .sy-topic .head .n{ width:26px;height:26px;border-radius:7px;background:#00955f;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:700; }
  .sy-topic .head .c{ margin-left:auto; font-size:12px; color:#6c757d; font-weight:500; }
  details.sy-les{ border-bottom:1px solid #f1f3f6; }
  details.sy-les:last-child{ border-bottom:none; }
  details.sy-les > summary{ list-style:none; cursor:pointer; padding:13px 18px; display:flex; align-items:center; gap:10px; }
  details.sy-les > summary::-webkit-details-marker{ display:none; }
  details.sy-les > summary:hover{ background:#f8faf9; }
  details.sy-les .chev{ margin-left:auto; color:#b7bdc8; transition:transform .15s; }
  details.sy-les[open] .chev{ transform:rotate(90deg); }
  .sy-note{ padding:2px 18px 18px 46px; color:#495166; line-height:1.65; }
  .sy-empty{ text-align:center; padding:44px 20px; color:#9aa1b0; }
  .sy-empty i{ font-size:34px; color:#d3d8e0; }
</style>

<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ get_phrase('Syllabus details') }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2">
          <li><a href="#">{{ get_phrase('Home') }}</a></li>
          <li><a href="{{ route('student.syllabus') }}">{{ get_phrase('Syllabus') }}</a></li>
          <li><a href="#">{{ get_phrase('Details') }}</a></li>
        </ul>
      </div>
      <a class="eBtn btn-secondary" href="{{ route('student.syllabus') }}">{{ get_phrase('Back') }}</a>
    </div>
  </div></div>
</div>

<div class="sy-hero d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
  <div>
    <h4>{{ $syllabus->title }}</h4>
    <p>{{ $subject->name ?? '' }} · {{ $course ? $course->topics->count().' '.get_phrase('topics').' · '.$lessonCount.' '.get_phrase('lessons') : get_phrase('Outline') }}</p>
  </div>
  <div class="d-flex" style="gap:8px;">
    @if($syllabus->file)
      <a class="eBtn dl" target="_blank" href="{{ asset('assets/uploads/syllabus/'.$syllabus->file) }}"><i class="bi bi-download"></i> {{ get_phrase('File') }}</a>
    @endif
    <a class="eBtn dl" href="{{ route('student.syllabus.pdf', $syllabus->id) }}"><i class="bi bi-file-earmark-pdf"></i> {{ get_phrase('Download PDF') }}</a>
  </div>
</div>

@if(!empty($syllabus->content))
  <div class="sy-topic">
    <div class="head"><i class="bi bi-file-text" style="color:#00955f;"></i> {{ get_phrase('Syllabus') }}</div>
    <div class="sy-note" style="padding:18px 22px;">{!! $syllabus->content !!}</div>
  </div>
@endif

@if($course && $course->topics->count())
  @foreach($course->topics as $ti => $topic)
    <div class="sy-topic">
      <div class="head"><span class="n">{{ $ti+1 }}</span> {{ $topic->title }} <span class="c">{{ $topic->lessons->count() }} {{ get_phrase('lessons') }}</span></div>
      @forelse($topic->lessons as $li => $lesson)
        <details class="sy-les" {{ $ti===0 && $li===0 ? 'open' : '' }}>
          <summary>
            <i class="bi bi-journal-text" style="color:#00955f;"></i>
            <span style="font-weight:500;color:#2b2f3a;">{{ $lesson->title }}</span>
            <i class="bi bi-chevron-right chev"></i>
          </summary>
          <div class="sy-note">
            @if($lesson->content){!! $lesson->content !!}@else<span class="text-muted">{{ get_phrase('No notes for this topic.') }}</span>@endif
          </div>
        </details>
      @empty
        <div class="sy-note text-muted">{{ get_phrase('No lessons under this topic yet.') }}</div>
      @endforelse
    </div>
  @endforeach
@else
  <div class="sy-topic"><div class="sy-empty">
    <i class="bi bi-list-columns"></i>
    <p class="mb-1 mt-2">{{ get_phrase('No detailed outline has been published for this subject yet.') }}</p>
    @if($syllabus->file)<small>{{ get_phrase('You can download the syllabus document above.') }}</small>@endif
  </div></div>
@endif
@endsection
