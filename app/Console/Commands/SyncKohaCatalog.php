<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Koha;

/**
 * Reverse catalog mirror: pull Koha bib records into the app's `books` table
 * (read-only mirror) so catalogue changes made IN Koha (new titles, edits,
 * added copies) show up on the app's library screens. Keyed by koha_biblionumber.
 *
 *   php artisan koha:sync-catalog
 */
class SyncKohaCatalog extends Command
{
    protected $signature = 'koha:sync-catalog';
    protected $description = 'Mirror Koha bib records into app books (reverse catalog mirror)';

    public function handle(Koha $koha): int
    {
        if (!$koha->isConfigured()) { $this->error('Koha not configured.'); return 1; }

        $schoolId = (int) (DB::table('schools')->orderBy('id')->value('id') ?? 1);
        $session  = get_school_settings($schoolId)->value('running_session') ?? 0;

        $page = 1; $created = $updated = 0;
        do {
            $biblios = $koha->biblios($page, 100);
            foreach ($biblios as $b) {
                $bn = $b['biblio_id'] ?? null;
                if (!$bn) continue;

                $copies = count($koha->biblioItems($bn));
                $row = [
                    'name'       => $b['title'] ?: 'Untitled',
                    'author'     => $b['author'] ?? '',
                    'isbn'       => $b['isbn'] ?? null,
                    'copies'     => $copies,
                    'school_id'  => $schoolId,
                    'session_id' => $session,
                    'source'     => 'koha',
                    'timestamp'  => time(),
                    'updated_at' => now(),
                ];

                $existing = DB::table('books')->where('koha_biblionumber', $bn)->first();
                if ($existing) { DB::table('books')->where('id', $existing->id)->update($row); $updated++; }
                else { DB::table('books')->insert($row + ['koha_biblionumber' => $bn, 'created_at' => now()]); $created++; }
            }
            $page++;
        } while (count($biblios) === 100);

        $this->info("Catalog mirror complete — created=$created updated=$updated");
        return 0;
    }
}
