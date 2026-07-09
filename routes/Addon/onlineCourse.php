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
