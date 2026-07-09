@extends('admin.navigation')
@section('content')
<div class="mainSection-title"><div class="row"><div class="col-12">
  <h4>{{ get_phrase('Complaints') }}</h4>
</div></div></div>
<div class="eSection-wrap text-center" style="padding:40px 20px;">
  <p class="text-muted mb-0">{{ get_phrase('No complaints have been submitted.') }}</p>
</div>
@endsection
