{{-- Parent / Guardian picker — link an existing parent, add a new one inline, or none.
     Included by the Add-student modal and the Offline-admission form. Needs $parents. --}}
@php $parents = $parents ?? collect(); @endphp
<div class="pp-wrap" style="border:1px solid #e2e6ee;border-radius:12px;padding:14px 16px;margin:6px 0 14px;background:#fbfcfe;">
  <div class="d-flex align-items-center mb-2" style="gap:8px;">
    <i class="bi bi-people-fill" style="color:#00955f;"></i>
    <b style="font-size:14px;">{{ get_phrase('Parent / Guardian') }}</b>
  </div>

  <div class="fpb-7 mb-2">
    <label class="eForm-label">{{ get_phrase('How to link a parent') }}</label>
    <select name="parent_mode" class="form-select eForm-select pp-mode" onchange="khParentMode(this)">
      <option value="new">{{ get_phrase('Add a new parent') }}</option>
      <option value="existing">{{ get_phrase('Link an existing parent') }}</option>
      <option value="none">{{ get_phrase('No parent for now') }}</option>
    </select>
  </div>

  {{-- link existing --}}
  <div class="pp-existing" style="display:none;">
    <div class="fpb-7">
      <label class="eForm-label">{{ get_phrase('Select parent') }}</label>
      <select name="parent_id" class="form-select eForm-select pp-parent-select">
        <option value="">{{ get_phrase('Search a parent…') }}</option>
        @foreach($parents as $p)
          <option value="{{ $p->id }}">{{ $p->name }}{{ $p->email ? ' — '.$p->email : '' }}</option>
        @endforeach
      </select>
      @if(!count($parents))
        <small class="text-muted">{{ get_phrase('No parents on file yet — switch to “Add a new parent”.') }}</small>
      @endif
    </div>
  </div>

  {{-- add new --}}
  <div class="pp-new">
    <div class="row">
      <div class="col-md-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Parent name') }}</label>
        <input type="text" name="parent_name" class="form-control eForm-control" placeholder="{{ get_phrase('e.g. Jane Doe') }}">
      </div>
      <div class="col-md-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Parent email') }}</label>
        <input type="email" name="parent_email" class="form-control eForm-control" placeholder="parent@example.com">
      </div>
    </div>
    <div class="row">
      <div class="col-md-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Parent phone') }}</label>
        <input type="text" name="parent_phone" class="form-control eForm-control">
      </div>
      <div class="col-md-6 fpb-7">
        <label class="eForm-label">{{ get_phrase('Parent password') }}</label>
        <input type="text" name="parent_password" class="form-control eForm-control" value="12345678">
      </div>
    </div>
    <small class="text-muted">{{ get_phrase('The parent can log in with this email & password to see their child.') }}</small>
  </div>
</div>

<script type="text/javascript">
  "use strict";
  function khParentMode(sel){
    var form = sel.closest('form');
    if(!form) return;
    var mode = sel.value;
    var ex = form.querySelector('.pp-existing');
    var nw = form.querySelector('.pp-new');
    if(ex){
      ex.style.display = (mode === 'existing') ? '' : 'none';
      ex.querySelectorAll('select,input').forEach(function(el){ el.disabled = (mode !== 'existing'); });
    }
    if(nw){
      nw.style.display = (mode === 'new') ? '' : 'none';
      nw.querySelectorAll('input').forEach(function(el){ el.disabled = (mode !== 'new'); });
    }
  }
  // initialise every parent-mode select on the page (default = new)
  (function(){
    document.querySelectorAll('select.pp-mode').forEach(function(s){ khParentMode(s); });
    if (window.jQuery && jQuery.fn.select2) { jQuery('.pp-parent-select').select2(); }
  })();
</script>
