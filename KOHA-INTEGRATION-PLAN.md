# Plan: Koha ILS deep integration (Docker-only, on this Mac)

## Context

The app has a **built-in library module** (`books`, `book_issues`, member/issue flows, librarian + student/parent screens). The user wants to integrate **Koha** — the mature open-source Integrated Library System — as the real library engine, with a **deep two-way** integration:

- **Patron sync**: app users (students + staff) pushed into Koha as borrowers.
- **Catalog search in-app**: query Koha's catalog from inside the portals.
- **Circulation + catalog mirror**: Koha is the source of truth, but its catalog + checkouts are mirrored (read-only) back into the existing `books`/`book_issues` tables so current screens keep working ("keep both, mirror into app").
- **Fines → finance**: Koha borrower fines flow into the app's finance ledger and show on the student/parent fee views.

**Decisions (confirmed):** deep two-way · **Koha runs in Docker on this Mac** (no Linux server) · keep both + mirror.

## Reality / constraints (must design around)

- **Apple Silicon (arm64)**; Koha images are **amd64 → run emulated** (slower). **Docker currently 4 GB / 3 CPU with ~14 containers running** — Koha (Perl/Starman + MariaDB + indexer + memcached) needs more. **User must raise Docker Desktop memory to ≥8 GB / 4 CPU** before Phase 0. To cut load we run Koha with the **Zebra** indexer (not Elasticsearch).
- App has **no queue/scheduler running** (`QUEUE_CONNECTION=sync`, empty `app/Console/Kernel.php`). So syncs are **artisan commands** triggered by manual "Sync" buttons + documented cron; not background jobs.
- Settings/secrets live in `global_settings` (`get_settings('key')`, `app/Helpers/CommonHelper.php`); `SuperAdminController::systemUpdate` already `updateOrInsert`s any posted key — so new Koha settings need no controller change.
- Guzzle ^7 + `Http` facade available; follow the `app/Services/BigBlueButton.php` service pattern (from the parked BBB plan) for `app/Services/Koha.php`.

## Koha surfaces we use

- **REST API** `<koha_url>/api/v1/` — `patrons` (create/update borrowers), `checkouts` (per-patron), `patrons/{id}/account` (fines/balance), `biblios` / `public/biblios` (catalog). Auth: **OAuth2 client-credentials** (an API-enabled patron's client_id/secret) — token endpoint `/api/v1/oauth/token`.
- **OPAC** public site — deep-link students/librarians to catalog detail / their account (link-out; true SSO via LDAP/CAS is out of scope for the demo).
- **Z39.50/SRU** available as a fallback for catalog search if REST search is too limited on the installed version.

## Phased plan

### Phase 0 — Stand up Koha in Docker (foundational, riskiest)
- Use **koha-testing-docker (ktd)** configured with **Zebra** to fit RAM (exact image tag + flags confirmed live during setup — arm/emulation makes this finicky).
- Bring up the stack; create a Koha **instance**, a library branch, patron categories (Student/Staff), and **enable the REST API**; create an **API user** (patron with API/OAuth2 client credentials + circulation/borrowers/catalog permissions).
- Seed a few MARC bib records (or import the app's 18 seeded books) so there's catalog data.
- Confirm from the host: `curl <koha_url>/api/v1/...` returns data with a bearer token.
- Deliverable: `KOHA-SETUP.md` with the exact stand-up + API-user steps + caveats (arm64 emulation, RAM, Zebra).

### Phase 1 — Config + `Koha` service client
- `database/koha.sql`: seed empty `global_settings` rows — `koha_base_url`, `koha_opac_url`, `koha_api_client_id`, `koha_api_client_secret`, `koha_library_branch`, `koha_patron_category_student`, `koha_patron_category_staff`.
- SuperAdmin System Settings (`resources/views/superadmin/settings/system_settings.blade.php`): add a **"Library (Koha)"** fieldset for the above (secret masked). No controller change (systemUpdate saves any key).
- `app/Services/Koha.php`: `isConfigured()`, OAuth2 `token()` (cached), `getPatron`, `createPatron`, `updatePatron`, `listCheckouts($borrowernumber)`, `getAccount($borrowernumber)` (fines), `searchBiblios($q)`, `getBiblio($id)`. Guzzle/`Http`; all guarded by `isConfigured()`.

### Phase 2 — Patron sync (app users → Koha borrowers)
- Schema: new **`koha_borrower_map`** table `(user_id, koha_borrowernumber, koha_cardnumber, synced_at)` — a side table (NOT a users column) because **staff have no `users.code`** and we must store the borrowernumber Koha returns.
- Field mapping (from recon): `cardnumber` ← `users.code` for students (unique, non-null); **staff get a synthesized card `STAFF-{id}`**. `surname`/`firstname` ← **parse the single `users.name`**. `email` ← `users.email`. `phone`/`address`/`dateofbirth` ← **`json_decode(users.user_information)`** → `phone`/`address`/`birthday` (birthday is a **unix ts** → `date()`). `branchcode` ← from `school_id`; `categorycode` ← from `role_id` (7 = Student category, else Staff).
- `app/Console/Commands/SyncKohaPatrons.php` (`koha:sync-patrons`) + an admin **"Sync users to Koha"** button: for each student (role_id 7) + staff, create/update the Koha borrower via `/patrons`, upsert into `koha_borrower_map`. Idempotent (update if already mapped).

### Phase 3 — Catalog search embedded in-app
- `KohaController@catalog` + a **"Library Catalog"** page in librarian + student navs: query `Koha::searchBiblios($q)`, render results (title/author/availability + OPAC detail link). Optional "Place hold" via REST for logged-in students.

### Phase 4 — Circulation + catalog mirror into built-in module
- Schema additions (the module has none of these today): `books` + `koha_biblionumber`, `isbn`, `barcode`, `source`; `book_issues` + `koha_checkout_id`, `due_date` (int), `fine` (decimal), `source`. (Existing `books`: name/author/copies/school_id/session_id/timestamp; `book_issues`: book_id/class_id/student_id/issue_date[unix]/status/school_id/session_id/timestamp.)
- `koha:sync-catalog` — upsert Koha biblios → `books` (title→name, author, copies, isbn, `koha_biblionumber`, `source='koha'`), read-only mirror.
- `koha:sync-circulation` — upsert Koha checkouts for mapped patrons → `book_issues`, resolving Koha borrowernumber → `student_id` via `koha_borrower_map` and biblio → local `book_id` via `koha_biblionumber`. **Write `status` as `0` = on loan, `1` = returned** (recon confirmed this is inverted from the earlier note — critical for the "Available copies" math and the Pending/Returned badges in `student/book/issued_list.blade.php`). `issue_date` stored as unix (matches existing). Idempotent by `koha_checkout_id`; manual button + documented cron.
- Bonus fix while here: `StudentController::bookIssueList` currently returns **all** school issues, not the logged-in student's — scope it to `student_id = auth()->id()` so a student sees only their own borrowed books.

### Phase 5 — Fines → finance (the "deep" part)
- Schema: `koha_fine_sync` tracking table (`user_id`, `koha_accountline_id`, `amount`, `invoice_id`/`txn_id`, `synced_at`) to prevent double-posting.
- `koha:sync-fines` — pull each mapped patron's Koha account lines (`/patrons/{id}/account`). Represent a new library fine as an **unpaid `invoice` + `invoice_item`** for that `student_id` (the `invoices` table already has a `fine` column + unpaid/paid status), so it shows on the existing **student/parent fee view** and is **payable through the current fee-payment flow** — which already writes the ledger income row on payment (`FinanceController` lines 270–281, using `schoolId()`/`activeSession()`/`ownAccountId()`). Also optionally post an `expense`/receivable marker to `finance_transactions` with `source_type='koha_fine'`, `source_id`=Koha accountline id. Idempotent via `koha_fine_sync`.
- Stretch (documented, optional): recording the fine payment in-app POSTs a credit back to Koha `/patrons/{id}/account/credits` (true two-way settle).

### Phase 6 — Wiring + polish
- Nav entries (Library Catalog, admin "Koha Sync"), a small **Koha status/health** indicator (calls a cheap REST endpoint), sync buttons with last-run timestamps, link-outs to OPAC. Note SSO as future work.

## Data model summary (all via `database/koha.sql` — `docker exec … mysql`, not artisan migrate)
- new **`koha_borrower_map`** `(user_id, koha_borrowernumber, koha_cardnumber, synced_at)`
- new **`koha_fine_sync`** `(user_id, koha_accountline_id, amount, invoice_id, txn_id, synced_at)`
- `books` + `koha_biblionumber`, `isbn`, `barcode`, `source`
- `book_issues` + `koha_checkout_id`, `due_date` (int), `fine` (decimal), `source`
- `global_settings` `koha_*` keys (seed empty rows)
- Model fillable updates: `Book`, `BookIssue` (new columns); new models `KohaBorrowerMap`, `KohaFineSync`.

## Files to create / modify
- **New:** `database/koha.sql`, `app/Services/Koha.php` (**`app/Services/` doesn't exist yet — created fresh**, uses the `Http` facade which is available-but-unused today), `app/Console/Commands/SyncKoha{Patrons,Catalog,Circulation,Fines}.php`, `app/Http/Controllers/KohaController.php`, `app/Models/KohaBorrowerMap.php`, `app/Models/KohaFineSync.php`, `KOHA-SETUP.md`.
- **Modify:** `routes/web.php` (admin/librarian/student Koha routes), `resources/views/superadmin/settings/system_settings.blade.php` (Library-Koha settings fieldset — no controller change, `systemUpdate` saves any key), librarian/student `navigation.blade.php` (catalog + sync links), `StudentController::bookIssueList` (scope to own student — the bonus fix), `app/Models/{Book,BookIssue}.php`. Existing student library/fee views need no change — they'll render the mirrored `book_issues` + the invoice-based fines automatically.

## Verification
1. Phase 0: `curl <koha_url>/api/v1/patrons` with a bearer token returns JSON from the running container.
2. Settings save + `get_settings('koha_base_url')` returns them; `Koha::isConfigured()` true; `Koha::token()` returns a bearer.
3. `php artisan koha:sync-patrons` → a student appears as a Koha borrower (verify via Koha staff UI + REST) and `users.koha_borrowernumber` is set.
4. Catalog page returns Koha search results; OPAC link opens the detail.
5. Issue a book in Koha → `koha:sync-circulation` → it appears in the app's `book_issues` and the student's "my books" screen.
6. Add a fine in Koha → `koha:sync-fines` → a "Library fines" entry appears in `finance_transactions` and on the student/parent finance view; re-running doesn't double-post.
7. Chrome pass: librarian catalog search, student "my books" + fines, admin sync buttons.

## Notes / risks
- **Biggest risk is Phase 0** (Koha up under arm64 emulation on limited RAM). If it won't run acceptably even at 8–12 GB, fallback is a **lightweight mock Koha** (a tiny local stub replicating the REST endpoints) so Phases 1–6 are fully built + demoable, swapping in real Koha later — but the user has asked for real Koha in Docker, so we attempt that first.
- Koha REST coverage varies by version; if the installed version's REST search/patron endpoints are thin, we fall back to Z39.50/SRU (search) and the older `svc`/ILS-DI APIs where needed.
- **Secret storage:** `global_settings.value` is plaintext longtext. Store `koha_api_client_secret` via Laravel `Crypt::encrypt()` (decrypt in the service) or in `.env`, rather than raw plaintext.
- **No parent library view exists today** — if parents should see a child's borrowed books/fines, that's a small new addition (optional; not in the core phases). The fines already surface to parents via the existing parent fee view (invoice-based).
- Recon confirmed **no data collision**: the built-in module has no fine/due/cardnumber fields, so Koha's data lands in the new columns/tables without clobbering anything.
