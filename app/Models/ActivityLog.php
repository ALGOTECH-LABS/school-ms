<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    protected $guarded = [];

    public static array $roles = [
        1 => 'Super Admin', 2 => 'Admin', 3 => 'Teacher', 4 => 'Accountant',
        5 => 'Librarian', 6 => 'Parent', 7 => 'Student', 8 => 'Driver', 9 => 'Alumni',
    ];

    /**
     * Build the filtered log + stats for the viewer. $schoolId scopes to a school (admin); null = all (superadmin).
     * Returns [$logs (paginator), $stats (array)].
     */
    public static function report($request, $schoolId = null): array
    {
        $scoped = fn() => static::query()->when($schoolId, fn($qq) => $qq->where('school_id', $schoolId));

        $q = $scoped()->orderByDesc('id');
        if (($s = trim((string) $request->get('search', ''))) !== '') {
            $q->where(function ($w) use ($s) {
                $w->where('user_name', 'like', "%$s%")->orWhere('action', 'like', "%$s%")->orWhere('ip_address', 'like', "%$s%");
            });
        }
        if (($role = $request->get('role', '')) !== '') $q->where('role', $role);
        if ($request->get('type') === 'logins')  $q->whereIn('action', ['Logged in', 'Logged out']);
        if ($request->get('type') === 'actions') $q->whereNotIn('action', ['Logged in', 'Logged out']);
        if (($from = $request->get('from', '')) !== '') $q->where('created_at', '>=', $from . ' 00:00:00');
        if (($to = $request->get('to', '')) !== '')     $q->where('created_at', '<=', $to . ' 23:59:59');

        $logs = $q->paginate(25)->withQueryString();

        $today = date('Y-m-d 00:00:00');
        $stats = [
            'total'         => $scoped()->count(),
            'logins_today'  => $scoped()->where('action', 'Logged in')->where('created_at', '>=', $today)->count(),
            'actions_today' => $scoped()->whereNotIn('action', ['Logged in', 'Logged out'])->where('created_at', '>=', $today)->count(),
            'active_users'  => $scoped()->where('created_at', '>=', $today)->distinct('user_id')->count('user_id'),
        ];

        return [$logs, $stats];
    }

    /** Record an activity entry (safe: never throws). */
    public static function record(string $action, ?string $description = null, $request = null): void
    {
        try {
            $u = auth()->user();
            $request = $request ?: request();
            static::create([
                'school_id'  => $u->school_id ?? null,
                'user_id'    => $u->id ?? null,
                'user_name'  => $u->name ?? 'Guest',
                'role'       => $u ? (static::$roles[$u->role_id] ?? 'User') : 'Guest',
                'action'     => \Illuminate\Support\Str::limit($action, 190, ''),
                'description'=> $description ? \Illuminate\Support\Str::limit($description, 250, '') : null,
                'method'     => $request->method(),
                'url'        => \Illuminate\Support\Str::limit($request->path(), 250, ''),
                'ip_address' => $request->ip(),
                'user_agent' => \Illuminate\Support\Str::limit((string) $request->userAgent(), 250, ''),
            ]);

            // Occasionally prune old entries so the table stays bounded (retention in days).
            if (rand(1, 100) === 1) {
                $days = (int) (get_settings('log_retention_days') ?: 30);
                if ($days > 0) {
                    static::where('created_at', '<', date('Y-m-d H:i:s', strtotime("-$days days")))->limit(5000)->delete();
                }
            }
        } catch (\Throwable $e) { /* logging must never break the app */ }
    }
}
