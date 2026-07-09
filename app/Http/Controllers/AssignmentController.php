<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classes;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Enrollment;
use App\Models\TeacherPermission;
use App\Models\User;

class AssignmentController extends Controller
{
    private function schoolId()
    {
        return auth()->user()->school_id;
    }

    private function activeSession()
    {
        return get_school_settings($this->schoolId())->value('running_session');
    }

    /* ===================================================================== TEACHER */

    public function teacherHome(Request $request, $type = 'published')
    {
        $assignments = Assignment::where('teacher_id', auth()->user()->id)
            ->where('school_id', $this->schoolId())
            ->when($type == 'draft', fn($q) => $q->where('status', 'draft'))
            ->when($type == 'published', fn($q) => $q->where('status', 'published'))
            ->orderByDesc('id')
            ->paginate(10);

        return view('teacher.assignments.index', compact('assignments', 'type'));
    }

    public function teacherCreateModal()
    {
        $classes = $this->permittedClasses();
        return view('teacher.assignments.create', compact('classes'));
    }

    private function permittedClasses()
    {
        $class_ids = TeacherPermission::where('teacher_id', auth()->user()->id)
            ->where('school_id', $this->schoolId())
            ->distinct()->pluck('class_id')->toArray();
        return Classes::whereIn('id', $class_ids)->get();
    }

    public function classSections(Request $request)
    {
        $sections = TeacherPermission::where('teacher_id', auth()->user()->id)
            ->where('class_id', $request->class_id)
            ->distinct()->pluck('section_id')->toArray();
        $sections = Section::whereIn('id', $sections)->get();

        $options = '<option value="">' . get_phrase('Select a section') . '</option>';
        foreach ($sections as $s) {
            $options .= '<option value="' . $s->id . '">' . $s->name . '</option>';
        }
        echo $options;
    }

    public function classSubjects(Request $request)
    {
        $subjects = Subject::where('class_id', $request->class_id)
            ->where('school_id', $this->schoolId())->get();

        $options = '<option value="">' . get_phrase('Select a subject') . '</option>';
        foreach ($subjects as $s) {
            $options .= '<option value="' . $s->id . '">' . $s->name . '</option>';
        }
        echo $options;
    }

    public function teacherStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'class_id' => 'required',
            'section_id' => 'required',
            'subject_id' => 'required',
            'total_marks' => 'required|numeric|min:1',
        ]);

        $attachment = null;
        if ($request->hasFile('attachment')) {
            $request->validate(['attachment' => 'file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,png,jpg,jpeg,gif|max:51200']);
            $file = $request->file('attachment');
            $attachment = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $dir = public_path('assets/uploads/assignments/');
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $file->move($dir, $attachment);
        }

        Assignment::create([
            'school_id'   => $this->schoolId(),
            'session_id'  => $this->activeSession(),
            'teacher_id'  => auth()->user()->id,
            'class_id'    => $request->class_id,
            'section_id'  => $request->section_id,
            'subject_id'  => $request->subject_id,
            'title'       => $request->title,
            'description' => $request->description,
            'total_marks' => $request->total_marks,
            'attachment'  => $attachment,
            'deadline'    => $request->deadline ? strtotime($request->deadline) : null,
            'status'      => $request->status ?? 'published',
        ]);

        return redirect()->back()->with('message', get_phrase('Assignment created successfully.'));
    }

    public function teacherShow($id)
    {
        $assignment = Assignment::where('id', $id)->where('teacher_id', auth()->user()->id)->firstOrFail();

        if ($assignment->is_quiz) {
            return redirect()->route('teacher.quiz.review', $assignment->id);
        }

        // roster of enrolled students for this class/section with their submission (if any)
        $student_ids = Enrollment::where('class_id', $assignment->class_id)
            ->where('section_id', $assignment->section_id)
            ->where('school_id', $assignment->school_id)
            ->pluck('user_id');
        $students = User::whereIn('id', $student_ids)->where('role_id', 7)->orderBy('name')->get();
        $submissions = AssignmentSubmission::where('assignment_id', $id)->get()->keyBy('student_id');

        return view('teacher.assignments.show', compact('assignment', 'students', 'submissions'));
    }

    public function teacherGrade(Request $request, $submission_id)
    {
        $submission = AssignmentSubmission::findOrFail($submission_id);
        $assignment = Assignment::where('id', $submission->assignment_id)
            ->where('teacher_id', auth()->user()->id)->firstOrFail();

        $request->validate(['obtained_marks' => 'required|numeric|min:0|max:' . $assignment->total_marks]);

        $submission->update([
            'obtained_marks' => $request->obtained_marks,
            'feedback'       => $request->feedback,
            'status'         => 'returned',
            'graded_at'      => time(),
        ]);

        return redirect()->back()->with('message', get_phrase('Assignment graded and returned to student.'));
    }

    public function teacherDelete($id)
    {
        $assignment = Assignment::where('id', $id)->where('teacher_id', auth()->user()->id)->firstOrFail();
        AssignmentSubmission::where('assignment_id', $id)->delete();
        $assignment->delete();
        return redirect()->back()->with('message', get_phrase('Assignment deleted.'));
    }

    /* ===================================================================== STUDENT */

    public function studentHome(Request $request, $type = 'active')
    {
        $enroll = Enrollment::where('user_id', auth()->user()->id)
            ->where('school_id', $this->schoolId())->latest()->first();

        if (!$enroll) {
            $assignments = collect();
            return view('student.assignments.index', ['assignments' => $assignments, 'type' => $type, 'submissions' => collect()]);
        }

        $query = Assignment::where('class_id', $enroll->class_id)
            ->where('section_id', $enroll->section_id)
            ->where('school_id', $this->schoolId())
            ->where('status', 'published');

        $submittedIds = AssignmentSubmission::where('student_id', auth()->user()->id)->pluck('assignment_id')->toArray();

        if ($type == 'active') {
            $query->whereNotIn('id', $submittedIds);
        } elseif ($type == 'submitted') {
            $query->whereIn('id', $submittedIds);
        }

        $assignments = $query->orderByDesc('id')->paginate(10);
        $submissions = AssignmentSubmission::where('student_id', auth()->user()->id)->get()->keyBy('assignment_id');

        return view('student.assignments.index', compact('assignments', 'type', 'submissions'));
    }

    public function studentShow($id)
    {
        $assignment = Assignment::where('id', $id)->where('status', 'published')->firstOrFail();

        if ($assignment->is_quiz) {
            return redirect()->route('student.quiz.take', $assignment->id);
        }

        // ensure the student belongs to this assignment's class/section
        $enroll = Enrollment::where('user_id', auth()->user()->id)
            ->where('class_id', $assignment->class_id)
            ->where('section_id', $assignment->section_id)->first();
        abort_if(!$enroll, 403);

        $submission = AssignmentSubmission::where('assignment_id', $id)
            ->where('student_id', auth()->user()->id)->first();

        return view('student.assignments.show', compact('assignment', 'submission'));
    }

    public function studentSubmit(Request $request, $id)
    {
        $assignment = Assignment::where('id', $id)->where('status', 'published')->firstOrFail();

        $existing = AssignmentSubmission::where('assignment_id', $id)
            ->where('student_id', auth()->user()->id)->first();

        // once returned/graded, lock further edits
        if ($existing && $existing->status == 'returned') {
            return redirect()->back()->with('error', get_phrase('This assignment has already been graded.'));
        }

        $attachment = $existing->attachment ?? null;
        if ($request->hasFile('attachment')) {
            $request->validate(['attachment' => 'file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,png,jpg,jpeg,gif|max:51200']);
            $file = $request->file('attachment');
            $attachment = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $dir = public_path('assets/uploads/submissions/');
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $file->move($dir, $attachment);
        }

        AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $id, 'student_id' => auth()->user()->id],
            [
                'school_id'       => $assignment->school_id,
                'submission_text' => $request->submission_text,
                'attachment'      => $attachment,
                'submitted_at'    => time(),
                'status'          => 'submitted',
            ]
        );

        return redirect()->route('student.assignment_home', ['type' => 'submitted'])
            ->with('message', get_phrase('Assignment submitted successfully.'));
    }

    /* ===================================================================== ADMIN */

    public function adminHome(Request $request, $type = 'published')
    {
        $class_id   = $request->class_id ?? '';
        $section_id = $request->section_id ?? '';

        $assignments = Assignment::where('school_id', $this->schoolId())
            ->when($class_id !== '', fn($q) => $q->where('class_id', $class_id))
            ->when($section_id !== '', fn($q) => $q->where('section_id', $section_id))
            ->orderByDesc('id')->paginate(10);

        $classes = Classes::where('school_id', $this->schoolId())->get();

        return view('admin.assignments.index', compact('assignments', 'classes', 'class_id', 'section_id', 'type'));
    }

    public function adminSections(Request $request)
    {
        $sections = Section::where('class_id', $request->class_id)->get();
        $options = '<option value="">' . get_phrase('All sections') . '</option>';
        foreach ($sections as $s) {
            $options .= '<option value="' . $s->id . '">' . $s->name . '</option>';
        }
        echo $options;
    }

    public function adminShow($id)
    {
        $assignment = Assignment::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $student_ids = Enrollment::where('class_id', $assignment->class_id)
            ->where('section_id', $assignment->section_id)
            ->where('school_id', $assignment->school_id)->pluck('user_id');
        $students = User::whereIn('id', $student_ids)->where('role_id', 7)->orderBy('name')->get();
        $submissions = AssignmentSubmission::where('assignment_id', $id)->get()->keyBy('student_id');

        return view('admin.assignments.show', compact('assignment', 'students', 'submissions'));
    }
}
