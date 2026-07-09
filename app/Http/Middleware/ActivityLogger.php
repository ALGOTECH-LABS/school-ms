<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Illuminate\Support\Str;

class ActivityLogger
{
    /**
     * Log meaningful, state-changing actions by authenticated users.
     * (Logins/logouts are logged explicitly in LoginController.)
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            if (auth()->check()) {
                $route  = $request->route();
                $name   = $route ? $route->getName() : null;
                $path   = $request->path();
                $method = $request->method();
                $isGet  = in_array($method, ['GET', 'HEAD', 'OPTIONS']);

                // never log these (auth flow handled explicitly, or the log viewer itself)
                $skip = ['login', 'logout', 'password.email', 'password.update',
                         'superadmin.activity_log', 'admin.activity_log', 'superadmin.activity_log.settings'];
                $skipPath = in_array($path, ['login', 'logout']);

                if (!in_array($name, $skip) && !$skipPath) {
                    if (!$isGet) {
                        // State-changing action — always logged.
                        $action = $name ? ucwords(str_replace(['.', '_', '-'], ' ', $name))
                                        : $method . ' ' . $path;
                        ActivityLog::record($action, $method . ' /' . $path, $request);
                    } elseif (get_settings('log_page_views') == '1' && !$request->ajax() && $route) {
                        // Page view — only when enabled, only real page loads (not AJAX partials/assets).
                        $action = 'Viewed: ' . ucwords(str_replace(['.', '_', '-'], ' ', $name ?: $path));
                        ActivityLog::record($action, 'GET /' . $path, $request);
                    }
                }
            }
        } catch (\Throwable $e) { /* never break the response */ }

        return $response;
    }
}
