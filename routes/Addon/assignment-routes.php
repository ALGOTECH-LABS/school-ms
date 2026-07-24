<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\QuestionBankController;

/*
| Assignments addon routes. Loaded by RouteServiceProvider when the
| 'assignments' addon is enabled. 'web' middleware is applied by the provider.
*/

Route::controller(AssignmentController::class)->group(function () {

    // ---- Teacher ----
    Route::middleware(['teacher', 'auth'])->group(function () {
        Route::get('teacher/assignments/{type?}', 'teacherHome')->name('teacher.assignment_home');
        Route::get('teacher/assignment/create-modal', 'teacherCreateModal')->name('teacher.assignment.create_modal');
        Route::get('teacher/assignment/class-sections', 'classSections')->name('teacher.assignment.class_sections');
        Route::get('teacher/assignment/class-subjects', 'classSubjects')->name('teacher.assignment.class_subjects');
        Route::post('teacher/assignment/store', 'teacherStore')->name('teacher.assignment.store');
        Route::get('teacher/assignment/show/{id}', 'teacherShow')->name('teacher.assignment.show');
        Route::post('teacher/assignment/grade/{submission_id}', 'teacherGrade')->name('teacher.assignment.grade');
        Route::post('teacher/assignment/delete/{id}', 'teacherDelete')->name('teacher.assignment.delete');
    });

    // ---- Student ----
    Route::middleware(['student', 'auth'])->group(function () {
        Route::get('student/my-assignments/{type?}', 'studentHome')->name('student.assignment_home');
        Route::get('student/my-assignment/show/{id}', 'studentShow')->name('student.assignment.show');
        Route::post('student/my-assignment/submit/{id}', 'studentSubmit')->name('student.assignment.submit');
    });

    // ---- Admin ----
    Route::middleware(['admin', 'auth'])->group(function () {
        Route::get('admin/assignments/{type?}', 'adminHome')->name('admin.assignment_home');
        Route::get('admin/assignment/sections', 'adminSections')->name('admin.assignment.sections');
        Route::get('admin/assignment/show/{id}', 'adminShow')->name('admin.assignment.show');
    });
});

/* ------------------------- Question bank + quizzes ------------------------- */
Route::controller(QuestionBankController::class)->group(function () {

    // ---- Teacher ----
    Route::middleware(['teacher', 'auth'])->group(function () {
        Route::get('teacher/question-bank', 'index')->name('teacher.qbank');
        Route::get('teacher/question-bank/create-modal', 'createModal')->name('teacher.qbank.create_modal');
        Route::get('teacher/question-bank/class-subjects', 'classSubjects')->name('teacher.qbank.class_subjects');
        Route::get('teacher/question-bank/class-sections', 'classSubjectsSections')->name('teacher.qbank.class_sections');
        Route::post('teacher/question-bank/store', 'store')->name('teacher.qbank.store');
        Route::get('teacher/question-bank/edit-modal/{id}', 'editModal')->name('teacher.qbank.edit_modal');
        Route::post('teacher/question-bank/update/{id}', 'update')->name('teacher.qbank.update');
        Route::post('teacher/question-bank/delete/{id}', 'delete')->name('teacher.qbank.delete');
        Route::get('teacher/question-bank/generate-modal', 'generateModal')->name('teacher.qbank.generate_modal');
        Route::post('teacher/question-bank/generate', 'generateQuiz')->name('teacher.qbank.generate');
        Route::get('teacher/online-cats', 'quizzes')->name('teacher.qbank.quizzes');

        Route::get('teacher/quiz/questions/{id}', 'quizQuestions')->name('teacher.quiz.questions');
        Route::get('teacher/quiz/paper/{id}', 'quizPaper')->name('teacher.quiz.paper');
        Route::post('teacher/quiz/question/add', 'quizQuestionAdd')->name('teacher.quiz.question.add');
        Route::post('teacher/quiz/question/remove', 'quizQuestionRemove')->name('teacher.quiz.question.remove');
        Route::get('teacher/quiz/review/{id}', 'teacherReview')->name('teacher.quiz.review');
        Route::get('teacher/quiz/grade-view/{submission_id}', 'teacherGradeView')->name('teacher.quiz.grade_view');
        Route::post('teacher/quiz/grade/{submission_id}', 'teacherGrade')->name('teacher.quiz.grade');
    });

    // ---- Student ----
    Route::middleware(['student', 'auth'])->group(function () {
        Route::get('student/quiz/take/{id}', 'studentTake')->name('student.quiz.take');
        Route::post('student/quiz/submit/{id}', 'studentSubmit')->name('student.quiz.submit');
    });
});
