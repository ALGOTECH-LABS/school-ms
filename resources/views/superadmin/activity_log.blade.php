@extends(($isSuper ?? auth()->user()->role_id == 1) ? 'superadmin.navigation' : 'admin.navigation')
@section('content')

@php $homeRoute = ($isSuper ?? false) ? 'superadmin.dashboard' : 'admin.dashboard'; @endphp
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ get_phrase('Activity Log') }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2">
          <li><a href="{{ route($homeRoute) }}">{{ get_phrase('Home') }}</a></li>
          <li><a href="#">{{ get_phrase('Activity Log') }}</a></li>
        </ul>
      </div>
    </div>
  </div></div>
</div>

@if(session('message'))
  <div class="alert" style="background:#e9f7f1;color:#0a7a52;border:1px solid #c4e8da;border-radius:10px;padding:10px 14px;font-size:14px;">{{ session('message') }}</div>
@endif

@php
  $cards = [
    ['label'=>'Total Activities','value'=>number_format($stats['total']),'icon'=>'bi-activity','c'=>'#00955f'],
    ['label'=>'Logins Today','value'=>number_format($stats['logins_today']),'icon'=>'bi-box-arrow-in-right','c'=>'#2f6fb0'],
    ['label'=>'Actions Today','value'=>number_format($stats['actions_today']),'icon'=>'bi-lightning-charge-fill','c'=>'#f04b24'],
    ['label'=>'Active Users Today','value'=>number_format($stats['active_users']),'icon'=>'bi-people-fill','c'=>'#7952b3'],
  ];
@endphp
<div class="row">
  @foreach($cards as $card)
  <div class="col-lg-3 col-md-6 mb-3">
    <div style="background:#fff;border:1px solid #eef1f0;border-radius:14px;padding:18px 20px;display:flex;align-items:center;gap:16px;">
      <span style="flex:none;width:50px;height:50px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:21px;color:{{ $card['c'] }};background:{{ $card['c'] }}1a;">
        <i class="bi {{ $card['icon'] }}"></i>
      </span>
      <div>
        <div style="font-size:23px;font-weight:800;color:#2b2f3a;line-height:1;">{{ $card['value'] }}</div>
        <div style="font-size:12.5px;color:#8b93a0;margin-top:4px;">{{ get_phrase($card['label']) }}</div>
      </div>
    </div>
  </div>
  @endforeach
</div>

@if($isSuper ?? false)
{{-- Superadmin settings: page-view logging toggle + retention + purge --}}
<div class="eSection-wrap mb-3" style="background:#fff;border:1px solid #eef1f0;border-radius:14px;padding:14px 18px;">
  <form method="POST" action="{{ route('superadmin.activity_log.settings') }}" class="row g-3 align-items-end">
    @csrf
    <div class="col-lg-4 col-md-6">
      <label class="d-flex align-items-center" style="gap:9px;cursor:pointer;font-size:13.5px;color:#4a5260;margin:0;">
        <input type="checkbox" name="log_page_views" value="1" {{ ($pageViews ?? false) ? 'checked' : '' }} style="width:17px;height:17px;accent-color:#00955f;">
        <span><b>{{ get_phrase('Log page views') }}</b> — {{ get_phrase('record every page a user opens (higher volume)') }}</span>
      </label>
    </div>
    <div class="col-lg-3 col-md-6">
      <label class="form-label" style="font-size:12.5px;font-weight:600;">{{ get_phrase('Keep logs for (days)') }}</label>
      <input type="number" min="1" name="log_retention_days" value="{{ $retention ?? 30 }}" class="form-control">
    </div>
    <div class="col-lg-2 col-md-6">
      <button class="btn btn-primary w-100" type="submit"><i class="bi bi-save"></i> {{ get_phrase('Save') }}</button>
    </div>
    <div class="col-lg-3 col-md-6">
      <button class="btn btn-outline-danger w-100" type="submit" name="purge" value="1"
        onclick="return confirm('{{ get_phrase('Delete all logs older than the retention period now?') }}')">
        <i class="bi bi-trash"></i> {{ get_phrase('Purge old logs now') }}</button>
    </div>
  </form>
</div>
@endif

{{-- Filters --}}
<div class="eSection-wrap mb-3" style="background:#fff;border:1px solid #eef1f0;border-radius:14px;padding:16px 18px;">
  <form method="GET" action="{{ route($logRoute) }}" class="row g-2 align-items-end">
    <div class="col-lg-3 col-md-6">
      <label class="form-label" style="font-size:12.5px;font-weight:600;">{{ get_phrase('Search') }}</label>
      <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ get_phrase('User, action or IP') }}">
    </div>
    <div class="col-lg-2 col-md-6">
      <label class="form-label" style="font-size:12.5px;font-weight:600;">{{ get_phrase('Role') }}</label>
      <select name="role" class="form-control">
        <option value="">{{ get_phrase('All roles') }}</option>
        @foreach($roles as $rname)
          <option value="{{ $rname }}" {{ $role==$rname?'selected':'' }}>{{ $rname }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-lg-2 col-md-6">
      <label class="form-label" style="font-size:12.5px;font-weight:600;">{{ get_phrase('Type') }}</label>
      <select name="type" class="form-control">
        <option value="">{{ get_phrase('All') }}</option>
        <option value="logins"  {{ $type=='logins'?'selected':'' }}>{{ get_phrase('Logins / Logouts') }}</option>
        <option value="actions" {{ $type=='actions'?'selected':'' }}>{{ get_phrase('Actions') }}</option>
      </select>
    </div>
    <div class="col-lg-2 col-md-6">
      <label class="form-label" style="font-size:12.5px;font-weight:600;">{{ get_phrase('From') }}</label>
      <input type="date" name="from" value="{{ $from }}" class="form-control">
    </div>
    <div class="col-lg-2 col-md-6">
      <label class="form-label" style="font-size:12.5px;font-weight:600;">{{ get_phrase('To') }}</label>
      <input type="date" name="to" value="{{ $to }}" class="form-control">
    </div>
    <div class="col-lg-1 col-md-6">
      <button class="btn btn-primary w-100" type="submit"><i class="bi bi-funnel"></i></button>
    </div>
  </form>
</div>

{{-- Log table --}}
<div class="eSection-wrap" style="background:#fff;border:1px solid #eef1f0;border-radius:14px;padding:6px 4px;">
  <div class="table-responsive">
    <table class="table text-nowrap align-middle mb-0" style="font-size:13.5px;">
      <thead>
        <tr style="border-bottom:2px solid #f0f2f1;color:#8b93a0;font-size:12px;text-transform:uppercase;">
          <th style="padding:12px 14px;">{{ get_phrase('User') }}</th>
          <th>{{ get_phrase('Role') }}</th>
          <th>{{ get_phrase('Action') }}</th>
          <th>{{ get_phrase('Details') }}</th>
          <th>{{ get_phrase('IP') }}</th>
          <th>{{ get_phrase('Device') }}</th>
          <th class="text-end" style="padding-right:14px;">{{ get_phrase('When') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($logs as $log)
          @php
            $isLogin = in_array($log->action, ['Logged in','Logged out']);
            $badgeC = $log->action=='Logged in' ? '#0a7a52' : ($log->action=='Logged out' ? '#8b93a0' : '#2f6fb0');
            $badgeBg= $log->action=='Logged in' ? '#e9f7f1' : ($log->action=='Logged out' ? '#eef1f0' : '#eaf2fb');
          @endphp
          <tr style="border-bottom:1px solid #f5f6f6;">
            <td style="padding:11px 14px;font-weight:600;color:#2b2f3a;">{{ $log->user_name ?: '—' }}</td>
            <td><span style="font-size:12px;color:#5a6270;">{{ $log->role }}</span></td>
            <td>
              <span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;color:{{ $badgeC }};background:{{ $badgeBg }};">
                @if($isLogin)<i class="bi bi-{{ $log->action=='Logged in'?'box-arrow-in-right':'box-arrow-left' }}"></i> @endif{{ $log->action }}
              </span>
            </td>
            <td><span style="color:#8b93a0;font-size:12.5px;">{{ $log->description ?: '—' }}</span></td>
            <td><span style="color:#5a6270;font-size:12.5px;">{{ $log->ip_address ?: '—' }}</span></td>
            <td><span style="color:#8b93a0;font-size:12px;">{{ \Illuminate\Support\Str::limit($log->user_agent, 34) ?: '—' }}</span></td>
            <td class="text-end" style="padding-right:14px;">
              <span style="color:#2b2f3a;font-size:12.5px;">{{ $log->created_at ? $log->created_at->format('d M Y, g:i A') : '' }}</span><br>
              <span style="color:#b3bac2;font-size:11px;">{{ $log->created_at ? $log->created_at->diffForHumans() : '' }}</span>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted" style="padding:36px;">{{ get_phrase('No activity recorded yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3">{{ $logs->links() }}</div>

@endsection
