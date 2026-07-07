<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baca config/portal-pages.php buat cek apakah user boleh masuk ke route ini.
 * Route yang tidak terdaftar di config otomatis lolos — lihat catatan di config/portal-pages.php.
 */
class EnsurePortalPageAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();
        $config = $routeName ? config("portal-pages.{$routeName}") : null;

        if ($config) {
            $user = $request->user();

            abort_if(! $user, 403);

            if (! empty($config['permission']) && ! $user->can($config['permission'])) {
                abort(403);
            }

            if (! empty($config['guard']) && ! $user->{$config['guard']}()) {
                abort(403);
            }
        }

        return $next($request);
    }
}
