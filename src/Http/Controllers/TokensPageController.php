<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Http\Controllers;

use Carbon\Carbon;
use EvolutionCMS\eMCP\Models\EmcpToken;
use EvolutionCMS\eMCP\Services\TokenService;
use EvolutionCMS\eMCP\Support\ManagerUrl;
use EvolutionCMS\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * "My MCP tokens" page: a manager user creates and revokes their own personal access tokens.
 *
 * Only the caller's own tokens are ever listed or touched; admins issue tokens for other
 * users from the CLI (emcp:token:create).
 */
class TokensPageController
{
    /** @var array<int, string> */
    private const EXPIRY_CHOICES = ['30', '90', '180', '365', 'never'];

    public function __construct(
        private readonly TokenService $tokens
    ) {
    }

    public function index(Request $request)
    {
        $userId = $this->currentUserId();

        $tokens = EmcpToken::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();

        return view('eMCP::manager.tokens', [
            'tokens' => $tokens,
            'scopes' => TokenService::KNOWN_SCOPES,
            'expiryChoices' => self::EXPIRY_CHOICES,
            'createUrl' => ManagerUrl::to('emcp/tokens'),
            'revokeBaseUrl' => ManagerUrl::to('emcp/tokens'),
            'endpointUrl' => ManagerUrl::siteUrl(trim((string)config('cms.settings.eMCP.route.api_prefix', 'mcp'), '/') . '/content'),
            'authMode' => strtolower(trim((string)config('cms.settings.eMCP.auth.mode', 'pat'))),
            'plaintext' => $this->pullFlash('plaintext'),
            'message' => $this->pullFlash('message'),
            'error' => $this->pullFlash('error'),
            'darkTheme' => $this->isDarkTheme(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $this->currentUserId();

        /** @var User|null $user */
        $user = User::query()->with('attributes')->find($userId);
        if ($user === null) {
            abort(403);
        }

        $name = trim((string)$request->input('name', ''));
        $scopes = (array)$request->input('scopes', []);
        $expires = trim((string)$request->input('expires', '90'));

        try {
            $expiresAt = $this->resolveExpiry($expires);
            $issued = $this->tokens->issue($user, $name, $scopes, $expiresAt);
        } catch (InvalidArgumentException $e) {
            return $this->back('error', $e->getMessage());
        }

        $this->logManagerAction('Created MCP token "' . $issued['token']->name . '"');
        $this->flash('plaintext', $issued['plaintext']);

        return $this->back('message', 'Token created. Copy it now: it will not be shown again.');
    }

    public function revoke(Request $request, int $id): RedirectResponse
    {
        $userId = $this->currentUserId();

        /** @var EmcpToken|null $token */
        $token = EmcpToken::query()->where('user_id', $userId)->find($id);
        if ($token === null) {
            return $this->back('error', 'Token not found.');
        }

        $this->tokens->revoke($token);
        $this->logManagerAction('Revoked MCP token "' . $token->name . '"');

        return $this->back('message', 'Token "' . $token->name . '" revoked.');
    }

    private function currentUserId(): int
    {
        if (!function_exists('evo') || !evo()->isLoggedIn('mgr')) {
            abort(403);
        }

        if (!evo()->hasPermission((string)config('cms.settings.eMCP.acl.permission', 'emcp'), 'mgr')) {
            abort(403);
        }

        return (int)evo()->getLoginUserID('mgr');
    }

    private function resolveExpiry(string $choice): ?Carbon
    {
        if (!in_array($choice, self::EXPIRY_CHOICES, true)) {
            throw new InvalidArgumentException('Invalid expiry choice.');
        }

        return $choice === 'never' ? null : Carbon::now()->addDays((int)$choice);
    }

    private function back(?string $flashKey = null, string $flashValue = ''): RedirectResponse
    {
        if ($flashKey !== null) {
            $this->flash($flashKey, $flashValue);
        }

        return redirect()->to(ManagerUrl::to('emcp/tokens'));
    }

    /**
     * One-shot messages ride on Evo's native session: the manager's Laravel session store is not
     * reliably shared across a redirect, the PHP session always is.
     */
    private function flash(string $key, string $value): void
    {
        $_SESSION['emcp_tokens_flash'][$key] = $value;
    }

    private function pullFlash(string $key): string
    {
        $value = (string)($_SESSION['emcp_tokens_flash'][$key] ?? '');
        unset($_SESSION['emcp_tokens_flash'][$key]);

        return $value;
    }

    private function isDarkTheme(): bool
    {
        $modes = ['', 'lightness', 'light', 'dark', 'darkness'];
        $index = isset($_COOKIE['MODX_themeMode']) ? (int)$_COOKIE['MODX_themeMode'] : (int)evo()->getConfig('manager_theme_mode');

        return in_array($modes[$index] ?? '', ['dark', 'darkness'], true);
    }

    private function logManagerAction(string $message): void
    {
        try {
            evo()->logEvent(0, 1, $message, 'eMCP');
        } catch (\Throwable) {
            // The event log must never break the page.
        }
    }
}
