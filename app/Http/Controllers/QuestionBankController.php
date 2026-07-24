<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question;
use App\Models\Assignment;
use App\Models\AssignmentQuestion;
use App\Models\AssignmentAnswer;
use App\Models\AssignmentSubmission;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\TeacherPermission;
use App\Models\User;

class QuestionBankController extends Controller
{
    private function schoolId()
    {
        return auth()->user()->school_id;
    }

    private function activeSession()
    {
        return get_school_settings($this->schoolId())->value('running_session');
    }

    private function permittedClasses()
    {
        $ids = TeacherPermission::where('teacher_id', auth()->user()->id)
            ->where('school_id', $this->schoolId())->distinct()->pluck('class_id')->toArray();
        return Classes::whereIn('id', $ids)->get();
    }

    /* ===================================================================== BANK CRUD */

    public function index(Request $request)
    {
        $subject_id = $request->subject_id ?? '';
        $type       = $request->type ?? '';
        $difficulty = $request->difficulty ?? '';
        $search     = $request->search ?? '';

        $questions = Question::where('school_id', $this->schoolId())
            ->when($subject_id !== '', fn($q) => $q->where('subject_id', $subject_id))
            ->when($type !== '', fn($q) => $q->where('type', $type))
            ->when($difficulty !== '', fn($q) => $q->where('difficulty', $difficulty))
            ->when($search !== '', fn($q) => $q->where('question', 'LIKE', "%{$search}%"))
            ->orderByDesc('id')->paginate(12)->appends($request->query());

        $subjects = Subject::where('school_id', $this->schoolId())->get();

        return view('teacher.question_bank.index', compact('questions', 'subjects', 'subject_id', 'type', 'difficulty', 'search'));
    }

    public function createModal(Request $request)
    {
        $classes = $this->permittedClasses();
        // optional: create a question directly INTO an online CAT (attach after saving)
        $attachQuiz = null;
        if ($request->attach_quiz_id) {
            $attachQuiz = $this->ownedQuiz($request->attach_quiz_id);
        }
        return view('teacher.question_bank.form', ['classes' => $classes, 'question' => null, 'attachQuiz' => $attachQuiz]);
    }

    public function classSubjects(Request $request)
    {
        $subjects = Subject::where('class_id', $request->class_id)->where('school_id', $this->schoolId())->get();
        $options = '<option value="">' . get_phrase('Select a subject') . '</option>';
        foreach ($subjects as $s) $options .= '<option value="' . $s->id . '">' . $s->name . '</option>';
        echo $options;
    }

    private function buildQuestionData(Request $request)
    {
        $type = $request->type;
        $options = null;
        $correct = null;

        if ($type === 'mcq') {
            $options = array_values(array_filter($request->options ?? [], fn($o) => trim((string)$o) !== ''));
            $correct = (string) $request->correct_option; // index
        } elseif ($type === 'truefalse') {
            $options = ['True', 'False'];
            $correct = $request->correct_tf; // 'true' | 'false'
        } elseif ($type === 'short') {
            $correct = $request->expected_answer; // optional
        }
        // essay: no options/correct

        return [
            'type'           => $type,
            'question'       => $request->question,
            'options'        => $options,
            'correct_answer' => $correct,
            'marks'          => (int) ($request->marks ?: 1),
            'difficulty'     => $request->difficulty ?: 'medium',
            'topic'          => $request->topic,
        ];
    }

    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required|string',
            'type'     => 'required|in:mcq,truefalse,short,essay',
            'class_id' => 'required',
            'subject_id' => 'required',
        ]);

        $question = Question::create(array_merge($this->buildQuestionData($request), [
            'school_id'  => $this->schoolId(),
            'teacher_id' => auth()->user()->id,
            'class_id'   => $request->class_id,
            'subject_id' => $request->subject_id,
        ]));

        // created from inside an online CAT → attach it there too
        if ($request->attach_quiz_id) {
            $quiz = $this->ownedQuiz($request->attach_quiz_id);
            abort_if($this->quizLocked($quiz), 403, get_phrase('Students have already submitted — the paper is locked.'));
            AssignmentQuestion::create([
                'assignment_id' => $quiz->id,
                'question_id'   => $question->id,
                'marks'         => $question->marks,
                'sort_order'    => (int) AssignmentQuestion::where('assignment_id', $quiz->id)->max('sort_order') + 1,
            ]);
            $this->recomputeQuizMarks($quiz);
            return redirect()->route('teacher.quiz.questions', $quiz->id)
                ->with('message', get_phrase('Question created and added to the CAT.'));
        }

        return redirect()->back()->with('message', get_phrase('Question added to the bank.'));
    }

    public function editModal($id)
    {
        $question = Question::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $classes = $this->permittedClasses();
        return view('teacher.question_bank.form', compact('question', 'classes'));
    }

    public function update(Request $request, $id)
    {
        $question = Question::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $request->validate(['question' => 'required|string', 'type' => 'required|in:mcq,truefalse,short,essay']);
        $question->update(array_merge($this->buildQuestionData($request), [
            'class_id'   => $request->class_id,
            'subject_id' => $request->subject_id,
        ]));
        return redirect()->route('teacher.qbank')->with('message', get_phrase('Question updated.'));
    }

    public function delete($id)
    {
        $question = Question::where('id', $id)->where('school_id', $this->schoolId())->firstOrFail();
        $question->delete();
        return redirect()->back()->with('message', get_phrase('Question deleted.'));
    }

    /* ===================================================================== QUIZ GENERATION */

    public function generateModal()
    {
        $classes = $this->permittedClasses();
        return view('teacher.question_bank.generate', compact('classes'));
    }

    public function classSubjectsSections(Request $request)
    {
        // sections for a class the teacher is permitted on
        $sectionIds = TeacherPermission::where('teacher_id', auth()->user()->id)
            ->where('class_id', $request->class_id)->distinct()->pluck('section_id')->toArray();
        $sections = Section::whereIn('id', $sectionIds)->get();
        $out = '<option value="">' . get_phrase('Select a section') . '</option>';
        foreach ($sections as $s) $out .= '<option value="' . $s->id . '">' . $s->name . '</option>';
        echo $out;
    }

    public function generateQuiz(Request $request)
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'class_id'   => 'required',
            'section_id' => 'required',
            'subject_id' => 'required',
            'count'      => 'required|integer|min:1',
        ]);

        $pool = Question::where('school_id', $this->schoolId())
            ->whereIn('subject_id', $this->sameNameSubjectIds($request->subject_id))
            ->when($request->difficulty, fn($q) => $q->where('difficulty', $request->difficulty))
            ->when($request->topic, fn($q) => $q->where('topic', 'LIKE', "%{$request->topic}%"))
            ->when($request->qtype, fn($q) => $q->where('type', $request->qtype))
            ->inRandomOrder()->limit((int) $request->count)->get();

        if ($pool->count() === 0) {
            return redirect()->back()->with('error', get_phrase('No questions match those filters. Add questions first.'));
        }

        $total = $pool->sum('marks');

        $assignment = Assignment::create([
            'school_id'   => $this->schoolId(),
            'session_id'  => $this->activeSession(),
            'teacher_id'  => auth()->user()->id,
            'class_id'    => $request->class_id,
            'section_id'  => $request->section_id,
            'subject_id'  => $request->subject_id,
            'title'       => $request->title,
            'description' => $request->description ?: 'Online quiz — answer all questions.',
            'total_marks' => $total,
            'deadline'    => $request->deadline ? strtotime($request->deadline) : null,
            'duration_minutes' => $request->duration_minutes ? (int) $request->duration_minutes : null,
            'status'      => 'published',
            'is_quiz'     => 1,
        ]);

        $so = 1;
        foreach ($pool as $q) {
            AssignmentQuestion::create([
                'assignment_id' => $assignment->id,
                'question_id'   => $q->id,
                'marks'         => $q->marks,
                'sort_order'    => $so++,
            ]);
        }

        return redirect()->route('teacher.quiz.review', $assignment->id)
            ->with('message', get_phrase('Quiz generated with ') . $pool->count() . get_phrase(' questions.'));
    }

    /* ===================================================================== ONLINE CATS (quiz list) */

    public function quizzes()
    {
        $sid = $this->schoolId();

        $quizzes = Assignment::where('school_id', $sid)
            ->where('teacher_id', auth()->user()->id)
            ->where('is_quiz', 1)
            ->orderByDesc('id')->get();

        $ids = $quizzes->pluck('id');
        $subCounts = AssignmentSubmission::whereIn('assignment_id', $ids)
            ->selectRaw('assignment_id, COUNT(*) c')->groupBy('assignment_id')->pluck('c', 'assignment_id');
        $qCounts = AssignmentQuestion::whereIn('assignment_id', $ids)
            ->selectRaw('assignment_id, COUNT(*) c')->groupBy('assignment_id')->pluck('c', 'assignment_id');

        $classNames   = Classes::whereIn('id', $quizzes->pluck('class_id')->unique())->pluck('name', 'id');
        $sectionNames = Section::whereIn('id', $quizzes->pluck('section_id')->unique())->pluck('name', 'id');
        $subjectNames = Subject::whereIn('id', $quizzes->pluck('subject_id')->unique())->pluck('name', 'id');

        return view('teacher.question_bank.quizzes', compact(
            'quizzes', 'subCounts', 'qCounts', 'classNames', 'sectionNames', 'subjectNames'
        ));
    }

    /* ===================================================================== CAT QUESTION MANAGEMENT */

    private function ownedQuiz($id)
    {
        return Assignment::where('id', $id)->where('is_quiz', 1)
            ->where('teacher_id', auth()->user()->id)
            ->where('school_id', $this->schoolId())->firstOrFail();
    }

    /**
     * All subject ids in this school that share the given subject's name.
     * A subject like "Anatomy" exists once per class (distinct ids) — the
     * question bank is treated as shared across them so questions are reusable.
     */
    private function sameNameSubjectIds($subject_id)
    {
        $name = Subject::where('id', $subject_id)->value('name');
        if (!$name) return [$subject_id];
        return Subject::where('school_id', $this->schoolId())
            ->where('name', $name)->pluck('id')->all();
    }

    // Once anyone has submitted, the paper must not change (grading would corrupt).
    private function quizLocked($quiz)
    {
        return AssignmentSubmission::where('assignment_id', $quiz->id)->exists();
    }

    private function recomputeQuizMarks($quiz)
    {
        $quiz->update(['total_marks' => (int) AssignmentQuestion::where('assignment_id', $quiz->id)->sum('marks')]);
    }

    /** Manage the questions inside one online CAT: list, add from bank, remove, create new. */
    public function quizQuestions(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($id);
        $locked = $this->quizLocked($quiz);

        $attached = AssignmentQuestion::where('assignment_id', $quiz->id)
            ->orderBy('sort_order')->get()
            ->map(function ($aq) {
                $aq->q = Question::find($aq->question_id);
                return $aq;
            })->filter(fn ($aq) => $aq->q)->values();

        // The bank is reusable across classes: pull from EVERY subject that shares
        // this subject's name (e.g. "Anatomy" exists once per class), not just the
        // exact subject_id — otherwise a CAT for one class can't see questions
        // written under the same subject in another class.
        $subjectName = optional(Subject::find($quiz->subject_id))->name;
        $subjectIds  = $this->sameNameSubjectIds($quiz->subject_id);

        // bank questions not already on the paper (paginated)
        $search = trim((string) $request->get('q', ''));
        $bankTotal = Question::where('school_id', $this->schoolId())
            ->whereIn('subject_id', $subjectIds)
            ->whereNotIn('id', $attached->pluck('question_id'))->count();
        $bank = Question::where('school_id', $this->schoolId())
            ->whereIn('subject_id', $subjectIds)
            ->whereNotIn('id', $attached->pluck('question_id'))
            ->when($search !== '', fn ($qq) => $qq->where(function ($w) use ($search) {
                $w->where('question', 'LIKE', "%{$search}%")->orWhere('topic', 'LIKE', "%{$search}%");
            }))
            ->orderByDesc('id')->paginate(8)->appends(['q' => $search]);

        $className   = optional(Classes::find($quiz->class_id))->name;
        $sectionName = optional(Section::find($quiz->section_id))->name;
        $submissionCount = AssignmentSubmission::where('assignment_id', $quiz->id)->count();

        // the course this CAT belongs to (class+subject+teacher), for the Back button
        $course = \App\Models\Course::where('class_id', $quiz->class_id)
            ->where('subject_id', $quiz->subject_id)
            ->where('teacher_id', auth()->user()->id)
            ->where('school_id', $this->schoolId())->first();

        return view('teacher.question_bank.quiz_questions', compact(
            'quiz', 'attached', 'bank', 'bankTotal', 'locked', 'search', 'course',
            'className', 'sectionName', 'subjectName', 'submissionCount'
        ));
    }

    public function quizQuestionAdd(Request $request)
    {
        $quiz = $this->ownedQuiz($request->assignment_id);
        abort_if($this->quizLocked($quiz), 403, get_phrase('Students have already submitted — the paper is locked.'));

        $question = Question::where('id', $request->question_id)
            ->where('school_id', $this->schoolId())->firstOrFail();

        $exists = AssignmentQuestion::where('assignment_id', $quiz->id)
            ->where('question_id', $question->id)->exists();
        if (!$exists) {
            AssignmentQuestion::create([
                'assignment_id' => $quiz->id,
                'question_id'   => $question->id,
                'marks'         => $question->marks,
                'sort_order'    => (int) AssignmentQuestion::where('assignment_id', $quiz->id)->max('sort_order') + 1,
            ]);
            $this->recomputeQuizMarks($quiz);
        }

        return redirect()->route('teacher.quiz.questions', $quiz->id)
            ->with('message', get_phrase('Question added to the CAT.'));
    }

    /** Printable exam paper: the CAT as students sit it (?answers=1 adds the marking key). */
    public function quizPaper(Request $request, $id)
    {
        $quiz = $this->ownedQuiz($id);

        $attached = AssignmentQuestion::where('assignment_id', $quiz->id)
            ->orderBy('sort_order')->get()
            ->map(function ($aq) {
                $aq->q = Question::find($aq->question_id);
                return $aq;
            })->filter(fn ($aq) => $aq->q)->values();

        $school   = \DB::table('schools')->where('id', $this->schoolId())->first();
        $logoFile = get_settings('dark_logo');
        $logoUrl  = ($logoFile && file_exists(public_path('assets/uploads/logo/' . $logoFile)))
            ? asset('assets/uploads/logo/' . $logoFile) : null;

        return view('teacher.question_bank.quiz_paper', [
            'quiz'        => $quiz,
            'attached'    => $attached,
            'school'      => $school,
            'logoUrl'     => $logoUrl,
            'answers'     => (bool) $request->get('answers'),
            'className'   => optional(Classes::find($quiz->class_id))->name,
            'sectionName' => optional(Section::find($quiz->section_id))->name,
            'subjectName' => optional(Subject::find($quiz->subject_id))->name,
        ]);
    }

    public function quizQuestionRemove(Request $request)
    {
        $quiz = $this->ownedQuiz($request->assignment_id);
        abort_if($this->quizLocked($quiz), 403, get_phrase('Students have already submitted — the paper is locked.'));

        AssignmentQuestion::where('assignment_id', $quiz->id)
            ->where('question_id', $request->question_id)->delete();
        $this->recomputeQuizMarks($quiz);

        return redirect()->route('teacher.quiz.questions', $quiz->id)
            ->with('message', get_phrase('Question removed from the CAT.'));
    }

    /* ===================================================================== STUDENT: TAKE */

    private function studentEnrollment()
    {
        return Enrollment::where('user_id', auth()->user()->id)
            ->where('school_id', $this->schoolId())->latest()->first();
    }

    public function studentTake($id)
    {
        $quiz = Assignment::where('id', $id)->where('is_quiz', 1)->where('status', 'published')->firstOrFail();
        $enroll = $this->studentEnrollment();
        abort_if(!$enroll || $enroll->class_id != $quiz->class_id || $enroll->section_id != $quiz->section_id, 403);

        $links = AssignmentQuestion::where('assignment_id', $id)->orderBy('sort_order')->with('question')->get();
        $submission = AssignmentSubmission::where('assignment_id', $id)->where('student_id', auth()->user()->id)->first();
        $answers = AssignmentAnswer::where('assignment_id', $id)->where('student_id', auth()->user()->id)->get()->keyBy('question_id');

        $now = time();
        $closed = false;   // deadline passed and student never started/submitted
        $remaining = null; // seconds left (min of timer window and deadline), null = untimed

        if (!$submission) {
            $pastDeadline = $quiz->deadline && $now > $quiz->deadline;
            $hasAttempt = QuizAttempt::where('assignment_id', $id)->where('student_id', auth()->user()->id)->exists();

            // Deadline is a hard close: can't start a fresh attempt after it passes.
            if ($pastDeadline && !$hasAttempt) {
                $closed = true;
            } else {
                // Anchor a per-student start time so the timer survives refresh.
                if ($quiz->duration_minutes) {
                    $attempt = QuizAttempt::firstOrCreate(
                        ['assignment_id' => $id, 'student_id' => auth()->user()->id],
                        ['school_id' => $quiz->school_id, 'started_at' => $now]
                    );
                    $remaining = ($quiz->duration_minutes * 60) - ($now - $attempt->started_at);
                }
                // Whichever comes first — timer expiry or the exam deadline.
                if ($quiz->deadline) {
                    $tilDeadline = $quiz->deadline - $now;
                    $remaining = $remaining === null ? $tilDeadline : min($remaining, $tilDeadline);
                }
                // Time's already up on load → finalize with whatever was saved (nothing) so it's graded.
                if ($remaining !== null && $remaining <= 0) {
                    $submission = $this->finalizeQuiz($quiz, []);
                    $remaining = null;
                }
            }
        }

        return view('student.assignments.quiz', compact('quiz', 'links', 'submission', 'answers', 'closed', 'remaining'));
    }

    public function studentSubmit(Request $request, $id)
    {
        $quiz = Assignment::where('id', $id)->where('is_quiz', 1)->where('status', 'published')->firstOrFail();
        $enroll = $this->studentEnrollment();
        // Enrollment must match BOTH class and section (H12: was class-only, allowing cross-section submits).
        abort_if(!$enroll || $enroll->class_id != $quiz->class_id || $enroll->section_id != $quiz->section_id, 403);

        $existing = AssignmentSubmission::where('assignment_id', $id)->where('student_id', auth()->user()->id)->first();
        if ($existing) {
            return redirect()->route('student.quiz.take', $id)->with('error', get_phrase('You have already submitted this quiz.'));
        }

        // C8: enforce deadline + timer server-side (studentTake/JS alone were bypassable via a direct POST).
        $now = time();
        if ($quiz->deadline && $now > $quiz->deadline) {
            return redirect()->route('student.quiz.take', $id)->with('error', get_phrase('This exam is closed — the deadline has passed.'));
        }
        if ($quiz->duration_minutes) {
            $attempt = QuizAttempt::where('assignment_id', $id)->where('student_id', auth()->user()->id)->first();
            // Window elapsed (30s grace): finalize as expired, ignoring late answers — prevents timer bypass.
            if ($attempt && $now > $attempt->started_at + ($quiz->duration_minutes * 60) + 30) {
                $this->finalizeQuiz($quiz, []);
                return redirect()->route('student.quiz.take', $id)->with('error', get_phrase('Time is up — your quiz was auto-submitted.'));
            }
        }

        $submission = $this->finalizeQuiz($quiz, $request->answers ?? []);
        $needsManual = ($submission->status === 'submitted');

        return redirect()->route('student.quiz.take', $id)->with('message',
            $needsManual ? get_phrase('Quiz submitted. Some answers await teacher review.')
                         : get_phrase('Quiz submitted and auto-graded.'));
    }

    /**
     * Grade a set of responses, persist the submission + per-question answers, return the submission.
     * Reused by studentSubmit (normal) and studentTake (auto-submit when the timer/deadline expires).
     * $responses = [question_id => value]
     */
    private function finalizeQuiz($quiz, array $responses)
    {
        $id = $quiz->id;
        $links = AssignmentQuestion::where('assignment_id', $id)->with('question')->get();

        $autoTotal = 0;
        $needsManual = false;
        $rows = [];

        foreach ($links as $link) {
            $q = $link->question;
            if (!$q) continue;
            $ans = $responses[$q->id] ?? null;
            $isCorrect = null;
            $awarded = null;

            if ($q->type === 'mcq') {
                $isCorrect = ((string)$ans === (string)$q->correct_answer) ? 1 : 0;
                $awarded = $isCorrect ? $link->marks : 0;
                $autoTotal += $awarded;
            } elseif ($q->type === 'truefalse') {
                $isCorrect = (strtolower((string)$ans) === strtolower((string)$q->correct_answer)) ? 1 : 0;
                $awarded = $isCorrect ? $link->marks : 0;
                $autoTotal += $awarded;
            } elseif ($q->type === 'short' && trim((string)$q->correct_answer) !== '') {
                $isCorrect = (strtolower(trim((string)$ans)) === strtolower(trim((string)$q->correct_answer))) ? 1 : 0;
                $awarded = $isCorrect ? $link->marks : 0;
                $autoTotal += $awarded;
            } else {
                // short without key, or essay -> manual
                $needsManual = true;
            }

            $rows[] = [
                'assignment_id' => $id,
                'student_id'    => auth()->user()->id,
                'question_id'   => $q->id,
                'answer'        => is_array($ans) ? json_encode($ans) : $ans,
                'is_correct'    => $isCorrect,
                'awarded_marks' => $awarded,
            ];
        }

        $submission = AssignmentSubmission::create([
            'assignment_id'   => $id,
            'student_id'      => auth()->user()->id,
            'school_id'       => $quiz->school_id,
            'submission_text' => null,
            'submitted_at'    => time(),
            'status'          => $needsManual ? 'submitted' : 'returned',
            'obtained_marks'  => $autoTotal,
            'feedback'        => $needsManual ? null : 'Auto-graded.',
            'graded_at'       => $needsManual ? null : time(),
        ]);

        foreach ($rows as $r) {
            $r['submission_id'] = $submission->id;
            AssignmentAnswer::create($r);
        }

        return $submission;
    }

    /* ===================================================================== TEACHER: REVIEW / GRADE */

    public function teacherReview($id)
    {
        $quiz = $this->ownedQuiz($id);
        $links = AssignmentQuestion::where('assignment_id', $id)->orderBy('sort_order')->with('question')->get();

        $student_ids = Enrollment::where('class_id', $quiz->class_id)->where('section_id', $quiz->section_id)
            ->where('school_id', $quiz->school_id)->pluck('user_id');
        $students = User::whereIn('id', $student_ids)->where('role_id', 7)->orderBy('name')->get();
        $submissions = AssignmentSubmission::where('assignment_id', $id)->get()->keyBy('student_id');

        return view('teacher.assignments.quiz_review', compact('quiz', 'links', 'students', 'submissions'));
    }

    public function teacherGradeView($submission_id)
    {
        $submission = AssignmentSubmission::findOrFail($submission_id);
        $quiz = $this->ownedQuiz($submission->assignment_id);
        $links = AssignmentQuestion::where('assignment_id', $quiz->id)->orderBy('sort_order')->with('question')->get();
        $answers = AssignmentAnswer::where('submission_id', $submission_id)->get()->keyBy('question_id');
        $student = User::find($submission->student_id);

        return view('teacher.assignments.quiz_grade', compact('quiz', 'links', 'answers', 'submission', 'student'));
    }

    public function teacherGrade(Request $request, $submission_id)
    {
        $submission = AssignmentSubmission::findOrFail($submission_id);
        $quiz = $this->ownedQuiz($submission->assignment_id);

        $manual = $request->awarded ?? []; // [question_id => marks]
        // M4: clamp each awarded mark to [0, that question's max] so a bad/tampered value can't exceed total.
        $maxMap = AssignmentQuestion::where('assignment_id', $quiz->id)->pluck('marks', 'question_id');
        foreach ($manual as $qid => $marks) {
            $max = (float) ($maxMap[$qid] ?? 0);
            $awarded = max(0, min((float) $marks, $max));
            AssignmentAnswer::where('submission_id', $submission_id)->where('question_id', $qid)
                ->update(['awarded_marks' => $awarded, 'is_correct' => $awarded > 0 ? 1 : 0]);
        }

        $total = (int) AssignmentAnswer::where('submission_id', $submission_id)->sum('awarded_marks');
        $submission->update([
            'obtained_marks' => $total,
            'status'         => 'returned',
            'feedback'       => $request->feedback ?: $submission->feedback,
            'graded_at'      => time(),
        ]);

        return redirect()->route('teacher.quiz.review', $quiz->id)
            ->with('message', get_phrase('Quiz graded and returned.'));
    }
}
