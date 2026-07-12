# Plan: BigBlueButton live classes + recordings-into-course

## Context

The app already has an **"Online Sessions"** feature on each course (built earlier): `course_sessions` rows with a `platform` (zoom/meet/teams/other) and a teacher-pasted `meeting_url`, shown as a "Join" button on the teacher **Manage → Online Sessions** tab and the student course sidebar. The user wants real **live online classes powered by BigBlueButton (BBB)**, with **session recordings flowing back into the course** (a "Watch recording" button on ended sessions **and** auto-added as a video material in the course content).

**Hard infra constraint (confirmed with user):** BBB is a full WebRTC/FreeSWITCH media stack and **cannot run in Docker on macOS** (no host networking; needs HTTPS + a domain). The BBB *server* must live on a Linux host. Therefore this plan builds the **complete app-side integration + settings + a public recording webhook**, and ships **docker-compose/setup docs** so the user runs BBB on a Linux VM and pastes its URL + shared secret into the app. Everything except live A/V can be built and tested on this Mac.

Chosen options: **Integration + Linux compose docs**; recordings appear **on the session + in course content**.

## How BBB works (reference for implementation)

- API base: `<server_url>/api/`. Every call is `<endpoint>?<query>&checksum=HASH(<endpoint><query><shared_secret>)` where HASH is SHA-1 or SHA-256 (server-configured; make it a setting).
- `create` (idempotent): starts/ensures a meeting. Pass `record=true`, `meta_bbb-recording-ready-url=<our webhook>`, plus `attendeePW`/`moderatorPW` (works across BBB versions).
- `join?fullName&meetingID&password&redirect=true` → returns the URL the browser navigates to (we redirect the user to it). Moderator = teacher, viewer = student.
- `getRecordings?meetingID` → XML; when a recording's `state=published`, its `playback.format.url` is the playback page URL.
- Recording delivery: BBB POSTs a "recording ready" callback to `meta_bbb-recording-ready-url` when processing finishes; we also expose a manual "Fetch recording" pull + an artisan command (since the app has no queue/scheduler running by default — `QUEUE_CONNECTION=sync`, empty scheduler).

## Files & changes

### 1. DB — extend `course_sessions` (raw SQL, project convention is `docker exec … mysql`, NOT artisan migrate)
New file `database/bbb.sql` adding to `course_sessions`:
- `bbb_meeting_id` VARCHAR(191) NULL (unique per session, e.g. `khmtc-<id>-<rand>`)
- `bbb_attendee_pw` VARCHAR(64) NULL, `bbb_moderator_pw` VARCHAR(64) NULL
- `recording_url` VARCHAR(1000) NULL, `recording_state` VARCHAR(20) NULL (none|processing|published)

Model `app/Models/CourseSession.php` is `$guarded = []` so no fillable change; add a `getHasRecordingAttribute()` accessor. Add **`bbb`** as a supported `platform` value (no schema change — it's a varchar).

### 2. BBB API client — new `app/Services/BigBlueButton.php`
Reads `get_settings('bbb_server_url')`, `get_settings('bbb_shared_secret')`, `get_settings('bbb_checksum_algo')` (default `sha1`; expose sha256). `get_settings()` is in `app/Helpers/CommonHelper.php:188` (autoloaded via composer `files`). Methods:
- `isConfigured()` — both server_url + secret present.
- `buildUrl($endpoint, array $params)` — query + checksum (uses `http_build_query` + `hash($algo, ...)`).
- `createMeeting(CourseSession $s)` — idempotent create with `record=true` + recording-ready callback meta + `meta_khmtc_session_id`.
- `joinUrl(CourseSession $s, string $fullName, string $role)` — ensures created, returns signed join URL (`role` → moderator/viewer password).
- `getRecordings(string $meetingId): array` — returns `[{url, state}]` (parse XML via `simplexml_load_string`).
- Uses `Illuminate\Support\Facades\Http` (Guzzle ^7 is present). All calls guarded by `isConfigured()`.

### 3. Controller — `app/Http/Controllers/OnlineCourseController.php`
- **`sessionStore`** (currently ~L267): when `platform === 'bbb'`, make `meeting_url` optional, generate `bbb_meeting_id` + random attendee/moderator passwords, save. Keep existing paste-link behaviour for zoom/meet/teams/other.
- **`sessionJoin($id)`** (teacher, new): `ownedCourse` check → `$bbb->createMeeting` → redirect to `joinUrl(..., 'moderator')` with the teacher's name. Friendly error if `!isConfigured()`.
- **`studentSessionJoin($id)`** (student, new): reuse `studentView`'s access checks (enrolled in course's class **and** not in `course_removals` status=removed) → create → redirect `joinUrl(..., 'viewer')`. Only when session is upcoming/live.
- **`sessionFetchRecording($id)`** (teacher, new): `getRecordings` → if published, set `recording_url`/`recording_state` and call the attach-to-course helper. Idempotent.
- **`bbbRecordingWebhook(Request)`** (public, new): look up session by `bbb_meeting_id` from the callback payload, fetch + save recording, return 200. No auth.
- **Helper `attachRecordingToCourse($session)`**: find-or-create a `CourseTopic` "Class Recordings" on the course → find-or-create a `CourseLesson` "Recorded sessions" → create a `CourseMaterial` (`type='link'`, `title` = session title + date, `url` = recording_url) if one with that url doesn't already exist. Reuses the existing `CourseMaterial` model + the student view's material renderer (`resources/views/student/courses/view.blade.php:126`). `type='link'` (not video) because BBB playback pages set X-Frame-Options and don't embed like YouTube.

### 4. Routes
`routes/Addon/onlineCourse.php` (teacher group): `GET teacher/addons/courses/session/join/{id}` → `sessionJoin` (`teacher.addons.course.session.join`); `POST teacher/addons/courses/session/fetch-recording/{id}` → `sessionFetchRecording`. (student group): `GET student/addons/courses/session/join/{id}` → `studentSessionJoin` (`student.addons.course.session.join`).
Public webhook: add `POST bbb/recording-ready` → `OnlineCourseController@bbbRecordingWebhook` at the **top-level** of `routes/web.php` (outside auth groups), name `bbb.recording_ready`. Add `'bbb/recording-ready'` to `$except` in `app/Http/Middleware/VerifyCsrfToken.php` (join the existing paytm-callback entries).

### 5. Views
- `resources/views/teacher/courses/manage.blade.php` (Online Sessions tab, ~L281): add **BigBlueButton** as the first `platform` option (and to the `$platformMeta` map: icon `bi-camera-video-fill`, green); when a session's platform is `bbb`, the **Join** button points to the new `session.join` route (server-managed) instead of `meeting_url`; hide the "Meeting link" input when BBB is chosen (small JS toggle). Past sessions: show **Watch recording** (if `recording_url`) + a **Fetch recording** button (posts to `session.fetch-recording`).
- `resources/views/student/courses/view.blade.php` (Live sessions card, ~L168): for `bbb` sessions the Join button uses `student…session.join`; add a small **Recordings** list for `$past` sessions that have `recording_url` (studentView already passes `$past`). Recordings also appear automatically in the lessons/materials (from the attach helper).
- `resources/views/superadmin/settings/system_settings.blade.php`: add a **"Live Classes (BigBlueButton)"** fieldset — `bbb_server_url`, `bbb_shared_secret` (masked), `bbb_checksum_algo` (sha1/sha256 select). The existing `SuperAdminController::systemUpdate` already `updateOrInsert`s any posted key into `global_settings`, so no controller change is needed.

### 6. Recording sync fallback — new `app/Console/Commands/SyncBbbRecordings.php`
`bbb:sync-recordings` loops sessions where `platform='bbb'`, ended, and `recording_state != 'published'`, calls `getRecordings`, saves + attaches. Documented for cron (`* * * * * php artisan schedule:run` + a `->everyFiveMinutes()` entry, or direct cron). Complements the webhook.

### 7. Setup docs — new `BIGBLUEBUTTON-SETUP.md` (repo root)
- Why BBB can't run on the Mac; run it on **Ubuntu 22.04, ≥8 GB RAM / 4 vCPU, public IP + domain + TLS**.
- **docker-compose route:** use the maintained `bigbluebutton/docker` project — exact `git clone`, `./scripts/setup`, set domain/email, `docker compose up -d` steps; where to read the generated `SHARED_SECRET`. (We don't hand-roll BBB's ~15-service compose — it's version-specific and would rot; we document the maintained one.)
- **Alternative:** official `bbb-install.sh` on a bare server; `bbb-conf --secret` to get URL+secret.
- Enabling recordings + the recording-ready callback; then paste **server URL + secret** into the app's Live Classes settings and set the checksum algorithm to match.

### 8. Seed empty settings rows
Insert empty `bbb_server_url` / `bbb_shared_secret` / `bbb_checksum_algo=sha1` rows into `global_settings` so the settings screen renders (via the same `docker exec … mysql`).

## Verification

On this Mac (no live A/V, no real BBB server):
1. Run `database/bbb.sql`; confirm new columns via `docker exec … mysql -e "DESCRIBE course_sessions"`.
2. **Settings:** save BBB server URL/secret in SuperAdmin System Settings; confirm `get_settings('bbb_server_url')` returns them.
3. **Checksum/URL unit check (tinker):** with a known secret, assert `BigBlueButton::buildUrl('create', [...])` produces the correct `checksum=` (compare against a hand-computed SHA-1/256) — proves the API signing is correct.
4. **Schedule:** create a `platform='bbb'` session via the teacher form (curl, logged in as teacher1); confirm `bbb_meeting_id` + passwords saved and no `meeting_url` required.
5. **Join URL:** hit `teacher…session.join` — with BBB unconfigured, assert a friendly error; point `bbb_server_url` at the **BBB public test/HTML5 demo server** (or the user's real server) and assert it redirects to a signed `join?...&checksum=...` URL.
6. **Webhook + recording→course:** POST a sample recording-ready payload to `bbb/recording-ready` (CSRF-exempt) for a known `bbb_meeting_id`; assert the handler stores `recording_url` and that `attachRecordingToCourse` created exactly one `CourseMaterial` under a "Class Recordings" topic (and is idempotent on a second call). Verify "Watch recording" shows on the ended session (teacher + student) and the material appears in the student course content.
7. **Live end-to-end (documented, requires the Linux BBB server):** teacher Join → BBB room → student Join → end → recording auto-appears via webhook (or `php artisan bbb:sync-recordings`).

Then a Chrome pass as teacher + student to confirm the Join buttons and recording links render.
