<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\CourseTopic;
use App\Models\CourseLesson;
use App\Models\CourseMaterial;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\Enrollment;
use App\Models\TeacherPermission;
use App\Models\Syllabus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CourseSession;
use App\Models\CourseRemoval;
use App\Models\Section;
use App\Models\User;

class OnlineCourseController extends Controller
{
    private function schoolId()
    {
        return auth()->user()->school_id;
    }

    private function activeSession()
    {
        return get_school_settings($this->schoolId())->value('running_session');
    }

    /** A course the current teacher owns, or 404. */
    private function ownedCourse($id)
    {
        return Course::where('id', $id)
            ->where('teacher_id', auth()->user()->id)
            ->where('school_id', $this->schoolId())
            ->firstOrFail();
    }

    /* ===================================================================== TEACHER */

    public function teacherIndex()
    {
        $courses = Course::where('teacher_id', auth()->user()->id)
            ->where('school_id', $this->schoolId())
            ->orderByDesc('id')->paginate(9);

        return view('teacher.courses.index', compact('courses'));
    }

    public function teacherCreateModal()
    {
        $class_ids = TeacherPermission::where('teacher_id', auth()->user()->id)
            ->where('school_id', $this->schoolId())
            ->distinct()->pluck('class_id')->toArray();
        $classes = Classes::whereIn('id', $class_ids)->get();

        return view('teacher.courses.create', compact('classes'));
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
            'subject_id' => 'required',
        ]);

        $thumbnail = null;
        if ($request->hasFile('thumbnail')) {
            $request->validate(['thumbnail' => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120']);
            $file = $request->file('thumbnail');
            $thumbnail = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $dir = public_path('assets/uploads/course_thumbnails/');
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $file->move($dir, $thumbnail);
        }

        $course = Course::create([
            'school_id'   => $this->schoolId(),
            'session_id'  => $this->activeSession(),
            'teacher_id'  => auth()->user()->id,
            'class_id'    => $request->class_id,
            'subject_id'  => $request->subject_id,
            'title'       => $request->title,
            'description' => $request->description,
            'thumbnail'   => $thumbnail,
            'status'      => $request->status ?? 'published',
        ]);

        return redirect()->route('teacher.addons.course.manage', $course->id)
            ->with('message', get_phrase('Course created. Now add topics and lessons.'));
    }

    public function teacherManage($id)
    {
        $course = $this->ownedCourse($id);
        $course->load('topics.lessons.materials', 'sessions');

        // Roster: students enrolled in this course's class (courses are class-based).
        $enrollments = Enrollment::where('class_id', $course->class_id)
            ->where('school_id', $course->school_id)->get();
        $sectionNames  = Section::whereIn('id', $enrollments->pluck('section_id'))->pluck('name', 'id');
        $sectionByUser = $enrollments->pluck('section_id', 'user_id');
        $students = User::whereIn('id', $enrollments->pluck('user_id'))
            ->where('role_id', 7)->orderBy('name')->get()
            ->map(function ($s) use ($sectionByUser, $sectionNames) {
                $s->section_name = $sectionNames[$sectionByUser[$s->id] ?? null] ?? '-';
                return $s;
            });
        $className = optional(Classes::find($course->class_id))->name;

        // Coursework = assignments the teacher set for this course's class + subject.
        $coursework = Assignment::where('class_id', $course->class_id)
            ->where('subject_id', $course->subject_id)
            ->where('teacher_id', auth()->user()->id)
            ->where('school_id', $course->school_id)
            ->orderByDesc('id')->get()
            ->map(function ($a) {
                $subs = AssignmentSubmission::where('assignment_id', $a->id);
                $a->submission_count = (clone $subs)->count();
                $a->graded_count     = (clone $subs)->whereNotNull('graded_at')->count();
                return $a;
            });

        // Per-student coursework progress (submitted / total coursework).
        $courseworkIds  = $coursework->pluck('id');
        $totalCoursework = $courseworkIds->count();
        $submittedByUser = $totalCoursework
            ? AssignmentSubmission::whereIn('assignment_id', $courseworkIds)
                ->select('student_id')->selectRaw('COUNT(DISTINCT assignment_id) as c')
                ->groupBy('student_id')->pluck('c', 'student_id')
            : collect();
        // Removal status per student (removed-from-this-course, with reason).
        $removals = CourseRemoval::where('course_id', $course->id)->get()->keyBy('student_id');
        $students = $students->map(function ($s) use ($submittedByUser, $totalCoursework, $removals) {
            $s->submitted_count  = (int) ($submittedByUser[$s->id] ?? 0);
            $s->total_coursework = $totalCoursework;
            $rec = $removals[$s->id] ?? null;
            $s->is_removed     = $rec && $rec->status === 'removed';
            $s->removal_reason = $rec ? $rec->reason : null;
            return $s;
        });
        $removedCount = $students->where('is_removed', true)->count();

        $now      = now();
        $sessions = $course->sessions;
        $upcoming = $sessions->filter(fn ($x) => $x->status === 'scheduled'
            && $x->session_date && $x->session_date->copy()->addMinutes((int) $x->duration_minutes)->gte($now))->values();
        $past = $sessions->filter(fn ($x) => !($x->status === 'scheduled'
            && $x->session_date && $x->session_date->copy()->addMinutes((int) $x->duration_minutes)->gte($now)))
            ->sortByDesc('session_date')->values();

        return view('teacher.courses.manage', compact(
            'course', 'students', 'className', 'coursework', 'totalCoursework', 'upcoming', 'past', 'removedCount'
        ));
    }

    /* ---- coursework (course-scoped assignment creation; class + subject are fixed) ---- */
    public function courseworkCreateModal($course_id)
    {
        $course   = $this->ownedCourse($course_id);
        $class    = Classes::find($course->class_id);
        $subject  = Subject::find($course->subject_id);
        $sections = Section::where('class_id', $course->class_id)->orderBy('id')->get();

        return view('teacher.courses.coursework_modal', compact('course', 'class', 'subject', 'sections'));
    }

    public function courseworkStore(Request $request)
    {
        $course = $this->ownedCourse($request->course_id);
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'section_id'  => 'required|string', // section id or "all"
            'description' => 'nullable|string|max:4000',
            'total_marks' => 'required|integer|min:1|max:1000',
            'deadline'    => 'nullable|date',
            'attachment'  => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,png,jpg,jpeg|max:20480',
            'status'      => 'required|in:published,draft',
        ]);

        // class + subject come from the course context (never re-asked).
        $sectionIds = $data['section_id'] === 'all'
            ? Section::where('class_id', $course->class_id)->pluck('id')->all()
            : [(int) $data['section_id']];

        $attachment = null;
        if ($request->hasFile('attachment')) {
            $f = $request->file('attachment');
            $attachment = time() . '_' . preg_replace('/\s+/', '_', $f->getClientOriginalName());
            $dir = public_path('assets/uploads/assignments/');
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $f->move($dir, $attachment);
        }

        $deadline = $data['deadline'] ? strtotime($data['deadline']) : null;

        foreach ($sectionIds as $sid) {
            Assignment::create([
                'school_id'   => $course->school_id,
                'session_id'  => $this->activeSession(),
                'teacher_id'  => auth()->user()->id,
                'class_id'    => $course->class_id,
                'section_id'  => $sid,
                'subject_id'  => $course->subject_id,
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'total_marks' => $data['total_marks'],
                'attachment'  => $attachment,
                'deadline'    => $deadline,
                'status'      => $data['status'],
                'is_quiz'     => 0,
            ]);
        }

        return redirect()->route('teacher.addons.course.manage', $course->id)
            ->with('message', get_phrase('Coursework added to') . ' ' . $course->title . '.');
    }

    /* ---- remove / re-admit a student from this course (with a reason) ---- */
    public function studentRemove(Request $request)
    {
        $course = $this->ownedCourse($request->course_id);
        $data = $request->validate([
            'student_id' => 'required|integer',
            'reason'     => 'required|string|max:1000',
        ]);

        CourseRemoval::updateOrCreate(
            ['course_id' => $course->id, 'student_id' => $data['student_id']],
            ['school_id' => $course->school_id, 'reason' => $data['reason'],
             'status' => 'removed', 'removed_by' => auth()->user()->id]
        );

        return redirect()->back()->with('message', get_phrase('Student removed from the course.'));
    }

    public function studentReadmit(Request $request)
    {
        $course = $this->ownedCourse($request->course_id);
        $request->validate(['student_id' => 'required|integer']);

        CourseRemoval::where('course_id', $course->id)
            ->where('student_id', $request->student_id)
            ->update(['status' => 'active']);

        return redirect()->back()->with('message', get_phrase('Student re-admitted to the course.'));
    }

    /* ---- live sessions ---- */
    public function sessionStore(Request $request)
    {
        $course = $this->ownedCourse($request->course_id);
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'platform'         => 'required|in:zoom,meet,teams,other',
            'meeting_url'      => 'nullable|url|max:1000',
            'session_date'     => 'required|date',
            'duration_minutes' => 'required|integer|min:5|max:600',
            'description'      => 'nullable|string|max:2000',
        ]);

        CourseSession::create([
            'course_id'        => $course->id,
            'school_id'        => $course->school_id,
            'teacher_id'       => auth()->user()->id,
            'title'            => $data['title'],
            'platform'         => $data['platform'],
            'meeting_url'      => $data['meeting_url'] ?? null,
            'session_date'     => $data['session_date'],
            'duration_minutes' => $data['duration_minutes'],
            'description'      => $data['description'] ?? null,
            'status'           => 'scheduled',
        ]);

        return redirect()->back()->with('message', get_phrase('Online session scheduled.'));
    }

    public function sessionDelete($id)
    {
        $session = CourseSession::findOrFail($id);
        $this->ownedCourse($session->course_id); // authorize ownership
        $session->delete();
        return redirect()->back()->with('message', get_phrase('Session removed.'));
    }

    public function sessionCancel($id)
    {
        $session = CourseSession::findOrFail($id);
        $this->ownedCourse($session->course_id);
        $session->update(['status' => $session->status === 'cancelled' ? 'scheduled' : 'cancelled']);
        return redirect()->back()->with('message', get_phrase('Session updated.'));
    }

    public function teacherDeleteCourse($id)
    {
        $course = $this->ownedCourse($id);
        $lessonIds = CourseLesson::where('course_id', $course->id)->pluck('id');
        CourseMaterial::whereIn('lesson_id', $lessonIds)->delete();
        CourseLesson::where('course_id', $course->id)->delete();
        CourseTopic::where('course_id', $course->id)->delete();
        $course->delete();
        return redirect()->route('teacher.addons.courses')->with('message', get_phrase('Course deleted.'));
    }

    /* ---- topics ---- */
    public function topicStore(Request $request)
    {
        $course = $this->ownedCourse($request->course_id);
        $request->validate(['title' => 'required|string|max:255']);
        CourseTopic::create([
            'course_id'  => $course->id,
            'title'      => $request->title,
            'sort_order' => (int) CourseTopic::where('course_id', $course->id)->max('sort_order') + 1,
        ]);
        return redirect()->back()->with('message', get_phrase('Topic added.'));
    }

    public function topicDelete($id)
    {
        $topic = CourseTopic::findOrFail($id);
        $this->ownedCourse($topic->course_id);
        $lessonIds = CourseLesson::where('topic_id', $id)->pluck('id');
        CourseMaterial::whereIn('lesson_id', $lessonIds)->delete();
        CourseLesson::where('topic_id', $id)->delete();
        $topic->delete();
        return redirect()->back()->with('message', get_phrase('Topic deleted.'));
    }

    /* ---- lessons ---- */
    public function lessonCreateModal($topic_id)
    {
        $topic = CourseTopic::findOrFail($topic_id);
        $this->ownedCourse($topic->course_id);
        return view('teacher.courses.lesson_form', ['topic' => $topic, 'lesson' => null]);
    }

    public function lessonStore(Request $request)
    {
        $topic = CourseTopic::findOrFail($request->topic_id);
        $this->ownedCourse($topic->course_id);
        $request->validate(['title' => 'required|string|max:255']);
        CourseLesson::create([
            'course_id'  => $topic->course_id,
            'topic_id'   => $topic->id,
            'title'      => $request->title,
            'content'    => $request->content,
            'sort_order' => (int) CourseLesson::where('topic_id', $topic->id)->max('sort_order') + 1,
        ]);
        return redirect()->route('teacher.addons.course.manage', $topic->course_id)
            ->with('message', get_phrase('Lesson added.'));
    }

    public function lessonEditModal($id)
    {
        $lesson = CourseLesson::findOrFail($id);
        $this->ownedCourse($lesson->course_id);
        return view('teacher.courses.lesson_form', ['lesson' => $lesson, 'topic' => $lesson->topic]);
    }

    public function lessonUpdate(Request $request, $id)
    {
        $lesson = CourseLesson::findOrFail($id);
        $this->ownedCourse($lesson->course_id);
        $request->validate(['title' => 'required|string|max:255']);
        $lesson->update(['title' => $request->title, 'content' => $request->content]);
        return redirect()->route('teacher.addons.course.manage', $lesson->course_id)
            ->with('message', get_phrase('Lesson updated.'));
    }

    public function lessonDelete($id)
    {
        $lesson = CourseLesson::findOrFail($id);
        $this->ownedCourse($lesson->course_id);
        CourseMaterial::where('lesson_id', $id)->delete();
        $lesson->delete();
        return redirect()->back()->with('message', get_phrase('Lesson deleted.'));
    }

    /* ---- materials ---- */
    public function materialStore(Request $request)
    {
        $lesson = CourseLesson::findOrFail($request->lesson_id);
        $this->ownedCourse($lesson->course_id);
        $request->validate([
            'title' => 'required|string|max:255',
            'type'  => 'required|in:file,link,video',
        ]);

        $file = null;
        $url  = null;
        if ($request->type === 'file') {
            if ($request->hasFile('file')) {
                $request->validate(['file' => 'file|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,zip,png,jpg,jpeg,gif,mp4|max:51200']);
                $f = $request->file('file');
                $file = time() . '_' . preg_replace('/\s+/', '_', $f->getClientOriginalName());
                $dir = public_path('assets/uploads/course_materials/');
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                $f->move($dir, $file);
            } else {
                return redirect()->back()->with('error', get_phrase('Please choose a file to upload.'));
            }
        } else {
            $url = $request->url;
            if (!$url) return redirect()->back()->with('error', get_phrase('Please provide a URL.'));
        }

        CourseMaterial::create([
            'course_id' => $lesson->course_id,
            'lesson_id' => $lesson->id,
            'title'     => $request->title,
            'type'      => $request->type,
            'file'      => $file,
            'url'       => $url,
        ]);
        return redirect()->back()->with('message', get_phrase('Material added.'));
    }

    public function materialDelete($id)
    {
        $material = CourseMaterial::findOrFail($id);
        $lesson = CourseLesson::findOrFail($material->lesson_id);
        $this->ownedCourse($lesson->course_id);
        $material->delete();
        return redirect()->back()->with('message', get_phrase('Material removed.'));
    }

    /* ===================================================================== STUDENT */

    private function studentEnrollment()
    {
        return Enrollment::where('user_id', auth()->user()->id)
            ->where('school_id', $this->schoolId())->latest()->first();
    }

    public function studentIndex()
    {
        $enroll = $this->studentEnrollment();
        if (!$enroll) {
            return view('student.courses.index', ['courses' => collect()]);
        }
        // Courses this student was removed from are hidden.
        $removedCourseIds = CourseRemoval::where('student_id', auth()->user()->id)
            ->where('status', 'removed')->pluck('course_id');

        $courses = Course::where('class_id', $enroll->class_id)
            ->where('school_id', $this->schoolId())
            ->where('status', 'published')
            ->when($removedCourseIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $removedCourseIds))
            ->orderByDesc('id')->paginate(9);

        return view('student.courses.index', compact('courses'));
    }

    public function studentView($id)
    {
        $enroll = $this->studentEnrollment();
        $course = Course::where('id', $id)->where('status', 'published')->firstOrFail();
        abort_if(!$enroll || $enroll->class_id != $course->class_id, 403);

        // Blocked if the teacher removed this student from the course.
        $removed = CourseRemoval::where('course_id', $course->id)
            ->where('student_id', auth()->user()->id)
            ->where('status', 'removed')->exists();
        abort_if($removed, 403, get_phrase('You have been removed from this course.'));

        $course->load('topics.lessons.materials', 'sessions');

        // tie-in: syllabus + assignments for this class+subject (student's section)
        $syllabus = Syllabus::where('class_id', $course->class_id)
            ->where('subject_id', $course->subject_id)
            ->where('school_id', $this->schoolId())->get();
        $assignments = Assignment::where('class_id', $course->class_id)
            ->where('section_id', $enroll->section_id)
            ->where('subject_id', $course->subject_id)
            ->where('school_id', $this->schoolId())
            ->where('status', 'published')->get();

        // Live sessions: upcoming (still joinable) vs past.
        $now      = now();
        $upcoming = $course->sessions->filter(fn ($x) => $x->status === 'scheduled'
            && $x->session_date && $x->session_date->copy()->addMinutes((int) $x->duration_minutes)->gte($now))->values();
        $past = $course->sessions->filter(fn ($x) => !($x->status === 'scheduled'
            && $x->session_date && $x->session_date->copy()->addMinutes((int) $x->duration_minutes)->gte($now)))
            ->sortByDesc('session_date')->values();

        return view('student.courses.view', compact('course', 'syllabus', 'assignments', 'upcoming', 'past'));
    }

    /* ===================================================================== ADMIN */

    public function adminIndex(Request $request)
    {
        $class_id = $request->class_id ?? '';
        $courses = Course::where('school_id', $this->schoolId())
            ->when($class_id !== '', fn($q) => $q->where('class_id', $class_id))
            ->orderByDesc('id')->paginate(12);
        $classes = Classes::where('school_id', $this->schoolId())->get();

        return view('admin.courses.index', compact('courses', 'classes', 'class_id'));
    }

    public function adminView($id)
    {
        $course = Course::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $course->load('topics.lessons.materials');
        return view('admin.courses.view', compact('course'));
    }
}
