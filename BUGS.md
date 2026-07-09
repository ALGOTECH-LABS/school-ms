# Ekattor 8 — Full Portal Audit & Bug Report

**Date:** 2026-07-08
**App:** The Karen Hospital School of Nursing (Ekattor 8, Laravel 9 / PHP 8.1)
**Base URL tested:** http://127.0.0.1:8000
**Scope:** All 7 portals — Super Admin, School Admin, Teacher, Accountant, Librarian, Parent, Student.

## Methodology

1. **Authenticated HTTP smoke test** — logged into each portal via cURL and issued a GET to every parameter‑less GET route assigned to that role's middleware (230 routes), recording HTTP status and the Laravel exception behind each `500`.
2. **Static code audit** — four parallel reviewers read the custom‑built modules (Finance, Assignments/Quiz, Courses/LMS, Routines, Marks, Transcripts, Syllabus) and the auth/role/IDOR surface, cross‑checked every Blade variable against its controller and every DB column against the live schema.
3. **Verification** — the highest‑severity claims (missing role middleware, grade tampering, IDOR, unauthenticated endpoints) were re‑verified directly against `route:list --json`, the controller source, and the live database before inclusion here.

Legend: ✅ = personally re‑verified against source/routes/DB · ⚠️ = reported by code review, high confidence, not yet exploited live.

## ✅ Remediation status (updated 2026-07-08)

**All 8 Critical + all 12 High + 11 of 13 Medium + key Lows are FIXED and verified.**
- Critical: C1–C8 ✅ (C1 & upload & IDOR checks verified live)
- High: H1–H12 ✅
- Medium: M1 ✅ (overpay blocked — verified), M3 ✅, M4 ✅, M5 ✅, M6 ✅, M7 ✅, M8 ✅, M9 ✅, M10 ✅, M11 ✅, M12 ✅, M13 ✅ (GET‑delete→POST+CSRF — verified). **H11** money reconciliation ✅.
- Low: L5 ✅ (enumeration scoped), L12 ✅ (stock detail null‑guards partially), plus null‑guards throughout.
- **Deferred (documented, not fixed):** **M2** — adding the `session_id` filter to `generateInvoices` would return zero invoices in this dataset because `global_settings.running_session=1` while all enrollments are `session_id=2` (a pre‑existing data mismatch); fixing safely requires correcting `running_session`, out of scope. Remaining **Low** items (L1 param‑needs, L2/L3/L4/L6–L11) are robustness/UX, non‑blocking.

## Severity summary

| Severity | Count | Theme |
|---|---|---|
| 🔴 Critical | 8 | Horizontal IDOR, unprotected grade‑write, arbitrary file upload (RCE), exam bypass |
| 🟠 High | 12 | Broken pages (missing views / null‑deref), auth hardening, money reconciliation, cross‑school finance IDOR |
| 🟡 Medium | 13 | Missing validation, timezone, upload dirs, GET‑based deletes (CSRF), param‑guard 500s |
| 🔵 Low | 12 | Robustness on deleted/stale ids, enumeration, UX traps, segregation‑of‑duties |
| ✅ Fixed during audit | 1 | Admin student‑list crash (regression) |

---

# 🔴 Critical

### C1 — Any authenticated user can create/overwrite ANY student's marks ✅ **EXPLOITED LIVE**
- **Where:** route `GET /mark/update` (`routes/web.php` — `CommonController` group, middleware `[web, auth]` **only, no role guard**) → `app/Http/Controllers/CommonController.php:198` `markUpdate()`.
- **What:** `markUpdate()` writes/creates `Gradebook` rows straight from `$request->all()`, scoping only `school_id`/`session_id` to the caller. There is no role check and no "may this user edit this student" check.
- **Impact:** A **student or parent** can hit `GET /mark/update?exam_category_id=..&class_id=..&section_id=..&student_id=..&subject_id=..&mark=..` and change their own or any classmate's grades. Full grade‑integrity compromise.
- **Proof (this audit):** Logged in as `student99` (role 7) and issued `GET /mark/update?...&student_id=165&subject_id=1&mark=999&comment=HACKED_BY_STUDENT` → **HTTP 200**; gradebook row 1 changed from `{"1":88,…}` to `{"1":"999",…}` with comment `HACKED_BY_STUDENT`. Reverted immediately after confirming.
- **Fix:** Move the route under `admin`/`teacher` role middleware, change to POST, and verify the caller may edit that student's gradebook.

### C2 — Student IDOR: read any invoice + hijack another student's invoice ⚠️
- **Where:** `app/Http/Controllers/StudentController.php:628` `FeePayment()`, `:636` `studentFeeinvoice()`, `:663` `offlinePaymentStudent()`.
- **What:** Each takes an invoice `{id}` via `StudentFeeManager::find($id)` with no `where('student_id', auth()->id())`. `offlinePaymentStudent()` even `UPDATE`s the arbitrary invoice to `pending` and attaches an uploaded file.
- **Impact:** A student enumerates `/student/student_fee/invoice/{id}` to read others' fee data, and via `POST /student/student_fee/offline_payment/{id}` flips another student's invoice status and uploads a document to it.
- **Fix:** Scope every invoice query by `student_id = auth()->id()`; `abort(403)` on mismatch.

### C3 — Parent IDOR: view any student's marks ⚠️
- **Where:** `app/Http/Controllers/ParentController.php:495` `marks_list()`, `:671` `marks_listc()`.
- **What:** `$user_id = $data['student_id']` taken straight from the request; `get_student_details_by_id()` does no `parent_id` scoping.
- **Impact:** A parent tampers the AJAX `student_id` and sees any child's full mark sheet.
- **Fix:** `User::where('id',$user_id)->where('parent_id',auth()->id())->exists()` else `abort(403)`.

### C4 — Parent IDOR: view any student's attendance ⚠️
- **Where:** `app/Http/Controllers/ParentController.php:343,350` `list_of_attendence()`.
- **What:** Uses raw request `student_id` with no parent‑child check. (Also carries bug H‑? below: `section_id` filtered by `class_id`.)
- **Impact:** A parent reads any other family's child's monthly attendance.
- **Fix:** Constrain to children of the auth parent, `abort(403)` otherwise.

### C5 — Parent IDOR: ID card / fee invoice / payment by id ⚠️
- **Where:** `app/Http/Controllers/ParentController.php:91` `studentIdCardGenerate()`, `:134` `FeePayment()`, `:184` `studentFeeinvoice()`.
- **What:** `find($id)` / `get_student_details_by_id($id)` with no `parent_id` (and not even school‑scoped — `CommonController` `User::find($id)`).
- **Impact:** Cross‑tenant read of any student's ID card, fee invoice, and payment history by changing the id.
- **Fix:** Scope each lookup to the authenticated parent's children; `abort(403)` on mismatch.

### C6 — Messaging IDOR: read any conversation in the school ⚠️
- **Where:** `allMessage($id)` duplicated in `StudentController`, `ParentController`, `TeacherController`, `AccountantController`, `LibrarianController`.
- **What:** Thread lookup filters only `message_thrades.school_id` and "not me"; never verifies the caller is a participant. `Chat::where('school_id',…)->get()` over‑fetches every message in the school.
- **Impact:** Any user iterates `message/all-message/{id}` to read others' private threads.
- **Fix:** Require `sender_id = auth id OR reciver_id = auth id`; scope `Chat` by the thread id.

### C7 — Arbitrary file upload → possible RCE ⚠️
- **Where:** `OnlineCourseController.php:83,210`, `AssignmentController.php:94,227` (course thumbnails, course materials, assignment attachments, student submissions).
- **What:** Files are stored as `time().'_'.original_name` into web‑served `public/assets/uploads/…` with **no MIME/extension whitelist**. A `.php`/`.phtml` upload lands in a publicly executable path.
- **Impact:** If the web server executes PHP under `public/`, this is remote code execution; at minimum it's stored‑XSS / malware hosting.
- **Fix:** `->validate(['file'=>'file|mimes:pdf,doc,docx,ppt,pptx,png,jpg|max:10240'])`, store with a randomised name outside the web root (or a non‑executable disk).

### C8 — Quiz/exam timer & deadline can be bypassed ✅ (logic verified)
- **Where:** `app/Http/Controllers/QuestionBankController.php` `studentSubmit()` (~`:271`).
- **What:** `studentSubmit` checks only enrollment + "already submitted"; it performs **no deadline or timer‑window check**. Enforcement lives entirely in `studentTake()` (page load) and client‑side JS. A student who lets the timer expire without reloading, or crafts the POST directly, can submit after the deadline / after time is up.
- **Impact:** Unlimited effective exam time; a "closed" exam remains submittable.
- **Fix:** In `studentSubmit`, reject when `time() > deadline` or `time() > QuizAttempt.started_at + duration*60` (finalize‑empty instead), mirroring `studentTake`.

---

# 🟠 High

### H1 — Role middleware runs before `auth` → 500 for anonymous visitors instead of login redirect ✅
- **Where:** every role group in `routes/web.php` uses `->middleware('admin','auth')` (and teacher/parent/student/accountant/librarian/superadmin equivalents). `route:list` confirms order `[web, RoleMiddleware, Authenticate]`.
- **What:** The role middleware dereferences `auth()->user()->role_id` while the user is still null.
- **Impact:** An unauthenticated visitor to any `/admin/*`, `/student/*`, … URL gets **HTTP 500** ("read property 'role_id' on null") instead of being redirected to `/login`.
- **Fix:** Put `auth` first (`->middleware('auth','admin')`) or null‑guard each middleware (as `AdminAccountantMiddleware` already does).

### H2 — Login does not check disabled accounts ✅
- **Where:** `app/Http/Controllers/Auth/LoginController.php:54` — authenticates on email/password only.
- **What:** No `account_status == 'disable'` check at login. A disabled user still gets a valid session; enforcement relies on each role middleware, so any `auth`‑only route (C1, `user/{id}`, `clear-cache`) stays reachable by disabled accounts.
- **Fix:** After `auth()->attempt()`, log out + error if `account_status` is disabled.

### H3 — `/clear-cache` is unauthenticated ✅
- **Where:** `routes/web.php` `Route::get('/clear-cache', …)` — middleware `[web]` only.
- **What:** Anonymous visitors can repeatedly run `cache:clear`, `config:cache`, `view:clear`, `optimize:clear`.
- **Impact:** Cache stampede / minor DoS; leaks that it's a Laravel app.
- **Fix:** Remove it, or gate behind `superAdmin`.

### H4 — Public self‑registration creates orphan users ✅
- **Where:** `routes/web.php` `register` (GET/POST, `RedirectIfAuthenticated` only) → `Auth/RegisterController.php:65` `create()` sets only name/email/password.
- **What:** Anyone can self‑register; the account has NULL `role_id`/`school_id`/`account_status`. `role_id` is in `User::$fillable`, so any future `User::create($request->all())` becomes a privilege‑escalation vector.
- **Impact:** Junk/orphan accounts and a foothold to reach `auth`‑only routes (C1).
- **Fix:** `Auth::routes(['register' => false])` for this admin‑provisioned system.

### H5 — Missing view `teacher.gradebook.list` → 500 ⚠️
- **Where:** `TeacherController.php:245` `echo view('teacher.gradebook.list', …)`; only `teacher/gradebook/gradebook.blade.php` exists.
- **Impact:** `teacher/gradebook/list` throws `View not found`.
- **Fix:** Create the view (copy the admin one) or drop the route.

### H6 — Missing view `teacher.gradebook.subject_marks` → 500 ⚠️
- **Where:** `TeacherController.php:256`; view does not exist. `teacher/gradebook/subjec_marks/{student_id}` → 500.
- **Fix:** Create the view or drop the route.

### H7 — Complain module: 3 referenced views don't exist → 500 ⚠️
- **Where:** `AdminController.php:3747` `view('admin.complain.complainList')`, `StudentController.php:765` `view('student.complain.complain')`, `:773` `view('student.complain.complainUser')`. No `*complain*` blade files exist.
- **Impact:** `/admin/complain/complainList`, `/student/complain/complain`, `/student/complain/complainUser` all 500. (Confirmed via smoke test.)
- **Fix:** Create the three views (or remove the routes + menu links); validate params in `complainUser`.

### H8 — Null‑deref: "View Result" crashes for a student with no gradebook row ⚠️
- **Where:** `resources/views/admin/gradebook/subject_marks.blade.php:4` (via `AdminController.php:2238`). `Gradebook::…->first()` → null, then `json_decode($x->marks,true)`.
- **Impact:** Clicking View Result for an ungraded student returns a 500 fragment.
- **Fix:** Null‑guard before `->marks`.

### H9 — Deleted subject crashes syllabus lists ⚠️
- **Where:** `teacher/syllabus/list.blade.php:21` `Subject::…->first()->toArray()`; `admin/syllabus/syllabus_list.blade.php:93` `Subject::find(...)->name`.
- **Impact:** If any referenced subject was deleted, the syllabus list 500s.
- **Fix:** `optional($subject)->name` / `$subject->name ?? '-'`.

### H10 — Cross‑school finance IDOR on transfers & account tagging ⚠️
- **Where:** `FinanceController.php` `transferStore()` (~`:838`), and `account_id` in `recordPayment`/`expenseStore`/`incomeStore`.
- **What:** `from/to account_id` validated for presence only — never `where('school_id', …)`. A crafted POST moves money in/out of another school's account balances.
- **Fix:** Validate every account id with a school‑scoped exists rule.

### H11 — Money reconciliation breaks with any fine/discount ⚠️
- **Where:** `FinanceController.php` invoices summary (~`:205`) and `recomputeInvoice` (~`:46`); same pattern in `report_collection`.
- **What:** `billed = SUM(total_amount)` but `balance = total_amount + fine − discount − paid`, and `total_amount` is never updated to include fine/discount. So **Billed − Collected ≠ Outstanding** on any adjusted invoice, and "Collected %" can exceed 100% or go negative.
- **Fix:** Use a consistent net figure (`billed = SUM(total_amount + fine − discount)`), or fold fine/discount into `total_amount` at write time.

### H12 — Quiz submit omits section check + re‑submission race ⚠️
- **Where:** `QuestionBankController.php` `studentSubmit()` (~`:275`, `:277`, `finalizeQuiz :338`).
- **What:** (a) `abort_if` checks only `class_id`, not `section_id` (unlike `studentTake`) → a student in the same class, different section, can take/submit. (b) The "already submitted" guard is a non‑atomic SELECT then plain `create()`; there is **no unique index on `assignment_submissions(assignment_id, student_id)`**, so a double‑click / timer‑auto‑submit race creates duplicate submissions + answers.
- **Fix:** Add the `section_id` check; add a unique DB index and/or `firstOrCreate` under a lock; disable the manual submit button once the timer fires.

---

# 🟡 Medium

### M1 — Server accepts overpayment ⚠️
`FinanceController.php recordPayment` validates `amount|numeric|min:0.01` only; the `max=balance` is HTML‑only. A crafted POST records more than the balance and inflates "Collected". Fix: validate amount ≤ remaining balance server‑side.

### M2 — `generateInvoices` ignores session ⚠️
`FinanceController.php:143` enrollment query filters `class_id`+`school_id` but not `session_id`, so students enrolled in that class in a *prior* session get billed on the current structure. Fix: add `->where('session_id', $structure->session_id)`.

### M3 — Exam deadline stored without timezone normalisation ⚠️
`QuestionBankController.php:197` / `AssignmentController.php:109` `strtotime($request->deadline)` parses the browser's `datetime-local` in the PHP server TZ while display/close use the app TZ. Deadline fires at the wrong wall‑clock time when TZs differ. Fix: `Carbon::parse($deadline, $schoolTz)->timestamp`.

### M4 — Teacher quiz grading not clamped ⚠️
`QuestionBankController.php:396` stores `awarded[qid]` with no per‑question max (client‑side `max` only). A teacher/tampered POST can award more than a question's marks, pushing `obtained_marks` past `total_marks`. Fix: clamp to `[0, $link->marks]`.

### M5 — Upload target directories may not exist → 500 on first upload ⚠️
`OnlineCourseController.php:84,211`, `AssignmentController.php:95,228` `$file->move(public_path('assets/uploads/…'))` throws if the dir doesn't exist. Fix: `File::ensureDirectoryExists()` before move (or create on deploy).

### M6 — Teacher `attendanceTake` mis‑handles new students ⚠️
`TeacherController.php:612` unconditionally `->update()` by `$attendance_id[$key]`; keys don't align for newly‑added students → "Undefined array key" or wrong‑row update; never creates new rows. The admin version guards with `isset(...) … else create()`. Fix: mirror the admin logic.

### M7 — Student attendance/dashboard 500s for an un‑enrolled student ⚠️
`CommonController.php:45` `Classes::find($enrol->class_id)` where `$enrol` can be null; no guard. A student with no enrollment row 500s on dashboard/attendance (and `adminTranscript` for that student). Fix: guard the helper.

### M8 — Syllabus edit without re‑upload blanks the file ⚠️
`AdminController.php:2155` (`syllabusUpdate`), `:2119` (`syllabusAdd`) set `$filename` only inside `if($file)` but write it unconditionally. A direct POST without a file blanks the stored filename → broken download. Fix: only include `file` when present.

### M9 — Null `$active_session` crashes edit‑syllabus modal ⚠️
`admin/syllabus/edit_syllabus.blade.php:7` `Session::where('status',1)->first()->id` (not school‑scoped) → null when no active session flagged. Fix: null‑guard / use `running_session`.

### M10 — `marksFilter` 500s on stale ids ⚠️
`AdminController.php:2299`, `TeacherController.php:78` `Classes::find(...)->name` with no null‑check; a deleted class/section/subject id → 500. Fix: null‑check each `find()`.

### M11 — `superadmin/admin_password` weak/mis‑verbed ✅ (302 seen in smoke)
`SuperAdminController.php:221` updates any user's password from request with no `min`/`confirmed` and registered as `Route::any`. The 302 on GET is `redirect()->back()`. Fix: POST‑only, validate `password|min:8|confirmed`, confirm target id.

### M12 — `complainUser` reads unvalidated params ⚠️
`StudentController.php:773` reads `class_id`/`section_id`/`receiver` with no validation (undefined‑index if absent) — compounds H7.

### M13 — 57 state‑changing operations exposed over GET (CSRF) ✅ **FIXED**
- **Where:** `routes/web.php` (and `routes/Addon/*`) — 57 `delete` routes were registered as `GET`, e.g. `admin/student/delete/{id}`, `admin/class/delete/{id}`, `superadmin/package/delete/{id}`, plus the custom modules' `teacher/assignment/delete/{id}`, `teacher/addons/courses/*/delete/{id}`, `teacher/qbank/delete/{id}`, all `admin/finance/*/delete/{id}`.
- **What:** A GET carries no CSRF token, so `<img src="…/admin/class/delete/5">` on any page an admin visits silently deletes records.
- **⚠️ This actually bit us during the audit:** the parameterized‑route smoke test (`smoke_params.sh`) issued `GET …/delete/1` while authenticated, which **deleted every `id=1` row**: the superadmin user, `exam_category` "CAT 1", and class "Certificate Year 1" with its sections/subjects. All were reconstructed.
- **Fix applied:** (1) `resources/views/modal.blade.php` — `confirmModal()` now calls a new `postDelete(url)` helper that submits a CSRF‑protected POST form instead of setting the confirm button's `href` (fixes all 69 stock delete buttons at once). (2) All 15 custom‑module `<a href="{{ route('*.delete') }}">` links converted to `onclick="if(confirm(...)) postDelete('…')"`. (3) All 57 `Route::get('*delete*')` flipped to `Route::post` in `routes/web.php` + `routes/Addon/*`. Route **names** unchanged, so every `route()` call in views still resolves.
- **Verified:** `GET /admin/class/delete/{id}` → **405** (record survives); `POST` with CSRF token → **302** (deletes). Drive‑by GET can no longer delete anything; UI buttons still work.

---

# 🔵 Low / robustness

- **L1 — AJAX `/list` endpoints 500 without params** ✅ — `admin/marks/list`, `admin/gradebook/list`, `admin/syllabus/list`, `admin/routine/list`, and the `teacher/*` equivalents read query params with no guards. Confirmed: they return **200 when given params** (the filter forms always supply them), so a normal user doesn't hit it — but the directly‑reachable GET 500s and should default its inputs. (Smoke‑test flagged: also `admin/attendance/*`, `admin/admitCardFilter`, `admin/subscription/*`, `admin/upgrade_subscription`, `teacher/attendance/*`, `teacher/class_wise_section_for_syllabus`, `teacher/syllabus_details`, `parent/child/*`, `parent/feedback-list`, `parent/attendance/csv`, `student/attendance/csv` — same "needs params" class.)
- **L2 — Deleted‑reference 500s in legacy per‑day routine views** — `teacher/routine/routine_list.blade.php:86,159,180` and orphan `student|parent/routine/routine_list.blade.php` use `Subject::find(...)->name` etc. The new `partials/timetable.blade.php` is null‑safe; these old files weren't retired. Fix: `optional(...)->name` or delete the orphans.
- **L3 — `foreach` over null gradebook marks** — `admin|teacher/gradebook/gradebook.blade.php:124` `json_decode($student->marks,true)` on a nullable column → warning + broken row. Fix: `?: []`.
- **L4 — Gradebook download JS crashes when unfiltered** — `admin/gradebook/gradebook.blade.php:200` binds `getElementById("download-button")` that only renders after filtering → null `addEventListener` aborts the inline script.
- **L5 — `user/{id}` name enumeration cross‑tenant** ✅ — `CommonController::idWiseUserName` (`auth`‑only, unscoped) lets any user harvest every user's name by id. Fix: scope to `school_id`.
- **L6 — Discount/fine can't be cleared** — `FinanceController recordPayment` keeps the old value when the field is blanked (`$request->filled()`); no way to remove a fine/discount via UI.
- **L7 — Accountant self‑payroll (segregation of duties)** — role 4 passes `AdminAccountantMiddleware` and appears in `staffQuery()`, so an accountant can create + pay their own salary (posts to ledger). Fix: exclude self / gate approval to admin.
- **L8 — Parent `routineList` orphan route** — `ParentController.php:305` `Enrollment::…->first()->toArray()` no null guard, no parent scoping. Best removed.
- **L9 — Ineffective `!empty()` on a Collection** — `ParentController.php:322` always truthy; misleading dead guard.
- **L10 — `register` mass‑assignment latent risk** ✅ — `role_id` in `$fillable` (see H4).
- **L11 — SuperAdmin non‑match redirect has no error flash** — weak UX, not a hole.
- **L12 — Stock CRUD detail/edit/modal endpoints 500 on a stale/deleted/invalid id** ✅ — the stock Ekattor endpoints (`admin/class/{id}`, `admin/subject/{id}`, `admin/grade/{id}`, `admin/noticeboard/{id}`, `admin/book/{id}`, `admin/student/student_profile/{id}`, `admin/student/edit/{id}`, `admin/student/id_card/{id}`, `*/message/all-message/{id}`, `accountant/expenses/{id}`, `librarian/book/{id}`, etc.) call `find($id)`/`get_student_details_by_id($id)` with no null guard, so a deleted/stale/bookmarked id throws a raw 500 ("Attempt to read property … on null") instead of a graceful 404. Verified: with **valid** ids these all return 200 (`admin/class/2`, `admin/subject/6`, `admin/grade/2`, `admin/student/student_profile/133`), so a normal user clicking real rows is unaffected — this is a robustness/UX gap, not a routine crash. Notably the **custom‑built modules (assignments, quiz, courses, finance) already handle this correctly** — they `firstOrFail()` and return a clean 404 (e.g. `admin/finance/structure/show/{bad}` → 404, `student/quiz/take/{bad}` → 404). Fix: add null guards / `findOrFail` to the stock endpoints.

---

# Per‑portal smoke‑test results

| Portal | Login | Broken pages found | Notes |
|---|---|---|---|
| Super Admin | ✅ 200 | `admin_password` 302 (M11) | Otherwise clean across 20 GET pages |
| School Admin | ✅ 200 | `admin/student` (**FIXED**), `complain/complainList` (H7); ~12 param‑needed AJAX endpoints (L1) | Core pages OK once params supplied |
| Teacher | ✅ 200 | `gradebook/list` (H5), `gradebook/subject_marks` (H6); ~6 param‑needed AJAX (L1) | |
| Accountant | ✅ 200 | none | All 29 finance/accounting GET pages returned 200 |
| Librarian | ✅ 200 | none | All 9 GET pages returned 200 |
| Parent | ✅ 200 | IDOR (C3–C5); `child/subject/list`, `child/syllabus/list`, `feedback-list`, `marks/list`, `routine/list`, `attendance/csv` need params (L1) | |
| Student | ✅ 200 | `complain/complain`, `complain/complainUser` (H7); `attendance/csv` needs params (L1) | Quiz timer/deadline logic bypass C8 |

---

# ✅ Fixed during this audit

- **Admin student list crash** — `resources/views/admin/student/student_list.blade.php:311` used `$student->user_id` (a `users` row has `id`, not `user_id`), introduced with the Transcript link. The entire Students page 500'd. Changed to `$student->id`. Verified `GET /admin/student` now returns **200**.

---

# Recommended fix order

1. **C1, C2, C3, C4, C5, C6** — the horizontal‑access / grade‑write holes; all exploitable today by a logged‑in student or parent. Add ownership checks + move `mark/update` under a role guard.
2. **C7** — add upload validation (RCE risk).
3. **C8, H12** — enforce the exam timer/deadline server‑side and add the `assignment_submissions` unique index + `section_id` check.
4. **H1, H2, H3, H4** — auth hardening (middleware order, disabled‑login, `/clear-cache`, disable `register`).
5. **H5–H9, H7** — create the missing views / null‑guards so the broken pages stop 500ing.
6. **H10, H11, M1, M2** — finance correctness + cross‑school scoping.
7. Remaining Medium/Low as capacity allows.

## Verified‑clean areas (checked, no bug found)

- Transcript module (`TranscriptController` + views): `$logoPath` always passed, divide‑by‑zero guarded, cross‑school blocked via `firstOrFail()` scoped by `school_id`, student methods use `auth()->id()` (no tamperable param).
- Student marks & syllabus (`StudentController`): scoped to `auth()->id()`/`school_id` with `abort_if` on class mismatch.
- Finance ledger consistency: fees, expenses, incomes, projects, payslips all post to `finance_transactions` and clean up on delete; transfers correctly excluded (cash‑neutral).
- Quiz auto‑grading correctness: MCQ index vs value, true/false casing, short‑answer trim/case all consistent; deleted‑question links null‑guarded.
- All models use `$guarded = []` with columns matching the live schema (no `$fillable` mismatch); all `route()` names referenced in the audited views resolve.
