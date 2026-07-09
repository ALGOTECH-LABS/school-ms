@extends('teacher.navigation')

@section('content')
<div class="mainSection-title">
  <div class="row">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center flex-wrap gr-15">
        <div class="d-flex flex-column">
          <h4>{{ get_phrase('Assignments') }}</h4>
          <ul class="d-flex align-items-center eBreadcrumb-2">
            <li><a href="#">{{ get_phrase('Home') }}</a></li>
            <li><a href="#">{{ get_phrase('Assignments') }}</a></li>
          </ul>
        </div>
        <div class="export-btn-area">
          <a href="javascript:;" class="export_btn"
            onclick="rightModal('{{ route('teacher.assignment.create_modal') }}', '{{ get_phrase('Create assignment') }}')">
            <i class="bi bi-plus"></i>{{ get_phrase('Add assignment') }}</a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="eSection-wrap">

      <ul class="nav eTab-nav mb-3">
        <li><a class="eBtn {{ $type=='published'?'btn-primary':'btn-secondary' }} me-2" href="{{ route('teacher.assignment_home', ['type'=>'published']) }}">{{ get_phrase('Published') }}</a></li>
        <li><a class="eBtn {{ $type=='draft'?'btn-primary':'btn-secondary' }}" href="{{ route('teacher.assignment_home', ['type'=>'draft']) }}">{{ get_phrase('Draft') }}</a></li>
      </ul>

      <div class="table-responsive">
        <table class="table eTable eTable-2">
          <thead>
            <tr>
              <th>{{ get_phrase('Title') }}</th>
              <th>{{ get_phrase('Class / Section') }}</th>
              <th>{{ get_phrase('Subject') }}</th>
              <th>{{ get_phrase('Deadline') }}</th>
              <th>{{ get_phrase('Submissions') }}</th>
              <th>{{ get_phrase('Options') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse($assignments as $assignment)
              @php
                $roster = \App\Models\Enrollment::where('class_id',$assignment->class_id)->where('section_id',$assignment->section_id)->where('school_id',$assignment->school_id)->count();
                $submitted = \App\Models\AssignmentSubmission::where('assignment_id',$assignment->id)->count();
                $graded = \App\Models\AssignmentSubmission::where('assignment_id',$assignment->id)->where('status','returned')->count();
                $cls = \App\Models\Classes::find($assignment->class_id);
                $sec = \App\Models\Section::find($assignment->section_id);
                $sub = \App\Models\Subject::find($assignment->subject_id);
              @endphp
              <tr>
                <td>{{ $assignment->title }}</td>
                <td>{{ $cls->name ?? '-' }} / {{ $sec->name ?? '-' }}</td>
                <td>{{ $sub->name ?? '-' }}</td>
                <td>{{ $assignment->deadline ? date('d M Y', $assignment->deadline) : '—' }}</td>
                <td>
                  <span class="badge bg-primary">{{ $submitted }}/{{ $roster }} {{ get_phrase('submitted') }}</span>
                  <span class="badge bg-success">{{ $graded }} {{ get_phrase('graded') }}</span>
                </td>
                <td>
                  <a class="eBtn btn-secondary" href="{{ route('teacher.assignment.show', $assignment->id) }}">{{ get_phrase('View & grade') }}</a>
                  <a class="eBtn btn-danger" href="javascript:;" onclick="if(confirm('{{ get_phrase('Delete this assignment?') }}')) postDelete('{{ route('teacher.assignment.delete', $assignment->id) }}')">{{ get_phrase('Delete') }}</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center">{{ get_phrase('No assignments yet.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">{{ $assignments->links() }}</div>
    </div>
  </div>
</div>
@endsection
