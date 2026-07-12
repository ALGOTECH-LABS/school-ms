<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * Thin client for the Koha ILS REST API (/api/v1).
 * Config comes from global_settings (get_settings): koha_base_url, koha_opac_url,
 * koha_auth_mode (basic|oauth), koha_api_user/koha_api_pass (basic) or
 * koha_api_client_id/koha_api_client_secret (oauth).
 *
 * All methods are guarded by isConfigured(); network calls return arrays with
 * ok/status so callers (sync commands) can log failures instead of throwing.
 */
class Koha
{
    protected string $base;
    protected string $opac;
    protected string $mode;
    protected string $user;
    protected string $pass;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->base         = rtrim((string) get_settings('koha_base_url'), '/');
        $this->opac         = rtrim((string) get_settings('koha_opac_url'), '/');
        $this->mode         = get_settings('koha_auth_mode') ?: 'basic';
        $this->user         = (string) get_settings('koha_api_user');
        $this->pass         = (string) get_settings('koha_api_pass');
        $this->clientId     = (string) get_settings('koha_api_client_id');
        $this->clientSecret = (string) get_settings('koha_api_client_secret');
    }

    public function isConfigured(): bool
    {
        if ($this->base === '') return false;
        return $this->mode === 'oauth'
            ? ($this->clientId !== '' && $this->clientSecret !== '')
            : ($this->user !== '' && $this->pass !== '');
    }

    public function opacUrl(): string { return $this->opac; }

    /** Base HTTP request against <base>/api/v1 with auth + optional embed. */
    protected function http(array $embed = []): PendingRequest
    {
        $req = Http::baseUrl($this->base . '/api/v1')
            ->timeout(25)
            ->acceptJson();

        if ($this->mode === 'oauth') {
            $token = $this->oauthToken();
            if ($token) $req = $req->withToken($token);
        } else {
            $req = $req->withBasicAuth($this->user, $this->pass);
        }

        if ($embed) $req = $req->withHeaders(['x-koha-embed' => implode(',', $embed)]);
        return $req;
    }

    protected function oauthToken(): ?string
    {
        $r = Http::asForm()->timeout(20)->post($this->base . '/api/v1/oauth/token', [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);
        return $r->successful() ? ($r->json('access_token') ?? null) : null;
    }

    /* ---------------- health / reference data ---------------- */

    /** Cheap authenticated call to verify credentials + connectivity. */
    public function ping(): array
    {
        $r = $this->http()->get('/libraries', ['_per_page' => 1]);
        return ['ok' => $r->successful(), 'status' => $r->status(), 'body' => $r->json() ?? $r->body()];
    }

    public function libraries(): array
    {
        return $this->http()->get('/libraries', ['_per_page' => 500])->json() ?? [];
    }

    public function patronCategories(): array
    {
        return $this->http()->get('/patron_categories', ['_per_page' => 500])->json() ?? [];
    }

    /* ---------------- patrons ---------------- */

    public function findPatronByCardnumber(string $card): ?array
    {
        $r = $this->http()->get('/patrons', [
            'q' => json_encode(['cardnumber' => $card]),
            '_per_page' => 1,
        ]);
        $arr = $r->json();
        return (is_array($arr) && count($arr)) ? $arr[0] : null;
    }

    public function createPatron(array $body): array
    {
        $r = $this->http()->post('/patrons', $body);
        return ['ok' => $r->successful(), 'status' => $r->status(), 'body' => $r->json() ?? $r->body()];
    }

    public function updatePatron($patronId, array $body): array
    {
        $r = $this->http()->put("/patrons/{$patronId}", $body);
        return ['ok' => $r->successful(), 'status' => $r->status(), 'body' => $r->json() ?? $r->body()];
    }

    /* ---------------- circulation ---------------- */

    /** Current checkouts (on loan) for a patron. */
    public function checkouts($patronId): array
    {
        return $this->http()->get('/checkouts', [
            'patron_id' => $patronId,
            '_per_page' => 500,
        ])->json() ?? [];
    }

    /** A single item (to resolve item_id -> biblio_id). */
    public function getItem($itemId): ?array
    {
        $r = $this->http()->get("/items/{$itemId}");
        return $r->successful() ? $r->json() : null;
    }

    /** Patron account summary (balance + outstanding debits/fines). */
    public function account($patronId): ?array
    {
        $r = $this->http()->get("/patrons/{$patronId}/account");
        return $r->successful() ? $r->json() : null;
    }

    /** Individual debit lines (charges/fines) on a patron's account. */
    public function accountDebits($patronId): array
    {
        $r = $this->http()->get("/patrons/{$patronId}/account/debits", ['_per_page' => 200]);
        return $r->successful() ? ($r->json() ?? []) : [];
    }

    /* ---------------- catalog ---------------- */

    public function getBiblio($biblioId): ?array
    {
        $r = $this->http()->get("/biblios/{$biblioId}");
        return $r->successful() ? $r->json() : null;
    }

    /** Free-text catalog search (title/author). Query DSL may need tuning per Koha version. */
    public function searchBiblios(string $q, int $page = 1, int $perPage = 20): array
    {
        $r = $this->http()->get('/biblios', [
            'q' => json_encode(['-or' => [
                ['title'  => ['-like' => "%{$q}%"]],
                ['author' => ['-like' => "%{$q}%"]],
            ]]),
            '_page'     => $page,
            '_per_page' => $perPage,
        ]);
        return [
            'ok'    => $r->successful(),
            'status'=> $r->status(),
            'items' => $r->json() ?? [],
            'total' => (int) $r->header('X-Total-Count'),
        ];
    }

    public function opacBiblioUrl($biblioId): string
    {
        return $this->opac . '/cgi-bin/koha/opac-detail.pl?biblionumber=' . $biblioId;
    }

    /* ---------------- catalog write (bootstrap load) ---------------- */

    /** Create a bib record from a MARC-in-JSON array. Returns the biblionumber. */
    public function createBiblio(array $marc): ?int
    {
        $r = $this->http()->withOptions(['allow_redirects' => false])
            ->withBody(json_encode($marc), 'application/marc-in-json')
            ->withHeaders(['x-confirm-not-duplicate' => '1'])
            ->post('/biblios');

        $loc = $r->header('Location');
        if ($loc && preg_match('~biblios/(\d+)~', $loc, $m)) return (int) $m[1];
        $b = $r->json();
        return $b['biblio_id'] ?? $b['id'] ?? null;
    }

    /** Add an item (copy) to a bib. $item: home_library_id, holding_library_id, item_type_id, external_id(barcode). */
    public function addItem($biblioId, array $item): array
    {
        $r = $this->http()->post("/biblios/{$biblioId}/items", $item);
        return ['ok' => $r->successful(), 'status' => $r->status(), 'body' => $r->json() ?? $r->body()];
    }

    public function biblioItems($biblioId): array
    {
        return $this->http()->get("/biblios/{$biblioId}/items", ['_per_page' => 500])->json() ?? [];
    }

    public function biblios(int $page = 1, int $perPage = 100): array
    {
        return $this->http()->get('/biblios', ['_page' => $page, '_per_page' => $perPage])->json() ?? [];
    }

    /* ---------------- circulation write (demo helpers) ---------------- */

    /** Check an item out to a patron. */
    public function checkout($itemId, $patronId): array
    {
        $r = $this->http()->post('/checkouts', [
            'item_id'   => (int) $itemId,
            'patron_id' => (int) $patronId,
        ]);
        return ['ok' => $r->successful(), 'status' => $r->status(), 'body' => $r->json() ?? $r->body()];
    }

    /** Add a manual charge (fine) to a patron's account. */
    public function addDebit($patronId, float $amount, string $description = 'Library fine', string $type = 'OVERDUE'): array
    {
        $r = $this->http()->post("/patrons/{$patronId}/account/debits", [
            'amount'      => $amount,
            'description' => $description,
            'type'        => $type,
        ]);
        return ['ok' => $r->successful(), 'status' => $r->status(), 'body' => $r->json() ?? $r->body()];
    }
}
