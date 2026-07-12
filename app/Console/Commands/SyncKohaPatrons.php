<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Koha;

/**
 * Push app users (students + staff) into Koha as borrowers, keeping a
 * user_id <-> koha_borrowernumber map. Idempotent: updates if already mapped
 * or already present by cardnumber, otherwise creates.
 *
 *   php artisan koha:sync-patrons               # all students + staff
 *   php artisan koha:sync-patrons --limit=5     # first 5 (smoke test)
 *   php artisan koha:sync-patrons --role=7      # students only
 *   php artisan koha:sync-patrons --user=133    # a single user
 */
class SyncKohaPatrons extends Command
{
    protected $signature = 'koha:sync-patrons {--limit=0} {--role=} {--user=}';
    protected $description = 'Create/update app users as Koha borrowers (patron sync)';

    public function handle(Koha $koha): int
    {
        if (!$koha->isConfigured()) {
            $this->error('Koha is not configured (koha_base_url / credentials missing).');
            return 1;
        }

        $branch     = get_settings('koha_library_branch');
        $catStudent = get_settings('koha_patron_category_student');
        $catStaff   = get_settings('koha_patron_category_staff');
        if (!$branch || !$catStudent || !$catStaff) {
            $this->error('Set koha_library_branch / koha_patron_category_student / koha_patron_category_staff first.');
            return 1;
        }

        // students (7) + staff (admin/teacher/accountant/librarian). Parents (6) excluded.
        $roles = $this->option('role') ? [(int) $this->option('role')] : [7, 2, 3, 4, 5];

        $q = DB::table('users')->whereIn('role_id', $roles)->orderBy('id');
        if ($this->option('user'))  $q->where('id', (int) $this->option('user'));
        if ((int) $this->option('limit') > 0) $q->limit((int) $this->option('limit'));
        $users = $q->get();

        $created = $updated = $failed = 0;
        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $u) {
            $body = $this->buildBody($u, $branch, $catStudent, $catStaff);

            $map  = DB::table('koha_borrower_map')->where('user_id', $u->id)->first();
            $borrowernumber = $map->koha_borrowernumber ?? null;

            // Not mapped yet — maybe already in Koha under this cardnumber.
            if (!$borrowernumber) {
                $existing = $koha->findPatronByCardnumber($body['cardnumber']);
                if ($existing) $borrowernumber = $existing['patron_id'] ?? null;
            }

            if ($borrowernumber) {
                $r = $koha->updatePatron($borrowernumber, $body);
                if ($r['ok']) { $updated++; }
                else { $failed++; $this->warnRow($u, 'update', $r); $bar->advance(); continue; }
            } else {
                $r = $koha->createPatron($body);
                if ($r['ok'] && isset($r['body']['patron_id'])) {
                    $borrowernumber = $r['body']['patron_id'];
                    $created++;
                } else {
                    // duplicate cardnumber race — look it up and map instead of failing
                    $existing = $koha->findPatronByCardnumber($body['cardnumber']);
                    if ($existing && isset($existing['patron_id'])) {
                        $borrowernumber = $existing['patron_id'];
                        $updated++;
                    } else {
                        $failed++; $this->warnRow($u, 'create', $r); $bar->advance(); continue;
                    }
                }
            }

            DB::table('koha_borrower_map')->updateOrInsert(
                ['user_id' => $u->id],
                ['koha_borrowernumber' => $borrowernumber, 'koha_cardnumber' => $body['cardnumber'],
                 'synced_at' => time(), 'updated_at' => now(), 'created_at' => $map->created_at ?? now()]
            );
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Koha patron sync complete — created=$created updated=$updated failed=$failed total=" . $users->count());
        return 0;
    }

    private function buildBody($u, string $branch, string $catStudent, string $catStaff): array
    {
        $name  = trim((string) $u->name) ?: 'Unknown';
        $parts = preg_split('/\s+/', $name);
        if (count($parts) >= 2) { $surname = array_pop($parts); $firstname = implode(' ', $parts); }
        else                    { $surname = $name; $firstname = ''; }

        $isStudent = (int) $u->role_id === 7;
        $card = $isStudent ? ($u->code ?: ('STU-' . $u->id)) : ('STAFF-' . $u->id);

        $info = json_decode($u->user_information ?? '{}', true) ?: [];

        $body = [
            'surname'     => $surname,
            'firstname'   => $firstname !== '' ? $firstname : null,
            'cardnumber'  => $card,
            'library_id'  => $branch,
            'category_id' => $isStudent ? $catStudent : $catStaff,
        ];
        if (!empty($u->email))       $body['email']         = $u->email;
        if (!empty($info['phone']))  $body['phone']         = (string) $info['phone'];
        if (!empty($info['address']))$body['address']       = $info['address'];
        if (!empty($info['birthday']) && is_numeric($info['birthday']))
                                     $body['date_of_birth'] = date('Y-m-d', (int) $info['birthday']);
        return $body;
    }

    private function warnRow($u, string $op, array $r): void
    {
        $msg = is_array($r['body']) ? json_encode($r['body']) : (string) $r['body'];
        $this->warn("  {$op} failed for user {$u->id} ({$u->name}) [{$r['status']}]: " . substr($msg, 0, 200));
    }
}
