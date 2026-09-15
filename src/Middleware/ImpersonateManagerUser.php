<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Middleware;

use Closure;
use EvolutionCMS\eMCP\Services\ManagerIdentity;
use EvolutionCMS\eMCP\Support\TransportError;
use EvolutionCMS\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Runs the rest of the pipeline as the authenticated API user.
 *
 * Reads the user id left by EnsureApiPat (`emcp.auth.user_id`) or by sApi's JWT middleware
 * (`sapi.jwt.user_id`), so both API auth modes end up with the same manager context and the
 * same permission checks as a browser session.
 */
class ImpersonateManagerUser
{
    public function __construct(
        private readonly ManagerIdentity $identity
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $userId = $this->resolveUserId($request);
        if ($userId === null) {
            return TransportError::response($request, 401, 'unauthenticated', 'Unauthenticated');
        }

        /** @var User|null $user */
        $user = User::query()->with('attributes')->find($userId);
        if ($user === null) {
            return TransportError::response($request, 401, 'invalid_token', 'Token owner no longer exists');
        }

        $reason = $this->identity->refusalReason($user);
        if ($reason !== null) {
            return TransportError::response($request, 403, $reason, 'User may not act through the API');
        }

        $request->attributes->set('emcp.auth.user_id', $userId);

        $response = $this->identity->runAs($user, static fn() => $next($request));

        // A streamed body runs after the middleware stack has unwound, so it needs the identity again.
        if ($response instanceof StreamedResponse) {
            $callback = $response->getCallback();
            if ($callback !== null) {
                $response->setCallback(fn() => $this->identity->runAs($user, $callback));
            }
        }

        return $response;
    }

    private function resolveUserId(Request $request): ?int
    {
        foreach (['emcp.auth.user_id', 'sapi.jwt.user_id'] as $attribute) {
            $value = $request->attributes->get($attribute);
            if (is_numeric($value) && (int)$value > 0) {
                return (int)$value;
            }
        }

        return null;
    }
}
