<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Middleware;

use Closure;
use EvolutionCMS\eMCP\Services\TokenService;
use EvolutionCMS\eMCP\Support\TransportError;
use Illuminate\Http\Request;

/**
 * Authenticates a request with a personal access token sent as `Authorization: Bearer emcp_...`.
 *
 * On success the request carries a provider-neutral auth context (`emcp.auth.*`) that
 * ImpersonateManagerUser, EnsureMcpScopes and ResolveMcpActor read.
 */
class EnsureApiPat
{
    public function __construct(
        private readonly TokenService $tokens
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $plaintext = $this->bearerToken($request);
        if ($plaintext === null) {
            return TransportError::response($request, 401, 'unauthenticated', 'Unauthenticated');
        }

        $token = $this->tokens->resolve($plaintext);
        if ($token === null) {
            return TransportError::response($request, 401, 'invalid_token', 'Invalid or expired token');
        }

        $request->attributes->set('emcp.auth.mode', 'pat');
        $request->attributes->set('emcp.auth.token_id', (int)$token->getKey());
        $request->attributes->set('emcp.auth.user_id', $token->user_id);
        $request->attributes->set('emcp.auth.scopes', $token->scopeList());

        $this->tokens->touch($token);

        return $next($request);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = trim((string)$request->headers->get('Authorization', ''));

        // mod_php drops non-Basic Authorization headers from $_SERVER; the raw request headers still have it.
        if ($header === '' && function_exists('getallheaders')) {
            foreach ((array)getallheaders() as $name => $value) {
                if (strtolower((string)$name) === 'authorization') {
                    $header = trim((string)$value);
                    break;
                }
            }
        }
        if ($header === '' || !preg_match('~^Bearer\s+(\S+)$~i', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
