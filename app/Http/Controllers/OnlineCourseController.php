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
        $course->load('topics.lessons.materials');
        return view('teacher.courses.manage', compact('course'));
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
        $courses = Course::where('class_id', $enroll->class_id)
            ->where('school_id', $this->schoolId())
            ->where('status', 'published')
            ->orderByDesc('id')->paginate(9);

        return view('student.courses.index', compact('courses'));
    }

    public function studentView($id)
    {
        $enroll = $this->studentEnrollment();
        $course = Course::where('id', $id)->where('status', 'published')->firstOrFail();
        abort_if(!$enroll || $enroll->class_id != $course->class_id, 403);

        $course->load('topics.lessons.materials');

        // tie-in: syllabus + assignments for this class+subject (student's section)
        $syllabus = Syllabus::where('class_id', $course->class_id)
            ->where('subject_id', $course->subject_id)
            ->where('school_id', $this->schoolId())->get();
        $assignments = Assignment::where('class_id', $course->class_id)
            ->where('section_id', $enroll->section_id)
            ->where('subject_id', $course->subject_id)
            ->where('school_id', $this->schoolId())
            ->where('status', 'published')->get();

        return view('student.courses.view', compact('course', 'syllabus', 'assignments'));
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
