<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Console\Commands;

use EvolutionCMS\eMCP\Models\EmcpToken;
use EvolutionCMS\Models\User;
use Illuminate\Console\Command;

class eMcpTokenListCommand extends Command
{
    protected $signature = 'emcp:token:list
        {username? : Only tokens of this manager user}
        {--all : Include revoked and expired tokens}';

    protected $description = 'List MCP personal access tokens';

    public function handle(): int
    {
        $query = EmcpToken::query()->with('user')->orderBy('user_id')->orderByDesc('id');

        $username = trim((string)$this->argument('username'));
        if ($username !== '') {
            $userId = User::query()->where('username', $username)->value('id');
            if ($userId === null) {
                $this->error("User [{$username}] not found.");

                return self::FAILURE;
            }
            $query->where('user_id', (int)$userId);
        }

        $rows = [];
        foreach ($query->get() as $token) {
            /** @var EmcpToken $token */
            if (!(bool)$this->option('all') && !$token->isUsable()) {
                continue;
            }

            $rows[] = [
                $token->getKey(),
                $token->user?->username ?? ('#' . $token->user_id),
                $token->name,
                $token->token_prefix . '…',
                implode(',', $token->scopeList()),
                $token->last_used_at?->toDateTimeString() ?? 'never',
                $token->isRevoked() ? 'revoked' : ($token->expires_at?->toDateString() ?? 'never'),
            ];
        }

        if ($rows === []) {
            $this->line('No tokens.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'User', 'Name', 'Prefix', 'Scopes', 'Last used', 'Expires'], $rows);

        return self::SUCCESS;
    }
}
