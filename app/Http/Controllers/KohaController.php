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
        $browse = ($q === '');

        if ($configured) {
            // no query → browse the whole catalog; otherwise search
            $items = $q !== '' ? $koha->searchBiblios($q, 1, 50)['items'] : $koha->biblios(1, 60);
            foreach ($items as $b) {
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
            'browse'     => $browse,
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

    /* ---------------- shared: a user's Koha loans + fines ---------------- */
    private function patronLoansAndFines($userId, Koha $koha): array
    {
        $map = \DB::table('koha_borrower_map')->where('user_id', $userId)->first();
        $loans = $fines = [];
        if ($map && $map->koha_borrowernumber && $koha->isConfigured()) {
            $pid = $map->koha_borrowernumber;
            foreach ($koha->checkouts($pid) as $co) {
                $item = $koha->getItem($co['item_id'] ?? 0);
                $co['barcode'] = $item['external_id'] ?? null;
                $co['title']   = optional(\DB::table('books')->where('koha_biblionumber', $item['biblio_id'] ?? 0)->first())->name;
                $loans[] = $co;
            }
            $fines = ($koha->account($pid)['outstanding_debits']['lines'] ?? []);
        }
        return [$loans, $fines];
    }

    /** Student / teacher self-service: my loans + fines. */
    public function myLibrary(Koha $koha)
    {
        [$loans, $fines] = $this->patronLoansAndFines(auth()->id(), $koha);
        return view('koha.my_library', [
            'loans' => $loans, 'fines' => $fines,
            'configured' => $koha->isConfigured(), 'opac' => $koha->opacUrl(),
        ]);
    }

    /** Parent: each child's loans + fines. */
    public function parentLibrary(Koha $koha)
    {
        $children = \DB::table('users')->where('parent_id', auth()->id())->where('role_id', 7)->get(['id', 'name', 'code']);
        $kids = [];
        foreach ($children as $c) {
            [$loans, $fines] = $this->patronLoansAndFines($c->id, $koha);
            $kids[] = ['child' => $c, 'loans' => $loans, 'fines' => $fines];
        }
        return view('koha.parent_library', [
            'kids' => $kids, 'configured' => $koha->isConfigured(), 'opac' => $koha->opacUrl(),
        ]);
    }

    /* ---------------- book detail + borrowing history ---------------- */
    public function bookHistory($id, Koha $koha)
    {
        $book = \DB::table('books')->where('id', $id)->first();
        abort_if(!$book, 404);

        $history = \DB::table('book_issues as bi')
            ->leftJoin('users as u', 'u.id', '=', 'bi.student_id')
            ->leftJoin('classes as c', 'c.id', '=', 'bi.class_id')
            ->where('bi.book_id', $id)
            ->orderByDesc('bi.id')
            ->get(['bi.issue_date', 'bi.due_date', 'bi.status', 'bi.source',
                   'u.name as borrower', 'u.code as borrower_code', 'c.name as class_name']);

        // live availability from Koha holdings, if linked
        $total = $available = null;
        if ($book->koha_biblionumber && $koha->isConfigured()) {
            $items = $koha->biblioItems($book->koha_biblionumber);
            $total = count($items);
            $onLoan = collect($items)->filter(fn ($it) => !empty($it['checked_out_date']))->count();
            $available = max(0, $total - $onLoan);
        }

        return view('koha.book_history', [
            'book' => $book, 'history' => $history, 'total' => $total, 'available' => $available,
            'opac' => $book->koha_biblionumber ? $koha->opacBiblioUrl($book->koha_biblionumber) : null,
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
