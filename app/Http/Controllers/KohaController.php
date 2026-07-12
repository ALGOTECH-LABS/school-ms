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

        return redirect()->route('admin.koha')->with('message', ucfirst($job) . ' sync: ' . (\Illuminate\Support\Str::afterLast($out, "\n") ?: $out ?: 'done'));
    }
}
