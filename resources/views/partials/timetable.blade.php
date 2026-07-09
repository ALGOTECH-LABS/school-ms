@php
  use App\Models\Subject;
  use App\Models\User;
  use App\Models\ClassRoom;
  use App\Models\Classes;
  use App\Models\Section;

  // $routines (collection), optional $cellShow = 'teacher' | 'class'
  $cellShow  = $cellShow ?? 'teacher';
  $dayOrder  = ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'];
  $activeDays = collect($dayOrder)->filter(fn($d) => $routines->firstWhere('day', $d))->values();

  $slots = $routines->map(fn($r) => [
      'sh' => (int)$r->starting_hour, 'sm' => (int)$r->starting_minute,
      'eh' => (int)$r->ending_hour,   'em' => (int)$r->ending_minute,
  ])->unique(fn($s) => $s['sh'].':'.$s['sm'].'-'.$s['eh'].':'.$s['em'])
    ->sortBy(fn($s) => $s['sh'] * 60 + $s['sm'])->values();

  $fmt   = fn($h, $m) => sprintf('%d:%02d', $h, $m);
  $today = strtolower(date('l'));

  $grid = [];
  foreach ($routines as $r) {
      $key = (int)$r->starting_hour.':'.(int)$r->starting_minute.'-'.(int)$r->ending_hour.':'.(int)$r->ending_minute;
      $grid[$r->day][$key] = $r;
  }
  $palette = ['#e8f5ef','#eaf1fb','#fdeee9','#f3edfb','#fef6e6','#e9f7f6'];
  $admin = $admin ?? false;
@endphp

<style>
  .rt-table-wrap{ background:#fff; border:1px solid #eef0f4; border-radius:12px; overflow:hidden; }
  table.rt{ width:100%; border-collapse:separate; border-spacing:0; }
  table.rt th, table.rt td{ padding:0; border-bottom:1px solid #eef0f4; border-right:1px solid #eef0f4; }
  table.rt th:last-child, table.rt td:last-child{ border-right:none; }
  table.rt tbody tr:last-child td{ border-bottom:none; }
  table.rt thead th{ background:#f7fbf9; color:#181c32; font-weight:600; font-size:13px;
    padding:12px 10px; text-align:center; text-transform:capitalize; }
  table.rt thead th.today{ background:#00955f; color:#fff; }
  .rt-timecol{ width:110px; background:#fbfcfe; font-weight:600; color:#495166; font-size:12.5px;
    text-align:center; padding:14px 8px !important; white-space:nowrap; }
  .rt-cell{ padding:8px !important; vertical-align:top; }
  .rt-cell.today-col{ background:#f4fbf8; }
  .rt-block{ border-radius:9px; padding:9px 11px; border-left:3px solid #00955f; }
  .rt-block .subj{ font-weight:600; color:#181c32; font-size:13.5px; line-height:1.25; }
  .rt-block .meta{ font-size:11.5px; color:#6c7385; margin-top:3px; display:flex; flex-direction:column; gap:1px; }
  .rt-block .meta i{ width:13px; color:#9aa1b0; }
  .rt-empty-cell{ padding:14px !important; text-align:center; color:#cfd4dd; }
  .rt-empty{ text-align:center; padding:50px 20px; color:#9aa1b0; }
  .rt-empty i{ font-size:36px; color:#d3d8e0; }
  .rt-actions{ display:flex; gap:8px; margin-top:6px; padding-top:6px; border-top:1px solid rgba(0,0,0,.06); }
  .rt-actions a{ font-size:13px; color:#8a92a5; text-decoration:none; }
  .rt-actions a:hover{ color:#00955f; }
  .rt-actions a.del:hover{ color:#f04b24; }
</style>

@if($routines->count() === 0)
  <div class="rt-table-wrap"><div class="rt-empty">
    <i class="bi bi-calendar-week"></i>
    <p class="mb-0 mt-2">{{ get_phrase('No routine has been set yet.') }}</p>
  </div></div>
@else
  <div class="rt-table-wrap table-responsive">
    <table class="rt">
      <thead>
        <tr>
          <th class="rt-timecol">{{ get_phrase('Time') }}</th>
          @foreach($activeDays as $day)
            <th class="{{ $day===$today ? 'today' : '' }}">{{ get_phrase(ucfirst($day)) }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($slots as $si => $slot)
          @php $key = $slot['sh'].':'.$slot['sm'].'-'.$slot['eh'].':'.$slot['em']; @endphp
          <tr>
            <td class="rt-timecol">{{ $fmt($slot['sh'],$slot['sm']) }}<br><span style="color:#adb3c0;">{{ $fmt($slot['eh'],$slot['em']) }}</span></td>
            @foreach($activeDays as $day)
              @php $r = $grid[$day][$key] ?? null; @endphp
              @if($r)
                @php
                  $subject = Subject::find($r->subject_id);
                  $room = ClassRoom::find($r->room_id);
                  if ($cellShow === 'class') {
                      $secIcon = 'mortarboard';
                      $secText = optional(Classes::find($r->class_id))->name . ' · ' . optional(Section::find($r->section_id))->name;
                  } else {
                      $secIcon = 'person';
                      $secText = optional(User::find($r->teacher_id))->name;
                  }
                @endphp
                <td class="rt-cell {{ $day===$today ? 'today-col' : '' }}">
                  <div class="rt-block" style="background:{{ $palette[$si % count($palette)] }};">
                    <div class="subj">{{ $subject->name ?? '—' }}</div>
                    <div class="meta">
                      <span><i class="bi bi-{{ $secIcon }}"></i> {{ $secText ?: '—' }}</span>
                      <span><i class="bi bi-geo-alt"></i> {{ $room->name ?? '—' }}</span>
                    </div>
                    @if($admin)
                      <div class="rt-actions">
                        <a href="javascript:;" onclick="rightModal('{{ route('admin.routine_edit_modal', ['id' => $r->id]) }}', '{{ get_phrase('Edit class routine') }}')"><i class="bi bi-pencil-square"></i> {{ get_phrase('Edit') }}</a>
                        <a class="del" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this slot?') }}')) postDelete('{{ route('admin.routine.delete', ['id' => $r->id]) }}')"><i class="bi bi-trash"></i></a>
                      </div>
                    @endif
                  </div>
                </td>
              @else
                <td class="rt-cell {{ $day===$today ? 'today-col' : '' }} rt-empty-cell">·</td>
              @endif
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
