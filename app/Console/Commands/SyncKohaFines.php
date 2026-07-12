<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Koha;

/**
 * Pull outstanding Koha fines (patron account debits) into the app finance module
 * as unpaid invoices, so they show on the student/parent fee view and are payable
 * through the existing fee-payment flow (which posts the ledger row on payment).
 * Idempotent by koha_accountline_id (koha_fine_sync table).
 *
 *   php artisan koha:sync-fines
 *   php artisan koha:sync-fines --user=133
 */
class SyncKohaFines extends Command
{
    protected $signature = 'koha:sync-fines {--user=}';
    protected $description = 'Mirror outstanding Koha fines into app invoices (finance)';

    public function handle(Koha $koha): int
    {
        if (!$koha->isConfigured()) { $this->error('Koha not configured.'); return 1; }

        $maps = DB::table('koha_borrower_map')->whereNotNull('koha_borrowernumber');
        if ($this->option('user')) $maps->where('user_id', (int) $this->option('user'));
        $maps = $maps->get();

        $created = $skipped = 0;
        foreach ($maps as $m) {
            $student = DB::table('users')->where('id', $m->user_id)->first();
            if (!$student) continue;
            $enroll = DB::table('enrollments')->where('user_id', $m->user_id)->orderByDesc('session_id')->first();

            foreach ($koha->accountDebits($m->koha_borrowernumber) as $d) {
                $lineId      = $d['account_line_id'] ?? $d['accountlines_id'] ?? null;
                $outstanding = (float) ($d['amount_outstanding'] ?? 0);
                if (!$lineId || $outstanding <= 0) { $skipped++; continue; }

                // already imported?
                if (DB::table('koha_fine_sync')->where('koha_accountline_id', $lineId)->exists()) { $skipped++; continue; }

                $desc      = $d['description'] ?: ('Library ' . strtolower($d['debit_type'] ?? 'fine'));
                $sessionId = $enroll->session_id ?? (get_school_settings($student->school_id)->value('running_session') ?? 0);

                $invoiceId = DB::table('invoices')->insertGetId([
                    'school_id'    => $student->school_id,
                    'session_id'   => $sessionId,
                    'student_id'   => $m->user_id,
                    'class_id'     => $enroll->class_id ?? 0,
                    'section_id'   => $enroll->section_id ?? 0,
                    'invoice_no'   => 'LIB-FINE-' . $lineId,
                    'title'        => 'Library fine: ' . $desc,
                    'total_amount' => $outstanding,
                    'fine'         => 0,
                    'paid_amount'  => 0,
                    'balance'      => $outstanding,
                    'status'       => 'unpaid',
                    'due_date'     => time(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                DB::table('invoice_items')->insert([
                    'invoice_id' => $invoiceId,
                    'title'      => $desc,
                    'amount'     => $outstanding,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('koha_fine_sync')->insert([
                    'user_id'             => $m->user_id,
                    'koha_accountline_id' => $lineId,
                    'amount'              => $outstanding,
                    'invoice_id'          => $invoiceId,
                    'synced_at'           => time(),
                ]);
                $created++;
            }
        }

        $this->info("Fines sync complete — invoices_created=$created skipped=$skipped (patrons checked=" . $maps->count() . ")");
        return 0;
    }
}
