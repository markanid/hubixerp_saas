<?php

namespace Modules\Settings\app\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Settings\app\Models\PrintAgent;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePrintAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $agent = $token
            ? PrintAgent::query()->where('token_hash', hash('sha256', $token))->where('enabled', true)->first()
            : null;

        if (!$agent) {
            return response()->json(['message' => 'Invalid or revoked print-agent token.'], 401);
        }

        $request->attributes->set('printAgent', $agent);

        return $next($request);
    }
}
