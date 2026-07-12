<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Services\Koha;

class KohaController extends Controller
{
    /* ---------------- catalog search (student + librarian + admin) ---------------- */
    public function catalog(Request $request, Koha $koha)
    {
        $q = trim((string) $request->get('q', ''));
        $results = [];
        $configured = $koha->isConfigured();

        if ($configured && $q !== '') {
            $res = $koha->searchBiblios($q, 1, 30);
            foreach ($res['items'] as $b) {
                $results[] = [
                    'biblio_id' => $b['biblio_id'] ?? null,
                    'title'     => $b['title'] ?? '—',
                    'author'    => $b['author'] ?? '',
                    'isbn'      => $b['isbn'] ?? '',
                    'opac_url'  => isset($b['biblio_id']) ? $koha->opacBiblioUrl($b['biblio_id']) : null,
                ];
            }
        }

        return view('koha.catalog', [
            'q'          => $q,
            'results'    => $results,
            'configured' => $configured,
            'opac'       => $koha->opacUrl(),
        ]);
    }

    /* ---------------- admin Koha panel (config summary + sync buttons) ---------------- */
    public function adminPanel(Koha $koha)
    {
        $ping = $koha->isConfigured() ? $koha->ping() : ['ok' => false, 'status' => 0];
        $stats = [
            'mapped'       => (int) \DB::table('koha_borrower_map')->count(),
            'books_pushed' => (int) \DB::table('books')->whereNotNull('koha_biblionumber')->count(),
            'koha_issues'  => (int) \DB::table('book_issues')->where('source', 'koha')->count(),
            'koha_fines'   => (int) \DB::table('koha_fine_sync')->count(),
        ];
        return view('admin.koha.panel', [
            'configured' => $koha->isConfigured(),
            'online'     => (bool) ($ping['ok'] ?? false),
            'settings'   => [
                'base'   => get_settings('koha_base_url'),
                'opac'   => get_settings('koha_opac_url'),
                'user'   => get_settings('koha_api_user'),
                'branch' => get_settings('koha_library_branch'),
            ],
            'stats' => $stats,
        ]);
    }

    /* ---------------- librarian: dashboard ---------------- */
    public function librarianDashboard(Koha $koha)
    {
        $onLoan   = (int) \DB::table('book_issues')->where('status', 0)->count();
        $now = time();
        $overdue  = (int) \DB::table('book_issues')->where('status', 0)->whereNotNull('due_date')->where('due_date', '<', $now)->count();
        $titles   = (int) \DB::table('books')->count();
        $copies   = (int) \DB::table('books')->sum('copies');
        $finesOut = (float) \DB::table('invoices')->where('invoice_no', 'like', 'LIB-FINE-%')->where('status', '!=', 'paid')->sum('balance');
        $recent   = \DB::table('book_issues as bi')->join('books as b', 'b.id', '=', 'bi.book_id')
            ->join('users as u', 'u.id', '=', 'bi.student_id')
            ->where('bi.source', 'koha')->orderByDesc('bi.id')->limit(8)
            ->get(['b.name as book', 'u.name as student', 'bi.issue_date', 'bi.due_date', 'bi.status']);

        return view('librarian.koha.dashboard', compact('onLoan', 'overdue', 'titles', 'copies', 'finesOut', 'recent') + [
            'online' => $koha->isConfigured() && ($koha->ping()['ok'] ?? false),
        ]);
    }

    /* ---------------- librarian: patron lookup ---------------- */
    public function patronLookup(Request $request, Koha $koha)
    {
        $q = trim((string) $request->get('q', ''));
        $patron = null; $checkouts = []; $fines = []; $notFound = false;

        if ($koha->isConfigured() && $q !== '') {
            $patron = $koha->findPatronByCardnumber($q);
            if (!$patron) { // fall back to name search
                $r = $koha->searchPatrons($q);
                $patron = $r[0] ?? null;
            }
            if ($patron) {
                $pid = $patron['patron_id'];
                foreach ($koha->checkouts($pid) as $co) {
                    $item = $koha->getItem($co['item_id'] ?? 0);
                    $co['barcode'] = $item['external_id'] ?? null;
                    $co['title']   = optional(\DB::table('books')->where('koha_biblionumber', $item['biblio_id'] ?? 0)->first())->name;
                    $checkouts[] = $co;
                }
                $acc = $koha->account($pid);
                $fines = $acc['outstanding_debits']['lines'] ?? [];
            } else {
                $notFound = true;
            }
        }

        return view('librarian.koha.patron', [
            'q' => $q, 'patron' => $patron, 'checkouts' => $checkouts, 'fines' => $fines,
            'notFound' => $notFound, 'configured' => $koha->isConfigured(),
            'checkinBase' => $koha->checkinUrl(),
        ]);
    }

    /* ---------------- librarian: issue a book via Koha ---------------- */
    public function doIssue(Request $request, Koha $koha)
    {
        $data = $request->validate([
            'cardnumber' => 'required|string',
            'barcode'    => 'required|string',
        ]);

        $patron = $koha->findPatronByCardnumber($data['cardnumber']);
        if (!$patron) return back()->with('error', get_phrase('No borrower found for card') . ' ' . $data['cardnumber']);

        $item = $koha->getItemByBarcode($data['barcode']);
        if (!$item) return back()->with('error', get_phrase('No item found for barcode') . ' ' . $data['barcode']);

        $res = $koha->checkout($item['item_id'], $patron['patron_id']);
        if (!$res['ok']) {
            $msg = is_array($res['body']) ? ($res['body']['error'] ?? json_encode($res['body'])) : $res['body'];
            return back()->with('error', get_phrase('Koha refused the checkout') . ': ' . substr((string) $msg, 0, 160));
        }

        // reflect in the app mirror for this borrower
        $map = \DB::table('koha_borrower_map')->where('koha_borrowernumber', $patron['patron_id'])->first();
        if ($map) \Illuminate\Support\Facades\Artisan::call('koha:sync-circulation', ['--user' => $map->user_id]);

        return redirect()->route('librarian.koha.patron', ['q' => $data['cardnumber']])
            ->with('message', get_phrase('Book issued to') . ' ' . ($patron['firstname'] ?? '') . ' ' . ($patron['surname'] ?? ''));
    }

    /* ---------------- librarian: Koha panel (status + sync) ---------------- */
    public function librarianPanel(Koha $koha)
    {
        $ping = $koha->isConfigured() ? $koha->ping() : ['ok' => false];
        return view('librarian.koha.panel', [
            'configured' => $koha->isConfigured(),
            'online'     => (bool) ($ping['ok'] ?? false),
            'settings'   => ['base' => get_settings('koha_base_url'), 'opac' => get_settings('koha_opac_url'),
                             'user' => get_settings('koha_api_user'), 'branch' => get_settings('koha_library_branch')],
            'stats'      => [
                'mapped'       => (int) \DB::table('koha_borrower_map')->count(),
                'books_pushed' => (int) \DB::table('books')->whereNotNull('koha_biblionumber')->count(),
                'koha_issues'  => (int) \DB::table('book_issues')->where('source', 'koha')->count(),
                'koha_fines'   => (int) \DB::table('koha_fine_sync')->count(),
            ],
        ]);
    }

    public function runSync(Request $request)
    {
        $map = [
            'patrons'     => 'koha:sync-patrons',
            'catalog'     => 'koha:push-catalog',
            'circulation' => 'koha:sync-circulation',
            'fines'       => 'koha:sync-fines',
        ];
        $job = $request->get('job');
        abort_if(!isset($map[$job]), 404);

        // guard runtime — these hit an external server; keep the UI responsive
        @set_time_limit(0);
        Artisan::call($map[$job]);
        $out = trim(Artisan::output());

        return redirect()->back()->with('message', ucfirst($job) . ' sync: ' . (\Illuminate\Support\Str::afterLast($out, "\n") ?: $out ?: 'done'));
    }
}
