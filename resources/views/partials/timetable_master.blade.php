@php
  use App\Models\Subject;
  use App\Models\User;
  use App\Models\ClassRoom;
  use App\Models\Classes;
  use App\Models\Section;

  // $routines (collection) — whole-school. Renders one cell per day+slot, each
  // listing every class scheduled then, and flags teacher/room double-bookings.
  $dayOrder   = ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'];
  $activeDays = collect($dayOrder)->filter(fn($d) => $routines->firstWhere('day', $d))->values();

  $slots = $routines->map(fn($r) => [
      'sh' => (int)$r->starting_hour, 'sm' => (int)$r->starting_minute,
      'eh' => (int)$r->ending_hour,   'em' => (int)$r->ending_minute,
  ])->unique(fn($s) => $s['sh'].':'.$s['sm'].'-'.$s['eh'].':'.$s['em'])
    ->sortBy(fn($s) => $s['sh'] * 60 + $s['sm'])->values();

  $fmt   = fn($h, $m) => sprintf('%d:%02d', $h, $m);
  $today = strtolower(date('l'));

  // multi-entry grid: $grid[day][slotKey] = [ $r, $r, ... ]
  $grid = [];
  foreach ($routines as $r) {
      $key = (int)$r->starting_hour.':'.(int)$r->starting_minute.'-'.(int)$r->ending_hour.':'.(int)$r->ending_minute;
      $grid[$r->day][$key][] = $r;
  }

  // resolve display names once (avoid N+1 Model::find inside the nested loops)
  $subjects = Subject::whereIn('id', $routines->pluck('subject_id')->unique())->pluck('name', 'id');
  $rooms    = ClassRoom::whereIn('id', $routines->pluck('room_id')->unique())->pluck('name', 'id');
  $teachers = User::whereIn('id', $routines->pluck('teacher_id')->unique())->pluck('name', 'id');
  $classNm  = Classes::whereIn('id', $routines->pluck('class_id')->unique())->pluck('name', 'id');
  $sectionNm= Section::whereIn('id', $routines->pluck('section_id')->unique())->pluck('name', 'id');

  $palette = ['#e8f5ef','#eaf1fb','#fdeee9','#f3edfb','#fef6e6','#e9f7f6'];
  $classColor = fn($cid) => $palette[((int)$cid) % count($palette)];

  // tally school-wide clashes (distinct slots that have a teacher or room collision)
  $clashCount = 0;
@endphp

<style>
  .rtm-wrap{ background:#fff; border:1px solid #eef0f4; border-radius:12px; overflow:hidden; }
  table.rtm{ width:100%; border-collapse:separate; border-spacing:0; }
  table.rtm th, table.rtm td{ padding:0; border-bottom:1px solid #eef0f4; border-right:1px solid #eef0f4; vertical-align:top; }
  table.rtm th:last-child, table.rtm td:last-child{ border-right:none; }
  table.rtm tbody tr:last-child td{ border-bottom:none; }
  table.rtm thead th{ background:#f7fbf9; color:#181c32; font-weight:600; font-size:13px;
    padding:12px 10px; text-align:center; text-transform:capitalize; }
  table.rtm thead th.today{ background:#00955f; color:#fff; }
  .rtm-timecol{ width:96px; background:#fbfcfe; font-weight:600; color:#495166; font-size:12px;
    text-align:center; padding:14px 8px !important; white-space:nowrap; }
  .rtm-cell{ padding:6px !important; min-width:150px; }
  .rtm-cell.today-col{ background:#f4fbf8; }
  .rtm-block{ border-radius:8px; padding:7px 9px; border-left:3px solid #00955f; margin-bottom:6px; }
  .rtm-block:last-child{ margin-bottom:0; }
  .rtm-block.clash{ border:1.5px solid #f04b24; border-left-width:3px; }
  .rtm-block .subj{ font-weight:700; color:#181c32; font-size:12.5px; line-height:1.2; }
  .rtm-block .meta{ font-size:11px; color:#6c7385; margin-top:2px; display:flex; flex-direction:column; gap:1px; }
  .rtm-block .meta i{ width:12px; color:#9aa1b0; }
  .rtm-clash-badge{ display:inline-block; font-size:9.5px; font-weight:800; color:#fff;
    background:#f04b24; border-radius:10px; padding:1px 7px; margin-top:4px; letter-spacing:.2px; }
  .rtm-empty-cell{ padding:14px !important; text-align:center; color:#cfd4dd; }
  .rtm-empty{ text-align:center; padding:50px 20px; color:#9aa1b0; }
  .rtm-empty i{ font-size:36px; color:#d3d8e0; }
</style>

@if($routines->count() === 0)
  <div class="rtm-wrap"><div class="rtm-empty">
    <i class="bi bi-calendar-week"></i>
    <p class="mb-0 mt-2">{{ get_phrase('No routine has been set yet.') }}</p>
  </div></div>
@else
  <div class="rtm-wrap table-responsive">
    <table class="rtm">
      <thead>
        <tr>
          <th class="rtm-timecol">{{ get_phrase('Time') }}</th>
          @foreach($activeDays as $day)
            <th class="{{ $day===$today ? 'today' : '' }}">{{ get_phrase(ucfirst($day)) }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($slots as $slot)
          @php $key = $slot['sh'].':'.$slot['sm'].'-'.$slot['eh'].':'.$slot['em']; @endphp
          <tr>
            <td class="rtm-timecol">{{ $fmt($slot['sh'],$slot['sm']) }}<br><span style="color:#adb3c0;">{{ $fmt($slot['eh'],$slot['em']) }}</span></td>
            @foreach($activeDays as $day)
              @php $cell = $grid[$day][$key] ?? []; @endphp
              @if(count($cell))
                @php
                  // which teacher / room ids collide within this cell
                  $tCounts = collect($cell)->groupBy('teacher_id')->map->count();
                  $rCounts = collect($cell)->groupBy('room_id')->map->count();
                  $cellHasClash = $tCounts->contains(fn($c) => $c > 1) || $rCounts->contains(fn($c) => $c > 1);
                  if ($cellHasClash) $clashCount++;
                @endphp
                <td class="rtm-cell {{ $day===$today ? 'today-col' : '' }}">
                  @foreach($cell as $r)
                    @php
                      $teacherClash = ($tCounts[$r->teacher_id] ?? 0) > 1;
                      $roomClash    = $r->room_id && ($rCounts[$r->room_id] ?? 0) > 1;
                      $isClash      = $teacherClash || $roomClash;
                    @endphp
                    <div class="rtm-block {{ $isClash ? 'clash' : '' }}" style="background:{{ $classColor($r->class_id) }};">
                      <div class="subj">{{ $subjects[$r->subject_id] ?? '—' }}</div>
                      <div class="meta">
                        <span><i class="bi bi-mortarboard"></i> {{ ($classNm[$r->class_id] ?? '—') }} · {{ ($sectionNm[$r->section_id] ?? '—') }}</span>
                        <span><i class="bi bi-person"></i> {{ $teachers[$r->teacher_id] ?? '—' }}</span>
                        <span><i class="bi bi-geo-alt"></i> {{ $rooms[$r->room_id] ?? '—' }}</span>
                      </div>
                      @if($isClash)
                        <span class="rtm-clash-badge">
                          <i class="bi bi-exclamation-triangle-fill"></i>
                          @if($teacherClash && $roomClash) {{ get_phrase('Teacher & room clash') }}
                          @elseif($teacherClash) {{ get_phrase('Teacher clash') }}
                          @else {{ get_phrase('Room clash') }} @endif
                        </span>
                      @endif
                    </div>
                  @endforeach
                </td>
              @else
                <td class="rtm-cell {{ $day===$today ? 'today-col' : '' }} rtm-empty-cell">·</td>
              @endif
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
