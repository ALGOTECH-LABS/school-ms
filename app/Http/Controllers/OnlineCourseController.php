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
use App\Models\AssignmentQuestion;
use App\Models\Question;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\ExamQuestion;
use App\Models\Gradebook;
use App\Models\DailyAttendances;
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

    /** Subject ids in this school sharing the given subject's name (bank is reusable across classes). */
    private function sameNameSubjectIds($subject_id)
    {
        $name = Subject::where('id', $subject_id)->value('name');
        if (!$name) return [$subject_id];
        return Subject::where('school_id', $this->schoolId())->where('name', $name)->pluck('id')->all();
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

        // All work the teacher set for this course's class + subject,
        // split into coursework (assignments) and CATs/exams (quizzes).
        $allWork = Assignment::where('class_id', $course->class_id)
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
        $coursework = $allWork->where('is_quiz', 0)->values();
        $cats       = $allWork->where('is_quiz', 1)->values();

        // question count per CAT
        $qCounts = AssignmentQuestion::whereIn('assignment_id', $cats->pluck('id'))
            ->selectRaw('assignment_id, COUNT(*) c')->groupBy('assignment_id')->pluck('c', 'assignment_id');
        $cats = $cats->map(function ($c) use ($qCounts) {
            $c->question_count = (int) ($qCounts[$c->id] ?? 0);
            return $c;
        });

        // Sitting CATs / physical exams for this course's class + subject (marks via gradebook).
        $sittingCats = Exam::where('exam_type', 'offline')
            ->where('class_id', $course->class_id)
            ->where('subject_id', $course->subject_id)
            ->where('school_id', $course->school_id)
            ->where('session_id', $this->activeSession())
            ->orderByDesc('starting_time')->get()
            ->map(function ($e) use ($course) {
                // students already marked for this exam's category+subject
                $e->marked_count = Gradebook::where('exam_category_id', $e->exam_category_id)
                    ->where('class_id', $e->class_id)
                    ->where('session_id', $e->session_id)
                    ->where('school_id', $e->school_id)
                    ->where('marks', 'LIKE', '%"' . $course->subject_id . '":%')
                    ->count();
                $e->question_count = ExamQuestion::where('exam_id', $e->id)->count();
                return $e;
            });

        // Per-student progress (submitted / total work — coursework + CATs).
        $courseworkIds  = $allWork->pluck('id');
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
            'course', 'students', 'className', 'coursework', 'cats', 'sittingCats', 'totalCoursework', 'upcoming', 'past', 'removedCount'
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

    /* ---- CATs / exams (course-scoped online CAT creation; class + subject fixed) ---- */

    public function catCreateModal($course_id)
    {
        $course   = $this->ownedCourse($course_id);
        $class    = Classes::find($course->class_id);
        $subject  = Subject::find($course->subject_id);
        $sections = Section::where('class_id', $course->class_id)->orderBy('id')->get();

        // how many questions the bank holds for this subject (creation hint)
        $bankCount = Question::where('school_id', $this->schoolId())
            ->whereIn('subject_id', $this->sameNameSubjectIds($course->subject_id))->count();

        return view('teacher.courses.cat_modal', compact('course', 'class', 'subject', 'sections', 'bankCount'));
    }

    public function catStore(Request $request)
    {
        $course = $this->ownedCourse($request->course_id);
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'section_id'       => 'required|string', // section id or "all"
            'count'            => 'required|integer|min:1|max:100',
            'difficulty'       => 'nullable|in:easy,medium,hard',
            'qtype'            => 'nullable|in:mcq,truefalse,short,essay',
            'topic'            => 'nullable|string|max:255',
            'duration_minutes' => 'nullable|integer|min:1|max:600',
            'deadline'         => 'nullable|date',
        ]);

        // "start empty" skips the random draw — the teacher hand-picks questions afterwards
        $startEmpty = (bool) $request->start_empty;

        // draw the paper ONCE — every section sits the same set of questions
        $pool = $startEmpty ? collect() : Question::where('school_id', $this->schoolId())
            ->whereIn('subject_id', $this->sameNameSubjectIds($course->subject_id))
            ->when($data['difficulty'] ?? null, fn ($q, $d) => $q->where('difficulty', $d))
            ->when($data['topic'] ?? null, fn ($q, $t) => $q->where('topic', 'LIKE', "%{$t}%"))
            ->when($data['qtype'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->inRandomOrder()->limit((int) $data['count'])->get();

        if (!$startEmpty && $pool->count() === 0) {
            return redirect()->back()->with('error',
                get_phrase('No questions match those filters — add questions to the bank first, or tick "Start empty".'));
        }

        $sectionIds = $data['section_id'] === 'all'
            ? Section::where('class_id', $course->class_id)->pluck('id')->all()
            : [(int) $data['section_id']];

        $total    = (int) $pool->sum('marks');
        $deadline = !empty($data['deadline']) ? strtotime($data['deadline']) : null;
        $firstQuizId = null;

        foreach ($sectionIds as $sid) {
            $assignment = Assignment::create([
                'school_id'   => $course->school_id,
                'session_id'  => $this->activeSession(),
                'teacher_id'  => auth()->user()->id,
                'class_id'    => $course->class_id,
                'section_id'  => $sid,
                'subject_id'  => $course->subject_id,
                'title'       => $data['title'],
                'description' => 'Online CAT — answer all questions.',
                'total_marks' => $total,
                'deadline'    => $deadline,
                'duration_minutes' => !empty($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
                'status'      => 'published',
                'is_quiz'     => 1,
            ]);

            $firstQuizId = $firstQuizId ?: $assignment->id;
            $so = 1;
            foreach ($pool as $q) {
                AssignmentQuestion::create([
                    'assignment_id' => $assignment->id,
                    'question_id'   => $q->id,
                    'marks'         => $q->marks,
                    'sort_order'    => $so++,
                ]);
            }
        }

        // empty CAT → go straight to the question picker
        if ($startEmpty) {
            return redirect()->route('teacher.quiz.questions', $firstQuizId)
                ->with('message', get_phrase('CAT created empty — now pick or write its questions.'));
        }

        return redirect(route('teacher.addons.course.manage', $course->id) . '#tab-cats')
            ->with('message', get_phrase('CAT created with') . ' ' . $pool->count() . ' ' . get_phrase('questions for') . ' ' . count($sectionIds) . ' ' . get_phrase('section(s).'));
    }

    /* ---- sitting CAT / physical exam (course-scoped offline Exam; marks via gradebook) ---- */

    public function sittingCatCreateModal($course_id)
    {
        $course  = $this->ownedCourse($course_id);
        $class   = Classes::find($course->class_id);
        $subject = Subject::find($course->subject_id);

        $categories = ExamCategory::where('school_id', $this->schoolId())
            ->where('session_id', $this->activeSession())->orderBy('name')->get();

        return view('teacher.courses.sitting_cat_modal', compact('course', 'class', 'subject', 'categories'));
    }

    public function sittingCatStore(Request $request)
    {
        $course = $this->ownedCourse($request->course_id);
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'exam_category_id' => 'required|string',       // category id or "new"
            'starting_at'      => 'required|date',
            'ending_at'        => 'required|date|after:starting_at',
            'room_number'      => 'nullable|string|max:255',
            'total_marks'      => 'required|integer|min:1|max:1000',
        ]);

        // Marks live in the gradebook keyed by (exam_category, subject) — a new
        // category per CAT keeps papers from overwriting each other's marks.
        if ($data['exam_category_id'] === 'new') {
            $category = ExamCategory::create([
                'name'       => $data['title'],
                'school_id'  => $this->schoolId(),
                'session_id' => $this->activeSession(),
                'timestamp'  => strtotime(date('Y-m-d')),
            ]);
        } else {
            $category = ExamCategory::where('id', (int) $data['exam_category_id'])
                ->where('school_id', $this->schoolId())->firstOrFail();
        }

        Exam::create([
            'name'             => $data['title'],
            'exam_category_id' => $category->id,
            'exam_type'        => 'offline',
            'room_number'      => $data['room_number'] ?? '',
            'starting_time'    => strtotime($data['starting_at']),
            'ending_time'      => strtotime($data['ending_at']),
            'total_marks'      => $data['total_marks'],
            'status'           => 'pending',
            'class_id'         => $course->class_id,
            'subject_id'       => $course->subject_id,
            'school_id'        => $course->school_id,
            'session_id'       => $this->activeSession(),
        ]);

        return redirect(route('teacher.addons.course.manage', $course->id) . '#tab-cats')
            ->with('message', get_phrase('Sitting CAT scheduled — set its questions and enter marks from the CATs & Exams tab.'));
    }

    /* ================= sitting exam: questions + printable paper + in-place marks ================= */

    /** An offline exam whose class+subject the teacher owns a course for (else 404). */
    private function ownedExam($id)
    {
        $exam = Exam::where('id', $id)->where('exam_type', 'offline')
            ->where('school_id', $this->schoolId())->firstOrFail();
        Course::where('class_id', $exam->class_id)->where('subject_id', $exam->subject_id)
            ->where('teacher_id', auth()->user()->id)->where('school_id', $this->schoolId())->firstOrFail();
        return $exam;
    }

    private function recomputeExamMarks($exam)
    {
        $sum = (int) ExamQuestion::where('exam_id', $exam->id)->sum('marks');
        if ($sum > 0) $exam->update(['total_marks' => $sum]);
    }

    private function examCourse($exam)
    {
        return Course::where('class_id', $exam->class_id)->where('subject_id', $exam->subject_id)
            ->where('teacher_id', auth()->user()->id)->where('school_id', $this->schoolId())->first();
    }

    public function sittingCatQuestions(Request $request, $id)
    {
        $exam = $this->ownedExam($id);

        $attached = ExamQuestion::where('exam_id', $exam->id)->orderBy('sort_order')->get()
            ->map(function ($eq) { $eq->q = Question::find($eq->question_id); return $eq; })
            ->filter(fn ($eq) => $eq->q)->values();

        $subjectIds = $this->sameNameSubjectIds($exam->subject_id);
        $search = trim((string) $request->get('q', ''));
        $bankTotal = Question::where('school_id', $this->schoolId())->whereIn('subject_id', $subjectIds)
            ->whereNotIn('id', $attached->pluck('question_id'))->count();
        $bank = Question::where('school_id', $this->schoolId())->whereIn('subject_id', $subjectIds)
            ->whereNotIn('id', $attached->pluck('question_id'))
            ->when($search !== '', fn ($qq) => $qq->where(function ($w) use ($search) {
                $w->where('question', 'LIKE', "%{$search}%")->orWhere('topic', 'LIKE', "%{$search}%");
            }))
            ->orderByDesc('id')->paginate(8)->appends(['q' => $search]);

        return view('teacher.courses.sitting_cat_questions', [
            'exam' => $exam, 'attached' => $attached, 'bank' => $bank, 'bankTotal' => $bankTotal, 'search' => $search,
            'course' => $this->examCourse($exam),
            'className'   => optional(Classes::find($exam->class_id))->name,
            'subjectName' => optional(Subject::find($exam->subject_id))->name,
            'categoryName'=> optional(ExamCategory::find($exam->exam_category_id))->name,
        ]);
    }

    public function sittingCatQuestionAdd(Request $request)
    {
        $exam = $this->ownedExam($request->exam_id);
        $q = Question::where('id', $request->question_id)->where('school_id', $this->schoolId())->firstOrFail();
        if (!ExamQuestion::where('exam_id', $exam->id)->where('question_id', $q->id)->exists()) {
            ExamQuestion::create([
                'exam_id'     => $exam->id,
                'question_id' => $q->id,
                'marks'       => $q->marks,
                'sort_order'  => (int) ExamQuestion::where('exam_id', $exam->id)->max('sort_order') + 1,
            ]);
            $this->recomputeExamMarks($exam);
        }
        return redirect()->route('teacher.addons.course.sitting_cat.questions', $exam->id)
            ->with('message', get_phrase('Question added to the exam paper.'));
    }

    public function sittingCatQuestionRemove(Request $request)
    {
        $exam = $this->ownedExam($request->exam_id);
        ExamQuestion::where('exam_id', $exam->id)->where('question_id', $request->question_id)->delete();
        $this->recomputeExamMarks($exam);
        return redirect()->route('teacher.addons.course.sitting_cat.questions', $exam->id)
            ->with('message', get_phrase('Question removed from the exam paper.'));
    }

    public function sittingCatPaper(Request $request, $id)
    {
        $exam = $this->ownedExam($id);
        $attached = ExamQuestion::where('exam_id', $exam->id)->orderBy('sort_order')->get()
            ->map(function ($eq) { $eq->q = Question::find($eq->question_id); return $eq; })
            ->filter(fn ($eq) => $eq->q)->values();

        $school   = \DB::table('schools')->where('id', $this->schoolId())->first();
        $logoFile = get_settings('dark_logo');
        $logoUrl  = ($logoFile && file_exists(public_path('assets/uploads/logo/' . $logoFile)))
            ? asset('assets/uploads/logo/' . $logoFile) : null;

        return view('teacher.courses.sitting_cat_paper', [
            'exam' => $exam, 'attached' => $attached, 'school' => $school, 'logoUrl' => $logoUrl,
            'answers' => (bool) $request->get('answers'),
            'className'   => optional(Classes::find($exam->class_id))->name,
            'subjectName' => optional(Subject::find($exam->subject_id))->name,
        ]);
    }

    public function sittingCatMarks($id)
    {
        $exam = $this->ownedExam($id);

        // roster: every student in the exam's class (all sections), with section name
        $enrollments = Enrollment::where('class_id', $exam->class_id)->where('school_id', $exam->school_id)->get();
        $sectionNames  = Section::whereIn('id', $enrollments->pluck('section_id'))->pluck('name', 'id');
        $sectionByUser = $enrollments->pluck('section_id', 'user_id');
        $students = User::whereIn('id', $enrollments->pluck('user_id'))->where('role_id', 7)->orderBy('name')->get()
            ->map(function ($s) use ($sectionByUser, $sectionNames) {
                $s->section_id   = $sectionByUser[$s->id] ?? null;
                $s->section_name = $sectionNames[$s->section_id] ?? '-';
                return $s;
            });

        // prefill existing gradebook marks for this exam-category + subject
        $gradebooks = Gradebook::where('exam_category_id', $exam->exam_category_id)
            ->where('class_id', $exam->class_id)->where('session_id', $exam->session_id)
            ->where('school_id', $exam->school_id)->get()->keyBy('student_id');
        $existing = [];
        foreach ($gradebooks as $sid => $g) {
            $marks = json_decode($g->marks, true) ?: [];
            if (isset($marks[$exam->subject_id])) $existing[$sid] = $marks[$exam->subject_id];
        }

        return view('teacher.courses.sitting_cat_marks', [
            'exam' => $exam, 'students' => $students, 'existing' => $existing,
            'course' => $this->examCourse($exam),
            'className'   => optional(Classes::find($exam->class_id))->name,
            'subjectName' => optional(Subject::find($exam->subject_id))->name,
            'categoryName'=> optional(ExamCategory::find($exam->exam_category_id))->name,
        ]);
    }

    public function sittingCatMarksSave(Request $request)
    {
        $exam    = $this->ownedExam($request->exam_id);
        $scores  = $request->score ?? [];      // [student_id => mark]
        $max     = (int) $exam->total_marks;
        $saved   = 0;

        foreach ($scores as $student_id => $val) {
            if ($val === '' || $val === null) continue;              // skip blanks
            $mark = max(0, min((float) $val, $max));                 // clamp 0..total

            $enroll = Enrollment::where('user_id', $student_id)->where('class_id', $exam->class_id)
                ->where('school_id', $exam->school_id)->first();
            if (!$enroll) continue;

            // upsert the student's gradebook row (feeds report cards / transcripts)
            $g = Gradebook::where('exam_category_id', $exam->exam_category_id)
                ->where('class_id', $exam->class_id)->where('section_id', $enroll->section_id)
                ->where('student_id', $student_id)->where('school_id', $exam->school_id)
                ->where('session_id', $exam->session_id)->first();

            if ($g) {
                $marks = json_decode($g->marks, true) ?: [];
                $marks[$exam->subject_id] = $mark;
                $g->update(['marks' => json_encode($marks)]);
            } else {
                Gradebook::create([
                    'class_id'         => $exam->class_id,
                    'section_id'       => $enroll->section_id,
                    'student_id'       => $student_id,
                    'exam_category_id' => $exam->exam_category_id,
                    'marks'            => json_encode([$exam->subject_id => $mark]),
                    'comment'          => '',
                    'school_id'        => $exam->school_id,
                    'session_id'       => $exam->session_id,
                    'timestamp'        => strtotime(date('Y-m-d')),
                ]);
            }
            $saved++;
        }

        return redirect()->route('teacher.addons.course.sitting_cat.marks', $exam->id)
            ->with('message', $saved . ' ' . get_phrase('student mark(s) saved — they now appear on report cards & transcripts.'));
    }

    /* ================= course daily attendance (students on the course = the class) ================= */

    public function courseAttendance(Request $request, $id)
    {
        $course = $this->ownedCourse($id);

        // date (app tz midnight); default today
        $dateStr = $request->date ?: date('Y-m-d');
        $ts = strtotime(date('Y-m-d', strtotime($dateStr)));

        // roster: every student on the course's class, with their section
        $enrollments = Enrollment::where('class_id', $course->class_id)->where('school_id', $course->school_id)->get();
        $sectionNames  = Section::whereIn('id', $enrollments->pluck('section_id'))->pluck('name', 'id');
        $sectionByUser = $enrollments->pluck('section_id', 'user_id');
        $students = User::whereIn('id', $enrollments->pluck('user_id'))->where('role_id', 7)->orderBy('name')->get()
            ->map(function ($s) use ($sectionByUser, $sectionNames) {
                $s->section_id   = $sectionByUser[$s->id] ?? null;
                $s->section_name = $sectionNames[$s->section_id] ?? '-';
                return $s;
            });

        // existing marks for that day
        $marks = DailyAttendances::where('class_id', $course->class_id)->where('timestamp', $ts)
            ->where('school_id', $course->school_id)->get()->keyBy('student_id');
        $existing = $marks->map(fn ($m) => (int) $m->status);        // student_id => status(1/0)
        $anyMarked = $marks->count() > 0;

        return view('teacher.courses.attendance', [
            'course' => $course, 'students' => $students, 'existing' => $existing,
            'date' => date('Y-m-d', $ts), 'anyMarked' => $anyMarked,
            'className'   => optional(Classes::find($course->class_id))->name,
            'subjectName' => optional(Subject::find($course->subject_id))->name,
        ]);
    }

    public function courseAttendanceSave(Request $request)
    {
        $course  = $this->ownedCourse($request->course_id);
        $ts      = strtotime(date('Y-m-d', strtotime($request->date ?: date('Y-m-d'))));
        $session = $this->activeSession();
        $saved   = 0;

        foreach (($request->student_id ?? []) as $sid) {
            $enroll = Enrollment::where('user_id', $sid)->where('class_id', $course->class_id)
                ->where('school_id', $course->school_id)->first();
            if (!$enroll) continue;
            DailyAttendances::updateOrCreate(
                ['timestamp' => $ts, 'class_id' => $course->class_id, 'section_id' => $enroll->section_id,
                 'session_id' => $session, 'school_id' => $course->school_id, 'student_id' => $sid],
                ['status' => (int) $request->input('status-' . $sid, 0)]
            );
            $saved++;
        }

        return redirect()->route('teacher.addons.course.attendance', ['id' => $course->id, 'date' => date('Y-m-d', $ts)])
            ->with('message', $saved . ' ' . get_phrase('attendance record(s) saved for') . ' ' . date('d M Y', $ts) . '.');
    }

    /* ---- teacher preview: see the course exactly as a student does ---- */
    public function teacherPreview($id)
    {
        $course = $this->ownedCourse($id);
        $course->load('topics.lessons.materials', 'sessions');

        $syllabus = Syllabus::where('class_id', $course->class_id)
            ->where('subject_id', $course->subject_id)
            ->where('school_id', $this->schoolId())->get();
        // preview shows published work across ALL sections of the class
        $assignments = Assignment::where('class_id', $course->class_id)
            ->where('subject_id', $course->subject_id)
            ->where('school_id', $this->schoolId())
            ->where('status', 'published')->get();

        $sittingExams = Exam::where('exam_type', 'offline')
            ->where('class_id', $course->class_id)
            ->where('subject_id', $course->subject_id)
            ->where('school_id', $this->schoolId())
            ->where('ending_time', '>=', time())
            ->orderBy('starting_time')->get();

        $now      = now();
        $upcoming = $course->sessions->filter(fn ($x) => $x->status === 'scheduled'
            && $x->session_date && $x->session_date->copy()->addMinutes((int) $x->duration_minutes)->gte($now))->values();
        $past = $course->sessions->filter(fn ($x) => !($x->status === 'scheduled'
            && $x->session_date && $x->session_date->copy()->addMinutes((int) $x->duration_minutes)->gte($now)))
            ->sortByDesc('session_date')->values();

        $results = collect();
        return view('student.courses.view', compact('course', 'syllabus', 'assignments', 'sittingExams', 'upcoming', 'past', 'results'))
            ->with('preview', true);
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

        // upcoming sitting CATs / physical exams for this class+subject
        $sittingExams = Exam::where('exam_type', 'offline')
            ->where('class_id', $course->class_id)
            ->where('subject_id', $course->subject_id)
            ->where('school_id', $this->schoolId())
            ->where('ending_time', '>=', time())
            ->orderBy('starting_time')->get();

        // Live sessions: upcoming (still joinable) vs past.
        $now      = now();
        $upcoming = $course->sessions->filter(fn ($x) => $x->status === 'scheduled'
            && $x->session_date && $x->session_date->copy()->addMinutes((int) $x->duration_minutes)->gte($now))->values();
        $past = $course->sessions->filter(fn ($x) => !($x->status === 'scheduled'
            && $x->session_date && $x->session_date->copy()->addMinutes((int) $x->duration_minutes)->gte($now)))
            ->sortByDesc('session_date')->values();

        $results = $this->studentCourseResults($course, $enroll, auth()->user()->id);

        return view('student.courses.view', compact('course', 'syllabus', 'assignments', 'sittingExams', 'upcoming', 'past', 'results'));
    }

    /** A student's own results for a course: online CAT scores + sitting exam gradebook marks. */
    private function studentCourseResults($course, $enroll, $student_id)
    {
        $results = collect();

        // online CATs (auto-graded quizzes) the student has sat
        $quizzes = Assignment::where('class_id', $course->class_id)
            ->where('section_id', $enroll->section_id)->where('subject_id', $course->subject_id)
            ->where('school_id', $this->schoolId())->where('is_quiz', 1)->get()->keyBy('id');
        if ($quizzes->isNotEmpty()) {
            $subs = AssignmentSubmission::where('student_id', $student_id)
                ->whereIn('assignment_id', $quizzes->keys())->get();
            foreach ($subs as $sub) {
                $a = $quizzes[$sub->assignment_id] ?? null;
                $results->push([
                    'title'  => $a ? $a->title : 'CAT',
                    'type'   => 'Online CAT',
                    'score'  => $sub->obtained_marks,
                    'total'  => $a ? (int) $a->total_marks : null,
                    'status' => $sub->status === 'returned' ? 'graded' : 'pending',
                    'date'   => $sub->graded_at ? date('d M Y', (int) $sub->graded_at) : null,
                ]);
            }
        }

        // sitting exams — marks live in the gradebook keyed by exam_category + subject
        $exams = Exam::where('exam_type', 'offline')->where('class_id', $course->class_id)
            ->where('subject_id', $course->subject_id)->where('school_id', $this->schoolId())
            ->where('session_id', $this->activeSession())->orderByDesc('starting_time')->get();
        if ($exams->isNotEmpty()) {
            $gradebooks = Gradebook::where('student_id', $student_id)->where('class_id', $course->class_id)
                ->where('section_id', $enroll->section_id)->where('school_id', $this->schoolId())
                ->where('session_id', $this->activeSession())->get()->keyBy('exam_category_id');
            foreach ($exams as $ex) {
                $g = $gradebooks[$ex->exam_category_id] ?? null;
                if (!$g) continue;
                $marks = json_decode($g->marks, true) ?: [];
                if (!isset($marks[$course->subject_id])) continue;
                $results->push([
                    'title'  => $ex->name,
                    'type'   => 'Sitting exam',
                    'score'  => $marks[$course->subject_id],
                    'total'  => (int) $ex->total_marks,
                    'status' => 'graded',
                    'date'   => $ex->starting_time ? date('d M Y', (int) $ex->starting_time) : null,
                ]);
            }
        }

        return $results;
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
