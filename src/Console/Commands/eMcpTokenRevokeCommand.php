<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Console\Commands;

use EvolutionCMS\eMCP\Models\EmcpToken;
use EvolutionCMS\eMCP\Services\TokenService;
use EvolutionCMS\Models\User;
use Illuminate\Console\Command;

class eMcpTokenRevokeCommand extends Command
{
    protected $signature = 'emcp:token:revoke
        {id? : Token id (see emcp:token:list)}
        {--user= : Revoke every active token of this manager user instead}';

    protected $description = 'Revoke MCP personal access tokens';

    public function handle(TokenService $tokens): int
    {
        $username = trim((string)$this->option('user'));
        if ($username !== '') {
            $userId = User::query()->where('username', $username)->value('id');
            if ($userId === null) {
                $this->error("User [{$username}] not found.");

                return self::FAILURE;
            }

            $count = 0;
            foreach (EmcpToken::query()->where('user_id', (int)$userId)->whereNull('revoked_at')->get() as $token) {
                $tokens->revoke($token);
                $count++;
            }

            $this->info("Revoked {$count} token(s) of [{$username}].");

            return self::SUCCESS;
        }

        $id = (int)$this->argument('id');
        if ($id < 1) {
            $this->error('Pass a token id or --user=<username>.');

            return self::FAILURE;
        }

        /** @var EmcpToken|null $token */
        $token = EmcpToken::query()->find($id);
        if ($token === null) {
            $this->error("Token #{$id} not found.");

            return self::FAILURE;
        }

        $tokens->revoke($token);
        $this->info("Token #{$id} (\"{$token->name}\") revoked.");

        return self::SUCCESS;
    }
}
