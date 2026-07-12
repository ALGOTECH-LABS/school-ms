<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Koha;

/**
 * Mirror Koha checkouts into the app's book_issues (read-only mirror) so the
 * existing librarian + student "issued books" screens show Koha circulation.
 * Resolves Koha borrowernumber -> student_id (koha_borrower_map) and biblio ->
 * local book (books.koha_biblionumber). Idempotent by koha_checkout_id.
 *
 *   php artisan koha:sync-circulation
 *   php artisan koha:sync-circulation --user=133
 */
class SyncKohaCirculation extends Command
{
    protected $signature = 'koha:sync-circulation {--user=}';
    protected $description = 'Mirror Koha checkouts into app book_issues';

    public function handle(Koha $koha): int
    {
        if (!$koha->isConfigured()) { $this->error('Koha not configured.'); return 1; }

        $maps = DB::table('koha_borrower_map')->whereNotNull('koha_borrowernumber');
        if ($this->option('user')) $maps->where('user_id', (int) $this->option('user'));
        $maps = $maps->get();

        $synced = $skipped = 0;
        foreach ($maps as $m) {
            $student = DB::table('users')->where('id', $m->user_id)->first();
            if (!$student) continue;
            $enroll = DB::table('enrollments')->where('user_id', $m->user_id)->orderByDesc('session_id')->first();

            foreach ($koha->checkouts($m->koha_borrowernumber) as $co) {
                // checkout carries item_id only -> fetch item to get biblio_id
                $itemId = $co['item_id'] ?? null;
                if (!$itemId) { $skipped++; continue; }
                $item = $koha->getItem($itemId);
                $biblioId = $item['biblio_id'] ?? null;
                if (!$biblioId) { $skipped++; continue; }
                $book = DB::table('books')->where('koha_biblionumber', $biblioId)->first();
                if (!$book) { $skipped++; continue; } // biblio not mirrored locally yet

                $checkoutId = $co['checkout_id'] ?? null;
                $issueDate  = isset($co['checkout_date']) ? strtotime($co['checkout_date']) : time();
                $dueDate    = isset($co['due_date']) ? strtotime($co['due_date']) : null;
                $returned   = !empty($co['checkin_date']);           // status 1 = returned, 0 = on loan

                $row = [
                    'book_id'    => $book->id,
                    'class_id'   => $enroll->class_id ?? 0,
                    'student_id' => $m->user_id,
                    'issue_date' => (string) $issueDate,
                    'status'     => $returned ? 1 : 0,
                    'due_date'   => $dueDate,
                    'school_id'  => $student->school_id,
                    'session_id' => $enroll->session_id ?? (get_school_settings($student->school_id)->value('running_session') ?? 0),
                    'source'     => 'koha',
                    'timestamp'  => time(),
                    'updated_at' => now(),
                ];

                if ($checkoutId) {
                    $existing = DB::table('book_issues')->where('koha_checkout_id', $checkoutId)->first();
                    if ($existing) DB::table('book_issues')->where('id', $existing->id)->update($row);
                    else DB::table('book_issues')->insert($row + ['koha_checkout_id' => $checkoutId, 'created_at' => now()]);
                } else {
                    DB::table('book_issues')->insert($row + ['created_at' => now()]);
                }
                $synced++;
            }
        }

        $this->info("Circulation mirror complete — synced=$synced skipped=$skipped (patrons checked=" . $maps->count() . ")");
        return 0;
    }
}
