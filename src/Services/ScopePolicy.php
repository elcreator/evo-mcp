<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScopePolicy
{
    /**
     * @var array<string, array<int, string>>
     */
    private const BUILTIN_SCOPE_MAP = [
        'mcp:read' => [
            'initialize',
            'ping',
            'tools/list',
            'resources/list',
            'resources/read',
            'prompts/list',
            'prompts/get',
            'completion/complete',
            'resources/templates/list',
            'notifications/*',
        ],
        'mcp:call' => [
            'tools/call',
        ],
        'mcp:admin' => [
            'admin/*',
        ],
    ];

    public function resolveRequestedMethod(Request $request): ?string
    {
        $cached = trim((string)$request->attributes->get('emcp.jsonrpc.method', ''));
        if ($cached !== '') {
            return $cached;
        }

        $rawBody = $request->getContent();
        if (!is_string($rawBody) || trim($rawBody) === '') {
            return null;
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return null;
        }

        $method = trim((string)($payload['method'] ?? ''));
        if ($method === '') {
            return null;
        }

        $request->attributes->set('emcp.jsonrpc.method', $method);

        return $method;
    }

    public function resolveRequiredScope(?string $serverHandle, string $method): ?string
    {
        $scopeMap = $this->resolveScopeMap($serverHandle);

        foreach ($scopeMap as $scope => $methods) {
            if ($this->matchesMethod($method, $methods)) {
                return $scope;
            }
        }

        if (str_starts_with($method, 'admin/')) {
            return 'mcp:admin';
        }

        return null;
    }

    public function requestHasScope(Request $request, string $requiredScope): bool
    {
        $scopes = $this->requestScopes($request);
        if (in_array('*', $scopes, true)) {
            return true;
        }

        return in_array($requiredScope, $scopes, true);
    }

    /**
     * Scopes granted to the request by whichever auth middleware ran (PAT first, then sApi JWT).
     *
     * @return array<int, string>
     */
    public function requestScopes(Request $request): array
    {
        $scopes = $request->attributes->get('emcp.auth.scopes');
        if ($scopes === null) {
            $scopes = $request->attributes->get('sapi.jwt.scopes', []);
        }

        if (is_string($scopes)) {
            $scopes = array_values(array_filter(array_map('trim', explode(',', $scopes))));
        }

        if (!is_array($scopes)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $value): string => trim((string)$value),
            $scopes
        )));
    }

    /**
     * Scope a `tools/call` needs on top of `mcp:call`, or null when the tool is not a write tool.
     */
    public function resolveToolScope(string $toolName): ?string
    {
        $isWrite = str_starts_with(trim($toolName), 'evo.write.')
            || (function_exists('app') && app()->bound(ToolRegistry::class) && app(ToolRegistry::class)->isWriteTool($toolName));

        if (!$isWrite) {
            return null;
        }

        $scope = trim((string)config('cms.settings.eMCP.auth.write_scope', 'mcp:write'));

        return $scope !== '' ? $scope : 'mcp:write';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function resolveScopeMap(?string $serverHandle): array
    {
        $serverMap = $this->serverScopeMap($serverHandle);
        if ($serverMap !== []) {
            return $serverMap;
        }

        $globalMap = $this->normalizeScopeMap(config('cms.settings.eMCP.auth.scope_map', []));
        if ($globalMap !== []) {
            return $globalMap;
        }

        return self::BUILTIN_SCOPE_MAP;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function serverScopeMap(?string $serverHandle): array
    {
        $serverHandle = trim((string)$serverHandle);
        if ($serverHandle === '') {
            return [];
        }

        $servers = config('mcp.servers', []);
        if (!is_array($servers)) {
            return [];
        }

        foreach ($servers as $server) {
            if (!is_array($server)) {
                continue;
            }

            if (trim((string)($server['handle'] ?? '')) !== $serverHandle) {
                continue;
            }

            return $this->normalizeScopeMap($server['scope_map'] ?? []);
        }

        return [];
    }

    /**
     * @param  mixed  $map
     * @return array<string, array<int, string>>
     */
    private function normalizeScopeMap(mixed $map): array
    {
        if (!is_array($map)) {
            return [];
        }

        $normalized = [];

        foreach ($map as $scope => $methods) {
            $scope = trim((string)$scope);
            if ($scope === '') {
                continue;
            }

            if (is_string($methods)) {
                $methods = [$methods];
            }

            if (!is_array($methods)) {
                continue;
            }

            $prepared = [];
            foreach ($methods as $method) {
                $method = trim((string)$method);
                if ($method !== '') {
                    $prepared[] = $method;
                }
            }

            if ($prepared !== []) {
                $normalized[$scope] = array_values(array_unique($prepared));
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function matchesMethod(string $method, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return true;
            }

            if (Str::contains($pattern, '*')) {
                if (Str::is($pattern, $method)) {
                    return true;
                }

                continue;
            }

            if ($pattern === $method) {
                return true;
            }
        }

        return false;
    }
}
