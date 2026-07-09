<?php

namespace App\Http\Controllers;

use App\Http\Controllers\CommonController;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Session;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\Gradebook;
use App\Models\Grade;
use App\Models\ClassList;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\DailyAttendances;
use App\Models\Routine;
use App\Models\Syllabus;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Noticeboard;
use App\Models\FrontendEvent;
use App\Models\Admin;
use App\Models\ExpenseCategory;
use App\Models\Expense;
use App\Models\StudentFeeManager;
use App\Models\PaymentMethods;
use App\Models\Payments;
use App\Models\MessageThrade;
use App\Models\Chat;
use Illuminate\Foundation\Auth\User as AuthUser;

class StudentController extends Controller
{
    /**
     * Show the student dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function studentDashboard()
    {

        if (auth()->user()->role_id == 7) {

            $user = auth()->user();
            $enroll = \App\Models\Enrollment::where('user_id', $user->id)
                ->where('school_id', $user->school_id)->latest()->first();

            $events = [];         // assignment / quiz deadlines
            $routineEvents = [];  // recurring weekly class routine
            $todo = collect();

            if ($enroll) {
                $assignments = \App\Models\Assignment::where('class_id', $enroll->class_id)
                    ->where('section_id', $enroll->section_id)
                    ->where('school_id', $user->school_id)
                    ->where('status', 'published')->get();

                $submitted = \App\Models\AssignmentSubmission::where('student_id', $user->id)
                    ->pluck('status', 'assignment_id')->toArray();

                foreach ($assignments as $a) {
                    if (!$a->deadline) continue;
                    $status = $submitted[$a->id] ?? null;
                    $color = $status === 'returned' ? '#00955f' : ($status === 'submitted' ? '#2f6fb0' : '#f04b24');
                    $events[] = [
                        'id'      => $a->id,
                        'title'   => ($a->is_quiz ? '📝 ' : '📌 ') . $a->title,
                        'start'   => date('Y-m-d', $a->deadline),
                        'allDay'  => true,
                        'color'   => $color,
                        'url'     => $a->is_quiz ? route('student.quiz.take', $a->id) : route('student.assignment.show', $a->id),
                    ];
                }

                // class routine woven into the calendar as recurring weekly events
                $dow = ['sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6];
                $routineRows = \App\Models\Routine::where('class_id', $enroll->class_id)
                    ->where('section_id', $enroll->section_id)
                    ->where('school_id', $user->school_id)->get();
                foreach ($routineRows as $r) {
                    $subjName = optional(\App\Models\Subject::find($r->subject_id))->name;
                    $roomName = optional(\App\Models\ClassRoom::find($r->room_id))->name;
                    $routineEvents[] = [
                        'title'      => trim($subjName . ($roomName ? ' · ' . $roomName : '')),
                        'daysOfWeek' => [$dow[strtolower($r->day)] ?? 1],
                        'startTime'  => sprintf('%02d:%02d', (int)$r->starting_hour, (int)$r->starting_minute),
                        'endTime'    => sprintf('%02d:%02d', (int)$r->ending_hour, (int)$r->ending_minute),
                        'color'      => '#00955f',
                        'isRoutine'  => true,
                    ];
                }

                // "What you need to do" = not-yet-submitted, soonest deadline first
                $todo = $assignments
                    ->filter(fn($a) => !isset($submitted[$a->id]))
                    ->sortBy(fn($a) => $a->deadline ?: PHP_INT_MAX)
                    ->take(6);
            }

            // student-centric stat cards
            $stats = ['courses' => 0, 'pending' => 0, 'att_pct' => null, 'att_present' => 0, 'att_total' => 0];
            if ($enroll) {
                $stats['courses'] = \App\Models\Course::where('class_id', $enroll->class_id)
                    ->where('school_id', $user->school_id)->where('status', 'published')->count();
                $stats['pending'] = isset($assignments)
                    ? $assignments->filter(fn($a) => !isset($submitted[$a->id]))->count() : 0;

                $attQuery = \Illuminate\Support\Facades\DB::table('daily_attendances')
                    ->where('student_id', $user->id)->where('school_id', $user->school_id);
                $stats['att_total']   = (clone $attQuery)->count();
                $stats['att_present'] = (clone $attQuery)->where('status', 1)->count();
                $stats['att_pct']     = $stats['att_total'] > 0
                    ? round($stats['att_present'] * 100 / $stats['att_total']) : null;
            }

            return view('student.dashboard', compact('events', 'routineEvents', 'todo', 'stats'));
        } else {
            redirect()->route('login')
                ->with('error', 'You are not logged in.');
        }
    }

    /**
     * Show the teacher list.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function teacherList(Request $request)
    {
        $search = $request['search'] ?? "";

        if($search != "") {

            $teachers = User::where(function ($query) use($search) {
                    $query->where('name', 'LIKE', "%{$search}%")
                        ->where('school_id', auth()->user()->school_id)
                        ->where('role_id', 3);
                })->paginate(10);

        } else {
            $teachers = User::where('role_id', 3)->where('school_id', auth()->user()->school_id)->paginate(10);
        }

        return view('student.teacher.teacher_list', compact('teachers', 'search'));
    }

    /**
     * Show the daily attendance.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function dailyAttendance(Request $request)
    {
        if(!empty($request->all())){
            $data = $request->all();
            $date = '01 '.$data['month'].' '.$data['year'];
            $page_data['attendance_date'] = strtotime($date);
            $page_data['month'] = $data['month'];
            $page_data['year'] = $data['year'];

            $student_data = (new CommonController)->get_student_details_by_id(auth()->user()->id);
            $classes = Classes::where('school_id', auth()->user()->school_id)->get();
            $sections = Section::where(['class_id' => $student_data['class_id']])->get();

            return view('student.attendance.daily_attendance', ['student_data' => $student_data, 'classes' => $classes, 'sections' => $sections, 'page_data' => $page_data]);
        } else {

            $date = '01 '.date('M').' '.date('Y');
            $page_data['attendance_date'] = strtotime($date);
            $page_data['month'] = date('M');
            $page_data['year'] = date('Y');

            $student_data = (new CommonController)->get_student_details_by_id(auth()->user()->id);
            $classes = Classes::where('school_id', auth()->user()->school_id)->get();
            $sections = Section::where(['class_id' => $student_data['class_id']])->get();
            return view('student.attendance.daily_attendance', ['student_data' => $student_data, 'classes' => $classes, 'sections' => $sections, 'page_data' => $page_data]);
        }
    }

    public function dailyAttendanceFilter_csv(Request $request)
    {

        $data = $request->all();

        $store_get_data=array_keys($data);


        $data['month']= substr($store_get_data[0],0,3);
        $data['year']= substr($store_get_data[0],4,4);
        $data['role_id']=substr($store_get_data[0],9,5);

        $active_session = get_school_settings(auth()->user()->school_id)->value('running_session');

      
        $date = '01 ' . $data['month'] . ' ' . $data['year'];


        $first_date = strtotime($date);

        $last_date = date("Y-m-t", strtotime($date));
        $last_date = strtotime($last_date);

        $page_data['month'] = $data['month'];
        $page_data['year'] = $data['year'];
        $page_data['attendance_date'] = $first_date;
        $no_of_users = 0;


        $no_of_users = DailyAttendances::whereBetween('timestamp', [$first_date, $last_date])->where(['school_id' => auth()->user()->school_id,  'session_id' => $active_session])->distinct()->count('student_id');
        $attendance_of_students = DailyAttendances::whereBetween('timestamp', [$first_date, $last_date])->where(['school_id' => auth()->user()->school_id, 'student_id' => auth()->user()->id, 'session_id' => $active_session])->get()->toArray();
       

        $csv_content ="Student"."/".get_phrase('Date');
        $number_of_days = date('m', $page_data['attendance_date']) == 2 ? (date('Y', $page_data['attendance_date']) % 4 ? 28 : (date('m', $page_data['attendance_date']) % 100 ? 29 : (date('m', $page_data['attendance_date']) % 400 ? 28 : 29))) : ((date('m', $page_data['attendance_date']) - 1) % 7 % 2 ? 30 : 31);
        for ($i = 1; $i <= $number_of_days; $i++)
        {
            $csv_content .=','.get_phrase($i);

        }


        $file = "Attendence_report.csv";


        $student_id_count = 0;


        foreach(array_slice($attendance_of_students, 0, $no_of_users) as $attendance_of_student ){
            $csv_content .= "\n";

            $user_details = (new CommonController)->get_user_by_id_from_user_table($attendance_of_student['student_id']);
            if(date('m', $page_data['attendance_date']) == date('m', $attendance_of_student['timestamp'])) {
                
                if($student_id_count != $attendance_of_student['student_id']) {
                    
                    $csv_content .= $user_details['name'] . ',';

                    for ($i = 1; $i <= $number_of_days; $i++) {

                        $page_data['date'] = $i.' '.$page_data['month'].' '.$page_data['year'];
                        $timestamp = strtotime($page_data['date']);

                        $attendance_by_id = DailyAttendances::where([ 'student_id' => $attendance_of_student['student_id'], 'school_id' => auth()->user()->school_id, 'timestamp' => $timestamp])->first();

                        if(isset($attendance_by_id->status) && $attendance_by_id->status == 1){
                            $csv_content .= "P,";
                        }elseif(isset($attendance_by_id->status) && $attendance_by_id->status == 0){
                            $csv_content .= "A,";
                        }
                        else
                        {
                            $csv_content .= ",";

                        }


                        if($i==$number_of_days)
                        {
                            $csv_content= substr_replace($csv_content,"", -1);
                        }
                    }
                }

                $student_id_count = $attendance_of_student['student_id'];
            }
        }

        $txt = fopen($file, "w") or die("Unable to open file!");
        fwrite($txt, $csv_content);
        fclose($txt);

        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=' . $file);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        header("Content-type: text/csv");
        readfile($file);
    }

    /**
     * Show the routine.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function routine()
    {
        $student_data = (new CommonController)->get_student_details_by_id(auth()->user()->id);
        $class_id = $student_data['class_id'];
        $section_id = $student_data['section_id'];
        $classes = Classes::where('school_id', auth()->user()->school_id)->get();
        return view('student.routine.routine', ['class_id' => $class_id, 'section_id' => $section_id, 'classes' => $classes]);
    }

    /**
     * Show the subject list.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function subjectList()
    {
        $active_session = get_school_settings(auth()->user()->school_id)->value('running_session');

        $student_data = (new CommonController)->get_student_details_by_id(auth()->user()->id);
        $subjects = Subject::where('class_id', $student_data['class_id'])
            ->where('school_id', auth()->user()->school_id)
            ->where('session_id', $active_session)
            ->paginate(10);

        return view('student.subject.subject_list', compact('subjects'));
    }

    /**
     * Show the syllabus.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function syllabus()
    {
        if (auth()->user()->role_id != "" && auth()->user()->role_id == 7) {
            $active_session = get_school_settings(auth()->user()->school_id)->value('running_session');
            $student_data = (new CommonController)->get_student_details_by_id(auth()->user()->id);

            $syllabuses = Syllabus::where(['class_id' => $student_data['class_id'], 'section_id' => $student_data['section_id'], 'session_id' => $active_session, 'school_id' => auth()->user()->school_id])->paginate(10);

            return view('student.syllabus.syllabus', compact('syllabuses'));
        } else {
            return redirect('login')->with('error', "Please login first.");
        }
    }

    public function syllabusDetails($id)
    {
        $user = auth()->user();
        $syllabus = \App\Models\Syllabus::where('id', $id)->where('school_id', $user->school_id)->firstOrFail();

        $sd = (new CommonController)->get_student_details_by_id($user->id);
        abort_if($syllabus->class_id != $sd['class_id'], 403);

        $subject = \App\Models\Subject::find($syllabus->subject_id);

        // pull the detailed outline (topics + lesson notes) from the matching course, if one exists
        $course = \App\Models\Course::where('class_id', $syllabus->class_id)
            ->where('subject_id', $syllabus->subject_id)
            ->where('school_id', $user->school_id)
            ->where('status', 'published')->first();
        if ($course) $course->load('topics.lessons');

        return view('student.syllabus.details', compact('syllabus', 'subject', 'course'));
    }

    public function syllabusPdf($id)
    {
        $user = auth()->user();
        $syllabus = \App\Models\Syllabus::where('id', $id)->where('school_id', $user->school_id)->firstOrFail();
        $sd = (new CommonController)->get_student_details_by_id($user->id);
        abort_if($syllabus->class_id != $sd['class_id'], 403);

        $subject = \App\Models\Subject::find($syllabus->subject_id);
        $class = \App\Models\Classes::find($syllabus->class_id);
        $course = \App\Models\Course::where('class_id', $syllabus->class_id)
            ->where('subject_id', $syllabus->subject_id)
            ->where('school_id', $user->school_id)->where('status', 'published')->first();
        if ($course) $course->load('topics.lessons');

        $school = \DB::table('schools')->where('id', $user->school_id)->first();
        $logoFile = get_settings('dark_logo');
        $logoPath = $logoFile ? public_path('assets/uploads/logo/' . $logoFile) : null;
        if ($logoPath && !file_exists($logoPath)) $logoPath = null;

        $pdf = \PDF::loadView('student.syllabus.pdf', compact('syllabus', 'subject', 'class', 'course', 'school', 'logoPath'));
        $pdf->setPaper('a4');
        return $pdf->download('Syllabus-' . str_replace(' ', '-', $subject->name ?? 'course') . '.pdf');
    }

    /**
     * Show the grade list.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function marks($value = '')
    {
        $user = auth()->user();
        $exam_categories = ExamCategory::where('school_id', $user->school_id)->get();
        $student_details = (new CommonController)->get_student_details_by_id($user->id);
        $subjects = Subject::where(['class_id' => $student_details['class_id'], 'school_id' => $user->school_id])->get();
        $active_session = get_school_settings($user->school_id)->value('running_session');

        // marks map: [exam_category_id][subject_id] = mark
        $marksMap = [];
        $gbRows = \App\Models\Gradebook::where('student_id', $student_details['user_id'])
            ->where('school_id', $user->school_id)
            ->where('session_id', $active_session)->get();
        foreach ($gbRows as $gb) {
            $marksMap[$gb->exam_category_id] = json_decode($gb->marks, true) ?: [];
        }

        $grades = \App\Models\Grade::where('school_id', $user->school_id)->orderByDesc('mark_from')->get();

        return view('student.marks.index', compact('exam_categories', 'student_details', 'subjects', 'marksMap', 'grades'));
    }

    public function gradeList()
    {
        $grades = Grade::where('school_id', auth()->user()->school_id)->paginate(10);
        return view('student.grade.grade_list', compact('grades'));
    }

    /**
     * Academic history — year-by-year (session-by-session) breakdown of all CATs/exams.
     */
    public function academicHistory()
    {
        $user   = auth()->user();
        $grades = \App\Models\Grade::where('school_id', $user->school_id)->orderByDesc('mark_from')->get();

        $rows = \App\Models\Gradebook::where('student_id', $user->id)
            ->where('school_id', $user->school_id)->get();

        $sessions = \App\Models\Session::whereIn('id', $rows->pluck('session_id')->unique())
            ->orderByDesc('id')->get();

        $history = [];
        foreach ($sessions as $session) {
            $sr = $rows->where('session_id', $session->id);
            $cats = \App\Models\ExamCategory::whereIn('id', $sr->pluck('exam_category_id')->unique())
                ->orderBy('id')->get();

            // subject ids appearing in this year's marks
            $subjIds = collect();
            $map = []; // [subject_id][cat_id] = mark
            foreach ($sr as $r) {
                $m = json_decode($r->marks, true) ?: [];
                foreach ($m as $sid => $mark) { $subjIds->push($sid); $map[$sid][$r->exam_category_id] = $mark; }
            }
            $subjects = \App\Models\Subject::whereIn('id', $subjIds->unique())->get();

            $history[] = [
                'session'  => $session,
                'cats'     => $cats,
                'subjects' => $subjects,
                'map'      => $map,
            ];
        }

        return view('student.marks.history', compact('history', 'grades'));
    }

    /**
     * Show the book list.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function bookList(Request $request)
    {
        $search = $request['search'] ?? "";

        if($search != "") {

            $books = Book::where(function ($query) use($search) {
                    $query->where('name', 'LIKE', "%{$search}%")
                        ->where('school_id', auth()->user()->school_id);
                })->orWhere(function ($query) use($search) {
                    $query->where('author', 'LIKE', "%{$search}%")
                        ->where('school_id', auth()->user()->school_id);
                })->paginate(10);

        } else {
            $books = Book::where('school_id', auth()->user()->school_id)->paginate(10);
        }

        return view('student.book.list', compact('books', 'search'));
    }

    public function bookIssueList(Request $request)
    {
        $active_session = get_school_settings(auth()->user()->school_id)->value('running_session');

        if (count($request->all()) > 0) {

            $data = $request->all();

            $date = explode('-', $data['eDateRange']);
            $date_from = strtotime($date[0] . ' 00:00:00');
            $date_to  = strtotime($date[1] . ' 23:59:59');
            $book_issues = BookIssue::where('issue_date', '>=', $date_from)
                ->where('issue_date', '<=', $date_to)
                ->where('school_id', auth()->user()->school_id)
                ->where('session_id', $active_session)
                ->get();

            return view('student.book.book_issue', ['book_issues' => $book_issues, 'date_from' => $date_from, 'date_to' => $date_to]);
        } else {

            $date_from = strtotime(date('d-M-Y', strtotime(' -30 day')) . ' 00:00:00');
            $date_to = strtotime(date('d-M-Y') . ' 23:59:59');
            $book_issues = BookIssue::where('issue_date', '>=', $date_from)
                ->where('issue_date', '<=', $date_to)
                ->where('school_id', auth()->user()->school_id)
                ->where('session_id', $active_session)
                ->get();

            return view('student.book.book_issue', ['book_issues' => $book_issues, 'date_from' => $date_from, 'date_to' => $date_to]);
        }
    }

    /**
     * Show the noticeboard list.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function noticeboardList()
    {
        $notices = Noticeboard::get()->where('school_id', auth()->user()->school_id);

        $events = array();

        foreach ($notices as $notice) {
            if ($notice['end_date'] != "") {
                if ($notice['start_date'] != $notice['end_date']) {
                    $end_date = strtotime($notice['end_date']) + 24 * 60 * 60;
                    $end_date = date('Y-m-d', $end_date);
                } else {
                    $end_date = date('Y-m-d', strtotime($notice['end_date']));
                }
            }

            if ($notice['end_date'] == "" && $notice['start_time'] == "" && $notice['end_time'] == "") {
                $info = array(
                    'id' => $notice['id'],
                    'title' => $notice['notice_title'],
                    'start' => date('Y-m-d', strtotime($notice['start_date']))
                );
            } else if ($notice['start_time'] != "" && ($notice['end_date'] == "" && $notice['end_time'] == "")) {
                $info = array(
                    'id' => $notice['id'],
                    'title' => $notice['notice_title'],
                    'start' => date('Y-m-d', strtotime($notice['start_date'])) . 'T' . $notice['start_time']
                );
            } else if ($notice['end_date'] != "" && ($notice['start_time'] == "" && $notice['end_time'] == "")) {
                $info = array(
                    'id' => $notice['id'],
                    'title' => $notice['notice_title'],
                    'start' => date('Y-m-d', strtotime($notice['start_date'])),
                    'end' => $end_date
                );
            } else if ($notice['end_date'] != "" && $notice['start_time'] != "" && $notice['end_time'] != "") {
                $info = array(
                    'id' => $notice['id'],
                    'title' => $notice['notice_title'],
                    'start' => date('Y-m-d', strtotime($notice['start_date'])) . 'T' . $notice['start_time'],
                    'end' => date('Y-m-d', strtotime($notice['end_date'])) . 'T' . $notice['end_time']
                );
            } else {
                $info = array(
                    'id' => $notice['id'],
                    'title' => $notice['notice_title'],
                    'start' => date('Y-m-d', strtotime($notice['start_date']))
                );
            }
            array_push($events, $info);
        }

        $events = json_encode($events);

        return view('student.noticeboard.noticeboard', ['events' => $events]);
    }

    public function editNoticeboard($id = "")
    {
        $notice = Noticeboard::find($id);
        return view('student.noticeboard.edit', ['notice' => $notice]);
    }

    /**
     * Show the live class.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */


    public function FeeManagerList(Request $request)
    {
        $active_session = get_school_settings(auth()->user()->school_id)->value('running_session');
        $student_class_information = Enrollment::where('user_id', auth()->user()->id)->first()->toArray();

        if (count($request->all()) > 0) {


            $data = $request->all();
            $date = explode('-', $data['eDateRange']);
            $date_from = strtotime($date[0] . ' 00:00:00');
            $date_to  = strtotime($date[1] . ' 23:59:59');
            $selected_status = $data['status'];

            if ($selected_status != "all") {
                $invoices = StudentFeeManager::where('timestamp', '>=', $date_from)->where('timestamp', '<=', $date_to)->where('status', $selected_status)->where('student_id', auth()->user()->id)->where('session_id', $active_session)->get();
            } else if ($selected_status == "all") {
                $invoices = StudentFeeManager::where('timestamp', '>=', $date_from)->where('timestamp', '<=', $date_to)->where('school_id', auth()->user()->school_id)->where('student_id', auth()->user()->id)->where('session_id', $active_session)->get();
            }


            return view('student.fee_manager.student_fee_manager', ['invoices' => $invoices, 'date_from' => $date_from, 'date_to' => $date_to,  'selected_status' => $selected_status]);
        } else {

            $date_from = strtotime(date('d-M-Y', strtotime(' -30 day')) . ' 00:00:00');
            $date_to = strtotime(date('d-M-Y') . ' 23:59:59');
            $selected_status = "";

            $invoices = StudentFeeManager::where('timestamp', '>=', $date_from)->where('timestamp', '<=', $date_to)->where('student_id', auth()->user()->id)->where('school_id', auth()->user()->school_id)->where('session_id', $active_session)->get();

            return view('student.fee_manager.student_fee_manager', ['invoices' => $invoices, 'date_from' => $date_from, 'date_to' => $date_to,  'selected_status' => $selected_status]);
        }
    }

    public function feeManagerExport($date_from = "", $date_to = "", $selected_status = "")
    {

        $active_session = get_school_settings(auth()->user()->school_id)->value('running_session');


        if ($selected_status != "all") {
            $invoices = StudentFeeManager::where('timestamp', '>=', $date_from)->where('timestamp', '<=', $date_to)->where('status', $selected_status)->where('student_id', auth()->user()->id)->where('session_id', $active_session)->get();
        } else if ($selected_status == "all") {
            $invoices = StudentFeeManager::where('timestamp', '>=', $date_from)->where('timestamp', '<=', $date_to)->where('school_id', auth()->user()->school_id)->where('student_id', auth()->user()->id)->where('session_id', $active_session)->get();
        }

        $classes = Classes::where('school_id', auth()->user()->school_id)->get();



        $file = "student_fee-" . date('d-m-Y', $date_from) . '-' . date('d-m-Y', $date_to) . '-' . $selected_status . ".csv";

        $csv_content = get_phrase('Invoice No') . ', ' . get_phrase('Student') . ', ' . get_phrase('Class') . ', ' . get_phrase('Invoice Title') . ', ' . get_phrase('Total Amount') . ', ' . get_phrase('Created At') . ', ' . get_phrase('Paid Amount') . ', ' . get_phrase('Status');

        foreach ($invoices as $invoice) {
            $csv_content .= "\n";

            $student_details = (new CommonController)->get_student_details_by_id($invoice['student_id']);
            $invoice_no = sprintf('%08d', $invoice['id']);

            $csv_content .= $invoice_no . ', ' . $student_details['name'] . ', ' . $student_details['class_name'] . ', ' . $invoice['title'] . ', ' . currency($invoice['total_amount']) . ', ' . date('d-M-Y', $invoice['timestamp']) . ', ' . currency($invoice['paid_amount']) . ', ' . $invoice['status'];
        }
        $txt = fopen($file, "w") or die("Unable to open file!");
        fwrite($txt, $csv_content);
        fclose($txt);

        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename=' . $file);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        header("Content-type: text/csv");
        readfile($file);
    }

    public function FeePayment(Request $request, $id)
    {
        // Ownership: a student may only open their own fee record (prevents invoice IDOR).
        $fee = StudentFeeManager::where('id', $id)->where('student_id', auth()->user()->id)->first();
        abort_if(!$fee, 403);
        $fee_details = $fee->toArray();
        $user_info = User::where('id', $fee_details['student_id'])->first()->toArray();
        return view('student.payment.payment_gateway', ['fee_details' => $fee_details, 'user_info' => $user_info]);
    }

    public function studentFeeinvoice(Request $request, $id)
    {
        $invoice = StudentFeeManager::where('id', $id)->where('student_id', auth()->user()->id)->first();
        abort_if(!$invoice, 403);
        $invoice_details = $invoice->toArray();
        $student_details = (new CommonController)->get_student_details_by_id($invoice_details['student_id'])->toArray();

        return view('student.fee_manager.invoice', ['invoice_details' => $invoice_details, 'student_details' => $student_details]);
    }

   

    public function offlinePaymentStudent(Request $request, $id = "")
    {
        // Ownership: prevent a student flipping another student's invoice to "pending".
        abort_if(!StudentFeeManager::where('id', $id)->where('student_id', auth()->user()->id)->exists(), 403);

        $data = $request->all();

        if ($data['amount'] > 0) {

            $file = $data['document_image'];

            if ($file) {
                $request->validate(['document_image' => 'file|mimes:pdf,png,jpg,jpeg,gif|max:10240']);
                $filename = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());
                $dir = public_path('assets/uploads/offline_payment');
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                $file->move($dir, $filename);
                $data['document_image'] = $filename;
            } else {
                $data['document_image'] = '';
            }

            StudentFeeManager::where('id',  $id)->update([
                'status' => 'pending',
                'document_image' => $data['document_image'],
                'payment_method' => 'offline'
            ]);





            return redirect()->route('student.fee_manager.list')->with('message', 'offline payment requested successfully');
        } else {
            return redirect()->route('student.fee_manager.list')->with('message', 'offline payment requested fail');
        }
    }

    function profile(){
        return view('student.profile.view');
    }

    function profile_update(Request $request){
        $data['name'] = $request->name;
        $data['email'] = $request->email;
        
        $user_info['birthday'] = strtotime($request->eDefaultDateRange);
        $user_info['gender'] = $request->gender;
        $user_info['phone'] = $request->phone;
        $user_info['address'] = $request->address;


        if(empty($request->photo)){
            $user_info['photo'] = $request->old_photo;
        }else{
            $file_name = random(10).'.png';
            $user_info['photo'] = $file_name;

            $request->photo->move(public_path('assets/uploads/user-images/'), $file_name);
        }

        $data['user_information'] = json_encode($user_info);

        User::where('id', auth()->user()->id)->update($data);
        
        return redirect(route('student.profile'))->with('message', get_phrase('Profile info updated successfully'));
    }

    function user_language(Request $request){
        $data['language'] = $request->language;
        User::where('id', auth()->user()->id)->update($data);
        
        return redirect()->back()->with('message', 'You have successfully transleted language.');
    }

    function password($action_type = null, Request $request){



        if($action_type == 'update'){

            

            if($request->new_password != $request->confirm_password){
                return back()->with("error", "Confirm Password Doesn't match!");
            }


            if(!Hash::check($request->old_password, auth()->user()->password)){
                return back()->with("error", "Current Password Doesn't match!");
            }

            $data['password'] = Hash::make($request->new_password);
            User::where('id', auth()->user()->id)->update($data);

            return redirect(route('student.password', 'edit'))->with('message', get_phrase('Password changed successfully'));
        }

        return view('student.profile.password');
    }

    /**
     * Show the event list.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function eventList(Request $request)
    {
        $search = $request['search'] ?? "";

        if($search != "") {

            $events = FrontendEvent::where(function ($query) use($search) {
                    $query->where('title', 'LIKE', "%{$search}%");
                })->paginate(10);

        } else {
            $events = FrontendEvent::where('school_id', auth()->user()->school_id)->paginate(10);
        }

        return view('student.events.events', compact('events', 'search'));
    }

    function complain(){
        return view('student.complain.complain');
    }
    function complainUser(Request $request){
        // M12: read with defaults so a bare request doesn't undefined-index → 500.
        $page_data['class_id'] = $request->input('class_id');
        $page_data['section_id'] = $request->input('section_id');
        $page_data['receiver'] = $request->input('receiver');
        return view('student.complain.complainUser', ['page_data' => $page_data]);
   }

    function receivers(Request $request){
        $data = $request->all();

        $page_data['class_id'] = $data['class_id'];
        $page_data['section_id'] = $data['section_id'];
        $page_data['receiver'] = $data['receiver'];
        return view('student.complain.complain', ['page_data' => $page_data]);
   }

    //  Message

    public function allMessage(Request $request, $id)
    {
        // C6: verify the caller participates in this thread (prevents messaging IDOR)
        abort_if(!\DB::table("message_thrades")->where("id", $id)->where("school_id", auth()->user()->school_id)->where(function($q){ $q->where("sender_id", auth()->user()->id)->orWhere("reciver_id", auth()->user()->id); })->exists(), 403);

            $msg_user_details = DB::table('users')
            ->join('message_thrades', function ($join) {
                // Join where the user is the sender
                $join->on('users.id', '=', 'message_thrades.sender_id')
                    ->orWhere(function ($query) {
                        // Join where the user is the receiver
                        $query->on('users.id', '=', 'message_thrades.reciver_id');
                    });
            })
            ->select('users.id as user_id', 'message_thrades.id as thread_id', 'users.*', 'message_thrades.*')
            ->where('message_thrades.id', $id)
            ->where('message_thrades.school_id', auth()->user()->school_id)
            ->where('users.id', '<>', auth()->user()->id) // Exclude the authenticated user
            ->first();

            
            
        if ($request->ajax()) {
            $query = $request->input('query');
            
            // Search users by name or any other criteria
            $users = User::where('name', 'LIKE', "%{$query}%")
                ->where('school_id', auth()->user()->school_id)
                ->get();

            // Prepare HTML response
            $html = '';

            // Check if any users were found
            if ($users->isEmpty()) {
                return response()->json('No User found');
            }

            foreach ($users as $user) {
                
                if (!empty($user)) {
                    $userInfo = json_decode($user->user_information);
                    
                    $user_image = !empty($userInfo->photo) 
                        ? asset('assets/uploads/user-images/' . $userInfo->photo) 
                        : asset('assets/uploads/user-images/thumbnail.png');

                    $html .= '
                        <div class="user-item d-flex align-items-center msg_us_src_list">
                            <a href="' . route('student.message.messagethrades', ['id' => $user->id]).'">
                                <img src="' . $user_image . '" alt="User Image" style="width: 50px; height: 50px; border-radius: 50%;">
                                <span class="ms-3">' . $user->name . '</span>
                            </a>
                        </div>
                    ';
                }
            }

            return response()->json($html);
        }


        $chat_datas = Chat::where('school_id', auth()->user()->school_id)->get();

        $counter_condition = Chat::where('message_thrade', $id)->orderBy('id', 'desc')->first();

       
       if($counter_condition->sender_id != auth()->user()->id){
            Chat::where('message_thrade', $id)->update(['read_status' => 1]);
        }
        
        return view('student.message.all_message', ['msg_user_details' => $msg_user_details], ['chat_datas' => $chat_datas]);
    }

    public function messagethrades($id){

        $exists = MessageThrade::where('reciver_id', $id)
                            ->where('sender_id', auth()->user()->id)
                            ->exists();
        if( $id != auth()->user()->id){
            if (!$exists) {
                $message_thrades_data = [
                    'reciver_id' => $id,
                    'sender_id' => auth()->user()->id,
                    'school_id' => auth()->user()->school_id,
                ];
        
                MessageThrade::create($message_thrades_data);
        
                //return redirect()->back()->with('message', 'User added successfully');
            }
    
            
            $message_thrades = MessageThrade::where('reciver_id', $id)
                                         ->where('sender_id', auth()->user()->id)
                                         ->first();
            $msg_trd_id = $message_thrades->id;
            
            $msg_user_details = DB::table('users')
                ->join('message_thrades', 'users.id', '=', 'message_thrades.reciver_id')
                ->select('users.id as user_id', 'message_thrades.id as thread_id', 'users.*', 'message_thrades.*')
                ->where('message_thrades.id', $msg_trd_id)
                ->first();
    
                $chat_datas = Chat::where('school_id', auth()->user()->school_id)->get();
    
                // Combine all data into a single array
                return view('student.message.all_message', ['id' => $msg_trd_id, 'msg_user_details' => $msg_user_details, 'chat_datas' => $chat_datas,]);
        }
        return redirect()->back()->with('error', 'You can not add you');
        
                        
    }


    public function chat_save(Request $request)
    {
        $data = $request->all();
        $chat_data = [
            'message_thrade' => $data['message_thrade'],
            'reciver_id' => $data['reciver_id'],
            'message' => $data['message'],
            'school_id' => auth()->user()->school_id,
            'sender_id' => auth()->user()->id,
            'read_status' => 0,

        ];
    
        // Create feedback entry
        Chat::create($chat_data);

        return redirect()->back();
    }

    public function chat_empty(Request $request)
    {

        if ($request->ajax()) {
            $query = $request->input('query');

            $users = User::where('name', 'LIKE', "%{$query}%")
                ->where('school_id', auth()->user()->school_id)
                ->get();

            $html = '';

            if ($users->isEmpty()) {
                return response()->json('No User found');
            }

            foreach ($users as $user) {
                $userInfo = json_decode($user->user_information);
                $user_image = !empty($userInfo->photo) 
                    ? asset('assets/uploads/user-images/' . $userInfo->photo) 
                    : asset('assets/uploads/user-images/thumbnail.png');

                $html .= '
                    <div class="user-item d-flex align-items-center msg_us_src_list">
                        <a href="' . route('student.message.messagethrades', ['id' => $user->id]).'">
                            <img src="' . $user_image . '" alt="User Image" style="width: 50px; height: 50px; border-radius: 50%;">
                            <span class="ms-3">' . $user->name . '</span>
                        </a>
                    </div>
                ';
            }

            return response()->json($html);
        }

        // Pass the data to the view only if msg_user_details is not null
        return view('student.message.chat_empty');
    }

   
}
