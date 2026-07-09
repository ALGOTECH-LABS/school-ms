@extends('layouts.signin_page')

@section('content')

<style type="text/css">
  :root{
    --kh-green:#00955f; --kh-green-d:#00744a; --kh-orange:#f04b24; --kh-ink:#2b2f3a;
  }
  body{ background:#eef2f1; }
  .kh-wrap{ min-height:100vh; }
  .kh-wrap > .row{ margin:0; min-height:100vh; }

  /* ---------- left brand panel ---------- */
  .kh-hero{
    position:relative; min-height:100vh; overflow:hidden; color:#fff;
    background:linear-gradient(150deg,var(--kh-green) 0%, var(--kh-green-d) 60%, #005c3b 100%);
    display:flex; flex-direction:column; justify-content:center; padding:64px 60px;
  }
  .kh-hero:before, .kh-hero:after{ content:""; position:absolute; border-radius:50%; opacity:.10; background:#fff; }
  .kh-hero:before{ width:420px; height:420px; top:-120px; right:-120px; }
  .kh-hero:after{ width:300px; height:300px; bottom:-100px; left:-80px; opacity:.07; }
  .kh-badge{
    display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.15);
    padding:6px 14px; border-radius:30px; font-size:12.5px; font-weight:600; margin-bottom:28px; width:fit-content;
    position:relative;
  }
  .kh-hero h1{ font-size:38px; font-weight:800; line-height:1.15; margin:0 0 16px; position:relative; }
  .kh-hero .kh-lead{ font-size:16.5px; color:#fff !important; opacity:.95; max-width:440px; margin:0 0 34px; position:relative; }
  .kh-feats{ list-style:none; padding:0; margin:0; position:relative; }
  .kh-feats li{ display:flex; align-items:flex-start; gap:12px; margin-bottom:18px; }
  .kh-feats .ic{
    flex:none; width:38px; height:38px; border-radius:10px; background:rgba(255,255,255,.16);
    display:flex; align-items:center; justify-content:center; font-size:18px;
  }
  .kh-feats b{ display:block; font-weight:700; font-size:15px; }
  .kh-feats span{ opacity:.85; font-size:13px; }
  .kh-quote{ position:relative; margin-top:38px; font-size:13.5px; opacity:.8; border-left:3px solid rgba(255,255,255,.5); padding-left:14px; }

  /* ---------- right form panel ---------- */
  .kh-form-col{ min-height:100vh; display:flex; align-items:center; justify-content:center; padding:40px 24px; }
  .kh-card{ width:100%; max-width:420px; }
  .kh-logo{ text-align:center; margin-bottom:24px; }
  .kh-logo img{ height:64px; }
  .kh-card h2{ font-size:26px; font-weight:800; color:var(--kh-ink); margin:0 0 6px; text-align:center; }
  .kh-card .sub{ text-align:center; color:#7a8290; font-size:14.5px; margin-bottom:26px; }

  .kh-field{ margin-bottom:18px; }
  .kh-field label{ font-size:13px; font-weight:600; color:#4a5260; margin-bottom:7px; display:block; }
  .kh-input{ position:relative; }
  .kh-input .ic{ position:absolute; left:16px; top:50%; transform:translateY(-50%); color:#9aa2ad; font-size:17px; }
  .kh-input input{
    width:100%; height:52px; border:1.5px solid #e2e7e5; border-radius:12px; padding:0 44px 0 46px;
    font-size:15px; color:var(--kh-ink); background:#fff; transition:border-color .15s, box-shadow .15s;
  }
  .kh-input input::placeholder{ color:#b3bac2; }
  .kh-input input:focus{ outline:none; border-color:var(--kh-green); box-shadow:0 0 0 4px rgba(0,149,95,.12); }
  .kh-input .toggle{ position:absolute; right:14px; top:50%; transform:translateY(-50%); color:#9aa2ad; cursor:pointer; font-size:17px; }

  .kh-row{ display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; font-size:13.5px; }
  .kh-row a{ color:var(--kh-green); font-weight:600; text-decoration:none; }
  .kh-row a:hover{ text-decoration:underline; }
  .kh-check{ display:flex; align-items:center; gap:7px; color:#5a6270; cursor:pointer; margin:0; }
  .kh-check input{ width:16px; height:16px; accent-color:var(--kh-green); }

  .kh-btn{
    width:100%; height:52px; border:none; border-radius:12px; background:var(--kh-green); color:#fff;
    font-size:16px; font-weight:700; cursor:pointer; transition:background .15s, transform .05s;
    display:flex; align-items:center; justify-content:center; gap:9px;
  }
  .kh-btn:hover{ background:var(--kh-green-d); }
  .kh-btn:active{ transform:translateY(1px); }

  .kh-alert{ border-radius:10px; padding:12px 15px; font-size:14px; margin-bottom:20px; display:flex; align-items:center; gap:9px; }
  .kh-alert.err{ background:#fdecea; color:#c0311a; border:1px solid #f6ccc4; }
  .kh-alert.ok{ background:#e9f7f1; color:#0a7a52; border:1px solid #c4e8da; }

  .kh-foot{ text-align:center; margin-top:28px; font-size:12.5px; color:#98a0aa; }
  .kh-foot a{ color:var(--kh-green); text-decoration:none; font-weight:600; }

  @media (max-width: 991.98px){
    .kh-form-col{ padding:48px 20px; }
  }
</style>

<div class="kh-wrap">
  <div class="row">

    <!-- Brand hero -->
    <div class="col-lg-6 d-none d-lg-block p-0">
      <div class="kh-hero">
        <span class="kh-badge"><i class="bi bi-patch-check-fill"></i> ISO 9001:2015 Certified &middot; Leadership in Healthcare</span>
        <h1>The Karen Hospital<br>School of Nursing</h1>
        <p class="kh-lead">Your gateway to learning, examinations, results, and fees &mdash; all in one secure place.</p>
        <ul class="kh-feats">
          <li><span class="ic"><i class="bi bi-mortarboard-fill"></i></span><div><b>Learn online</b><span>Courses, coursework and timed exams</span></div></li>
          <li><span class="ic"><i class="bi bi-graph-up-arrow"></i></span><div><b>Track results</b><span>Grades, transcripts and attendance</span></div></li>
          <li><span class="ic"><i class="bi bi-credit-card-2-front-fill"></i></span><div><b>Manage fees</b><span>Invoices, receipts and online payments</span></div></li>
        </ul>
        <div class="kh-quote">Empowering nurses to excel as lifelong learners.</div>
      </div>
    </div>

    <!-- Login form -->
    <div class="col-lg-6 p-0">
      <div class="kh-form-col">
        <div class="kh-card">
          <div class="kh-logo">
            <img src="{{ asset('assets/uploads/logo/'.get_settings('dark_logo')) }}" alt="{{ get_settings('system_title') }}">
          </div>
          <h2>{{ get_phrase('Welcome back') }}</h2>
          <div class="sub">{{ get_phrase('Sign in to your account to continue') }}</div>

          @if ($errors->any())
            <div class="kh-alert err"><i class="bi bi-exclamation-triangle-fill"></i>
              <span>{{ get_phrase('Invalid email or password. Please try again.') }}</span></div>
          @endif
          @if (session('error'))
            <div class="kh-alert err"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ session('error') }}</span></div>
          @endif
          @if (session('message'))
            <div class="kh-alert ok"><i class="bi bi-check-circle-fill"></i><span>{{ session('message') }}</span></div>
          @endif

          <form method="post" action="{{ route('login') }}">
            @csrf
            <div class="kh-field">
              <label for="email">{{ get_phrase('Email address') }}</label>
              <div class="kh-input">
                <span class="ic"><i class="bi bi-envelope"></i></span>
                <input type="email" name="email" id="email" value="{{ old('email') }}"
                       placeholder="you@karenhospital.org" required autofocus>
              </div>
            </div>

            <div class="kh-field">
              <label for="password">{{ get_phrase('Password') }}</label>
              <div class="kh-input">
                <span class="ic"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" id="password" placeholder="Enter your password" required>
                <span class="toggle" onclick="khTogglePw()"><i class="bi bi-eye" id="khEye"></i></span>
              </div>
            </div>

            <div class="kh-row">
              <label class="kh-check"><input type="checkbox" name="remember"> {{ get_phrase('Remember me') }}</label>
              <a href="{{ route('password.request') }}">{{ get_phrase('Forgot password?') }}</a>
            </div>

            <button type="submit" class="kh-btn">{{ get_phrase('Sign in') }} <i class="bi bi-arrow-right"></i></button>
          </form>

          <div class="kh-foot">
            &copy; {{ date('Y') }} {{ get_settings('system_title') }} &middot; {{ get_phrase('Powered by') }}
            <a href="{{ get_settings('footer_link') }}" target="_blank">{{ str_replace('By ', '', get_settings('footer_text') ?? '') }}</a>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script type="text/javascript">
  function khTogglePw(){
    var p = document.getElementById('password'), e = document.getElementById('khEye');
    if(p.type === 'password'){ p.type = 'text'; e.className = 'bi bi-eye-slash'; }
    else { p.type = 'password'; e.className = 'bi bi-eye'; }
  }
</script>
@endsection
