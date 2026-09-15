<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Services;

use EvolutionCMS\Models\MemberGroup;
use EvolutionCMS\Models\RolePermissions;
use EvolutionCMS\Models\User;
use EvolutionCMS\Models\UserRole;

/**
 * Makes the current request run as a manager user without a browser session.
 *
 * Evo resolves the acting user, role, permissions and document groups from `$_SESSION['mgr*']`
 * (see Core::isLoggedIn, hasPermission, getUserDocGroups). Filling the same keys that
 * UserLogin::writeSession() fills means every permission check in core, services and plugins
 * sees the token owner exactly as if they had logged in — while nothing is persisted: the
 * keys are restored when the request finishes and no session cookie is ever issued.
 */
final class ManagerIdentity
{
    public const CONTEXT = 'mgr';

    /** @var array<int, string> */
    private const SESSION_KEYS = [
        'usertype',
        'mgrShortname',
        'mgrFullname',
        'mgrEmail',
        'mgrValidated',
        'mgrInternalKey',
        'mgrFailedlogins',
        'mgrLastlogin',
        'mgrLogincount',
        'mgrRole',
        'mgrPermissions',
        'mgrDocgroups',
        'mgrToken',
    ];

    /**
     * Returns null when the user may act, otherwise a short machine-readable reason.
     */
    public function refusalReason(User $user): ?string
    {
        $attributes = $user->attributes;
        if ($attributes === null) {
            return 'user_incomplete';
        }

        if ((int)$attributes->blocked === 1) {
            return 'user_blocked';
        }

        if ((int)$attributes->blockeduntil > time()) {
            return 'user_blocked_temporarily';
        }

        if ((int)$attributes->role < 1) {
            return 'user_has_no_role';
        }

        return null;
    }

    /**
     * Runs $callback with `$_SESSION` describing $user, then restores whatever was there before.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(User $user, callable $callback): mixed
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        $snapshot = [];
        foreach (self::SESSION_KEYS as $key) {
            if (array_key_exists($key, $_SESSION)) {
                $snapshot[$key] = $_SESSION[$key];
            }
        }

        $previousContext = function_exists('evo') ? evo()->getContext() : null;

        try {
            $this->assume($user);

            return $callback();
        } finally {
            foreach (self::SESSION_KEYS as $key) {
                unset($_SESSION[$key]);
            }
            foreach ($snapshot as $key => $value) {
                $_SESSION[$key] = $value;
            }

            if ($previousContext !== null) {
                evo()->setContext($previousContext);
            }
        }
    }

    private function assume(User $user): void
    {
        $attributes = $user->attributes;
        $roleId = (int)$attributes->role;

        $_SESSION['usertype'] = 'manager';
        $_SESSION['mgrShortname'] = $user->username;
        $_SESSION['mgrFullname'] = (string)$attributes->fullname;
        $_SESSION['mgrEmail'] = (string)$attributes->email;
        $_SESSION['mgrValidated'] = 1;
        $_SESSION['mgrInternalKey'] = (int)$user->getKey();
        $_SESSION['mgrFailedlogins'] = (int)$attributes->failedlogincount;
        $_SESSION['mgrLastlogin'] = (int)$attributes->lastlogin;
        $_SESSION['mgrLogincount'] = (int)$attributes->logincount;
        $_SESSION['mgrRole'] = $roleId;
        $_SESSION['mgrPermissions'] = $this->rolePermissions($roleId);
        $_SESSION['mgrDocgroups'] = $this->documentGroups((int)$user->getKey());
        // Never a real CSRF token: the API path has no forms, and it must not be reusable in a browser.
        $_SESSION['mgrToken'] = '';

        if (function_exists('evo')) {
            evo()->setContext(self::CONTEXT);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rolePermissions(int $roleId): array
    {
        $role = UserRole::query()->find($roleId);
        if ($role === null) {
            return [];
        }

        $permissions = $role->toArray();
        $keys = RolePermissions::query()->where('role_id', $roleId)->pluck('permission')->all();
        foreach ($keys as $key) {
            $permissions[(string)$key] = 1;
        }

        return $permissions;
    }

    /**
     * @return array<int, int>
     */
    private function documentGroups(int $userId): array
    {
        return MemberGroup::query()
            ->join('membergroup_access', 'membergroup_access.membergroup', '=', 'member_groups.user_group')
            ->where('member_groups.member', $userId)
            ->pluck('documentgroup')
            ->map(static fn(mixed $value): int => (int)$value)
            ->all();
    }
}
