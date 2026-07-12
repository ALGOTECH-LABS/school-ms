<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Koha;

/**
 * One-time catalog bootstrap: push the app's existing `books` into Koha as bib
 * records + items so Koha (the source of truth) has a catalog to work from.
 * Records the returned biblionumber on books.koha_biblionumber.
 *
 *   php artisan koha:push-catalog            # up to 3 items per book
 *   php artisan koha:push-catalog --copies=1
 */
class PushKohaCatalog extends Command
{
    protected $signature = 'koha:push-catalog {--copies=3 : max items (copies) to create per book}';
    protected $description = 'Push app books into Koha as bib records + items (one-time catalog load)';

    public function handle(Koha $koha): int
    {
        if (!$koha->isConfigured()) {
            $this->error('Koha is not configured.');
            return 1;
        }
        $branch    = get_settings('koha_library_branch') ?: 'KHMTC';
        $maxCopies = max(1, (int) $this->option('copies'));

        $books = DB::table('books')->whereNull('koha_biblionumber')->orderBy('id')->get();
        if ($books->isEmpty()) {
            $this->info('All books already pushed (koha_biblionumber set).');
            return 0;
        }

        $created = $items = $failed = 0;
        $bar = $this->output->createProgressBar($books->count());
        $bar->start();

        foreach ($books as $b) {
            $fields = [
                ['245' => ['ind1' => '0', 'ind2' => '0', 'subfields' => [['a' => $b->name]]]],
                ['100' => ['ind1' => '1', 'ind2' => ' ', 'subfields' => [['a' => $b->author ?: 'Unknown']]]],
                ['942' => ['ind1' => ' ', 'ind2' => ' ', 'subfields' => [['c' => 'BK']]]],
            ];
            if (!empty($b->isbn)) {
                $fields[] = ['020' => ['ind1' => ' ', 'ind2' => ' ', 'subfields' => [['a' => $b->isbn]]]];
            }
            $marc = ['leader' => '00000nam a2200000 a 4500', 'fields' => $fields];

            $bn = $koha->createBiblio($marc);
            if (!$bn) { $failed++; $this->warn("  biblio create failed: {$b->name}"); $bar->advance(); continue; }

            DB::table('books')->where('id', $b->id)->update(['koha_biblionumber' => $bn, 'source' => 'koha']);
            $created++;

            $n = min((int) $b->copies, $maxCopies);
            for ($i = 1; $i <= $n; $i++) {
                $r = $koha->addItem($bn, [
                    'home_library_id'    => $branch,
                    'holding_library_id' => $branch,
                    'item_type_id'       => 'BK',
                    'external_id'        => "KH-{$b->id}-{$i}",
                ]);
                if ($r['ok']) $items++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Catalog push complete — biblios=$created items=$items failed=$failed");
        return 0;
    }
}
