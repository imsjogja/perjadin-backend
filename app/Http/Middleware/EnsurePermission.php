<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * @param  array<int, string>  $permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasAnyPermission($permissions)) {
            return response()->json([
                'message' => 'Anda tidak memiliki hak akses untuk aksi ini.',
            ], 403);
        }

        return $next($request);
    }
}
