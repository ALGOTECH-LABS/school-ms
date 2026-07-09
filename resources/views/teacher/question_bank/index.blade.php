@extends('teacher.navigation')

@section('content')
<div class="mainSection-title">
  <div class="row"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
      <div class="d-flex flex-column">
        <h4>{{ get_phrase('Question bank') }}</h4>
        <ul class="d-flex align-items-center eBreadcrumb-2">
          <li><a href="#">{{ get_phrase('Home') }}</a></li>
          <li><a href="#">{{ get_phrase('Question bank') }}</a></li>
        </ul>
      </div>
      <div class="export-btn-area d-flex" style="gap:8px;">
        <a href="javascript:;" class="eBtn btn-secondary"
           onclick="rightModal('{{ route('teacher.qbank.generate_modal') }}', '{{ get_phrase('Generate quiz') }}')"><i class="bi bi-magic"></i> {{ get_phrase('Generate quiz') }}</a>
        <a href="javascript:;" class="export_btn"
           onclick="rightModal('{{ route('teacher.qbank.create_modal') }}', '{{ get_phrase('Add question') }}')"><i class="bi bi-plus"></i>{{ get_phrase('Add question') }}</a>
      </div>
    </div>
  </div></div>
</div>

<div class="row"><div class="col-12"><div class="eSection-wrap">
  <form method="GET" action="{{ route('teacher.qbank') }}" class="row g-2 mb-3">
    <div class="col-md-3">
      <select name="subject_id" class="form-select eForm-select">
        <option value="">{{ get_phrase('All subjects') }}</option>
        @foreach($subjects as $s)
          <option value="{{ $s->id }}" {{ (string)$subject_id===(string)$s->id?'selected':'' }}>{{ $s->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <select name="type" class="form-select eForm-select">
        <option value="">{{ get_phrase('All types') }}</option>
        @foreach(['mcq'=>'MCQ','truefalse'=>'True/False','short'=>'Short','essay'=>'Essay'] as $k=>$v)
          <option value="{{ $k }}" {{ $type===$k?'selected':'' }}>{{ $v }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <select name="difficulty" class="form-select eForm-select">
        <option value="">{{ get_phrase('Any difficulty') }}</option>
        @foreach(['easy','medium','hard'] as $d)
          <option value="{{ $d }}" {{ $difficulty===$d?'selected':'' }}>{{ ucfirst($d) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3"><input type="text" name="search" value="{{ $search }}" class="form-control eForm-control" placeholder="{{ get_phrase('Search questions...') }}"></div>
    <div class="col-md-2"><button class="eBtn btn-primary w-100" type="submit">{{ get_phrase('Filter') }}</button></div>
  </form>

  <div class="table-responsive">
    <table class="table eTable eTable-2">
      <thead><tr>
        <th>{{ get_phrase('Question') }}</th>
        <th>{{ get_phrase('Type') }}</th>
        <th>{{ get_phrase('Subject') }}</th>
        <th>{{ get_phrase('Difficulty') }}</th>
        <th>{{ get_phrase('Marks') }}</th>
        <th>{{ get_phrase('Options') }}</th>
      </tr></thead>
      <tbody>
        @php $typeLabels=['mcq'=>'MCQ','truefalse'=>'True/False','short'=>'Short','essay'=>'Essay']; @endphp
        @forelse($questions as $q)
          @php $sub = \App\Models\Subject::find($q->subject_id); @endphp
          <tr>
            <td style="max-width:420px;">{{ \Illuminate\Support\Str::limit($q->question, 90) }}</td>
            <td><span class="badge bg-primary">{{ $typeLabels[$q->type] ?? $q->type }}</span></td>
            <td>{{ $sub->name ?? '-' }}</td>
            <td><span class="badge {{ $q->difficulty=='hard'?'bg-danger':($q->difficulty=='easy'?'bg-success':'bg-secondary') }}">{{ ucfirst($q->difficulty) }}</span></td>
            <td>{{ $q->marks }}</td>
            <td>
              <a class="eBtn btn-secondary" href="javascript:;" onclick="rightModal('{{ route('teacher.qbank.edit_modal', $q->id) }}', '{{ get_phrase('Edit question') }}')">{{ get_phrase('Edit') }}</a>
              <a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this question?') }}')) postDelete('{{ route('teacher.qbank.delete', $q->id) }}')">{{ get_phrase('Delete') }}</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center">{{ get_phrase('No questions yet. Click "Add question" to build your bank.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-3">{{ $questions->links() }}</div>
</div></div></div>
@endsection
