<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $exam->name }} — {{ get_phrase('Exam paper') }}</title>
<style>
  * { box-sizing: border-box; font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; }
  body { margin:0; background:#e9edf0; color:#1c222b; font-size:13.5px; line-height:1.5; }
  .toolbar { position:sticky; top:0; z-index:10; background:#14202b; color:#fff; padding:10px 18px;
    display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
  .toolbar a, .toolbar button { background:#a37b1d; color:#fff; border:0; border-radius:8px;
    padding:8px 16px; font-size:13px; font-weight:600; text-decoration:none; cursor:pointer; }
  .toolbar a.ghost { background:rgba(255,255,255,.15); }
  .sheet { max-width:850px; margin:22px auto; background:#fff; padding:46px 56px;
    box-shadow:0 6px 24px rgba(0,0,0,.12); min-height:1100px; }
  .head { text-align:center; border-bottom:3px solid #a37b1d; padding-bottom:14px; margin-bottom:8px; }
  .head img { width:74px; margin-bottom:6px; }
  .head .org { font-size:17px; font-weight:800; color:#a37b1d; letter-spacing:.4px; }
  .head .addr { font-size:10.5px; color:#777; }
  .head .doc { font-size:14px; font-weight:800; margin-top:10px; text-transform:uppercase; letter-spacing:1px; }
  .meta { display:flex; justify-content:space-between; flex-wrap:wrap; gap:6px 18px; font-size:12px;
    border-bottom:1px solid #dfe5ea; padding:10px 0; margin-bottom:6px; }
  .meta b { color:#a37b1d; }
  .fill { border-bottom:1px dotted #9aa1b0; display:inline-block; min-width:180px; }
  .instr { background:#fdf8ee; border:1px solid #efe2c4; border-radius:8px; padding:10px 14px;
    font-size:12px; margin:12px 0 20px; }
  .q { margin-bottom:20px; page-break-inside:avoid; }
  .q .qt { font-weight:700; display:flex; gap:8px; }
  .q .qt .no { color:#a37b1d; }
  .q .qt .mk { margin-left:auto; font-weight:600; color:#69707d; font-size:12px; white-space:nowrap; }
  .opts { margin:8px 0 0 26px; }
  .opt { display:flex; align-items:baseline; gap:9px; margin-bottom:6px; }
  .opt .bullet { width:15px; height:15px; border:1.6px solid #69707d; border-radius:50%; flex:0 0 auto; position:relative; top:2px; }
  .opt.correct .bullet { background:#a37b1d; border-color:#a37b1d; }
  .opt.correct { color:#7a5c12; font-weight:700; }
  .lines { margin:10px 0 0 26px; }
  .lines .ln { border-bottom:1px solid #cfd6dd; height:26px; }
  .key { margin:8px 0 0 26px; background:#fff8e6; border:1px dashed #d8bd62; border-radius:7px;
    padding:7px 11px; font-size:12px; color:#7a621a; font-weight:700; }
  .foot { text-align:center; font-size:10.5px; color:#9aa1b0; border-top:1px solid #e4e9ee; margin-top:34px; padding-top:10px; }
  @media print {
    body { background:#fff; }
    .toolbar { display:none !important; }
    .sheet { box-shadow:none; margin:0; max-width:none; padding:10mm 14mm; min-height:0; }
  }
</style>
</head>
<body>

<div class="toolbar">
  <div style="font-weight:700;"><span style="opacity:.7;">{{ get_phrase('Sitting exam paper') }} —</span> {{ $exam->name }}</div>
  <div style="display:flex; gap:8px; flex-wrap:wrap;">
    <a class="ghost" href="{{ route('teacher.addons.course.sitting_cat.questions', $exam->id) }}">&larr; {{ get_phrase('Questions') }}</a>
    @if($answers)
      <a class="ghost" href="{{ route('teacher.addons.course.sitting_cat.paper', $exam->id) }}">{{ get_phrase('Hide answer key') }}</a>
    @else
      <a class="ghost" href="{{ route('teacher.addons.course.sitting_cat.paper', ['id' => $exam->id, 'answers' => 1]) }}">{{ get_phrase('Show answer key') }}</a>
    @endif
    <button onclick="window.print()">&#128424; {{ get_phrase('Print') }}</button>
  </div>
</div>

<div class="sheet">
  <div class="head">
    @if($logoUrl)<img src="{{ $logoUrl }}" alt="logo">@endif
    <div class="org">{{ $school->title ?? '' }}</div>
    <div class="addr">{{ $school->address ?? '' }}{{ ($school->phone ?? '') ? ' · '.$school->phone : '' }}</div>
    <div class="doc">{{ $exam->name }} @if($answers)<span style="color:#c0392b;">— {{ get_phrase('ANSWER KEY') }}</span>@endif</div>
  </div>

  <div class="meta">
    <span><b>{{ get_phrase('Class') }}:</b> {{ $className }}</span>
    <span><b>{{ get_phrase('Subject') }}:</b> {{ $subjectName }}</span>
    <span><b>{{ get_phrase('Total marks') }}:</b> {{ (int) $exam->total_marks }}</span>
    @if($exam->starting_time)<span><b>{{ get_phrase('Date') }}:</b> {{ date('d M Y, H:i', (int) $exam->starting_time) }}</span>@endif
    @if($exam->room_number)<span><b>{{ get_phrase('Venue') }}:</b> {{ $exam->room_number }}</span>@endif
  </div>

  @if(!$answers)
  <div class="meta" style="border-bottom:1px solid #dfe5ea;">
    <span>{{ get_phrase('Name') }}: <span class="fill"></span></span>
    <span>{{ get_phrase('Adm No') }}: <span class="fill" style="min-width:110px;"></span></span>
    <span>{{ get_phrase('Signature') }}: <span class="fill" style="min-width:110px;"></span></span>
  </div>
  @endif

  <div class="instr"><b>{{ get_phrase('Instructions:') }}</b> {{ get_phrase('Answer ALL questions. Write clearly. For multiple-choice, shade one option only.') }}</div>

  @foreach($attached as $i => $eq)
    @php $q = $eq->q; @endphp
    <div class="q">
      <div class="qt">
        <span class="no">{{ $i + 1 }}.</span>
        <span style="flex:1;">{{ $q->question }}</span>
        <span class="mk">[{{ $eq->marks }} {{ get_phrase($eq->marks == 1 ? 'mark' : 'marks') }}]</span>
      </div>
      @if($q->type === 'mcq' && is_array($q->options))
        <div class="opts">
          @foreach($q->options as $oi => $opt)
            <div class="opt {{ $answers && (string)$q->correct_answer === (string)$oi ? 'correct' : '' }}">
              <span class="bullet"></span><span><b>{{ chr(65 + $oi) }}.</b> {{ $opt }}</span>
            </div>
          @endforeach
        </div>
      @elseif($q->type === 'truefalse')
        <div class="opts">
          <div class="opt {{ $answers && $q->correct_answer === 'true' ? 'correct' : '' }}"><span class="bullet"></span><span>{{ get_phrase('True') }}</span></div>
          <div class="opt {{ $answers && $q->correct_answer === 'false' ? 'correct' : '' }}"><span class="bullet"></span><span>{{ get_phrase('False') }}</span></div>
        </div>
      @elseif($q->type === 'short')
        @if($answers)<div class="key">{{ get_phrase('Expected answer') }}: {{ $q->correct_answer ?: get_phrase('(marked manually)') }}</div>
        @else<div class="lines"><div class="ln"></div><div class="ln"></div></div>@endif
      @else
        @if($answers)<div class="key">{{ get_phrase('Essay — marked manually out of') }} {{ $eq->marks }}.</div>
        @else<div class="lines">@for($l=0;$l<6;$l++)<div class="ln"></div>@endfor</div>@endif
      @endif
    </div>
  @endforeach

  @if($attached->count() === 0)
    <p style="text-align:center;color:#9aa1b0;padding:40px 0;">{{ get_phrase('This paper has no questions yet.') }}</p>
  @endif

  <div class="foot">{{ $school->title ?? '' }} — {{ $exam->name }} · {{ $className }} · {{ get_phrase('END OF PAPER') }}</div>
</div>
</body>
</html>
