<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OnlineCourseController;

/*
| Online Courses addon routes. Loaded by RouteServiceProvider when the
| 'online_courses' addon is enabled. 'web' middleware is applied by the provider.
*/

Route::controller(OnlineCourseController::class)->group(function () {

    // ---- Teacher ----
    Route::middleware(['teacher', 'auth'])->group(function () {
        Route::get('teacher/addons/courses', 'teacherIndex')->name('teacher.addons.courses');
        Route::get('teacher/addons/courses/create-modal', 'teacherCreateModal')->name('teacher.addons.course.create_modal');
        Route::get('teacher/addons/courses/class-subjects', 'classSubjects')->name('teacher.addons.course.class_subjects');
        Route::post('teacher/addons/courses/store', 'teacherStore')->name('teacher.addons.course.store');
        Route::get('teacher/addons/courses/manage/{id}', 'teacherManage')->name('teacher.addons.course.manage');
        Route::get('teacher/addons/courses/attendance/{id}', 'courseAttendance')->name('teacher.addons.course.attendance');
        Route::post('teacher/addons/courses/attendance/save', 'courseAttendanceSave')->name('teacher.addons.course.attendance.save');
        Route::post('teacher/addons/courses/delete/{id}', 'teacherDeleteCourse')->name('teacher.addons.course.delete');

        Route::post('teacher/addons/courses/topic/store', 'topicStore')->name('teacher.addons.course.topic.store');
        Route::post('teacher/addons/courses/topic/delete/{id}', 'topicDelete')->name('teacher.addons.course.topic.delete');

        Route::get('teacher/addons/courses/lesson/create-modal/{topic_id}', 'lessonCreateModal')->name('teacher.addons.course.lesson.create_modal');
        Route::post('teacher/addons/courses/lesson/store', 'lessonStore')->name('teacher.addons.course.lesson.store');
        Route::get('teacher/addons/courses/lesson/edit-modal/{id}', 'lessonEditModal')->name('teacher.addons.course.lesson.edit_modal');
        Route::post('teacher/addons/courses/lesson/update/{id}', 'lessonUpdate')->name('teacher.addons.course.lesson.update');
        Route::post('teacher/addons/courses/lesson/delete/{id}', 'lessonDelete')->name('teacher.addons.course.lesson.delete');

        Route::post('teacher/addons/courses/material/store', 'materialStore')->name('teacher.addons.course.material.store');
        Route::post('teacher/addons/courses/material/delete/{id}', 'materialDelete')->name('teacher.addons.course.material.delete');

        Route::post('teacher/addons/courses/session/store', 'sessionStore')->name('teacher.addons.course.session.store');
        Route::post('teacher/addons/courses/session/cancel/{id}', 'sessionCancel')->name('teacher.addons.course.session.cancel');
        Route::post('teacher/addons/courses/session/delete/{id}', 'sessionDelete')->name('teacher.addons.course.session.delete');

        Route::get('teacher/addons/courses/coursework/create-modal/{course_id}', 'courseworkCreateModal')->name('teacher.addons.course.coursework.create_modal');
        Route::post('teacher/addons/courses/coursework/store', 'courseworkStore')->name('teacher.addons.course.coursework.store');

        Route::get('teacher/addons/courses/cat/create-modal/{course_id}', 'catCreateModal')->name('teacher.addons.course.cat.create_modal');
        Route::post('teacher/addons/courses/cat/store', 'catStore')->name('teacher.addons.course.cat.store');
        Route::get('teacher/addons/courses/sitting-cat/create-modal/{course_id}', 'sittingCatCreateModal')->name('teacher.addons.course.sitting_cat.create_modal');
        Route::post('teacher/addons/courses/sitting-cat/store', 'sittingCatStore')->name('teacher.addons.course.sitting_cat.store');
        Route::get('teacher/addons/courses/sitting-cat/questions/{id}', 'sittingCatQuestions')->name('teacher.addons.course.sitting_cat.questions');
        Route::post('teacher/addons/courses/sitting-cat/question/add', 'sittingCatQuestionAdd')->name('teacher.addons.course.sitting_cat.question.add');
        Route::post('teacher/addons/courses/sitting-cat/question/remove', 'sittingCatQuestionRemove')->name('teacher.addons.course.sitting_cat.question.remove');
        Route::get('teacher/addons/courses/sitting-cat/paper/{id}', 'sittingCatPaper')->name('teacher.addons.course.sitting_cat.paper');
        Route::get('teacher/addons/courses/sitting-cat/marks/{id}', 'sittingCatMarks')->name('teacher.addons.course.sitting_cat.marks');
        Route::post('teacher/addons/courses/sitting-cat/marks/save', 'sittingCatMarksSave')->name('teacher.addons.course.sitting_cat.marks_save');
        Route::get('teacher/addons/courses/preview/{id}', 'teacherPreview')->name('teacher.addons.course.preview');

        Route::post('teacher/addons/courses/student/remove', 'studentRemove')->name('teacher.addons.course.student.remove');
        Route::post('teacher/addons/courses/student/readmit', 'studentReadmit')->name('teacher.addons.course.student.readmit');
    });

    // ---- Student ----
    Route::middleware(['student', 'auth'])->group(function () {
        Route::get('student/addons/courses', 'studentIndex')->name('student.addons.courses');
        Route::get('student/addons/courses/view/{id}', 'studentView')->name('student.addons.course.view');
    });

    // ---- Admin ----
    Route::middleware(['admin', 'auth'])->group(function () {
        Route::get('admin/addons/courses', 'adminIndex')->name('admin.addons.courses');
        Route::get('admin/addons/courses/view/{id}', 'adminView')->name('admin.addons.course.view');
    });
});
