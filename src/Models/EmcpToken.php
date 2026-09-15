<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Models;

use EvolutionCMS\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personal access token bound to one manager user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $token_prefix
 * @property string $token_hash
 * @property array<int, string> $scopes
 * @property \Carbon\Carbon|null $last_used_at
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $revoked_at
 */
class EmcpToken extends Model
{
    protected $table = 'emcp_tokens';

    protected $fillable = [
        'user_id',
        'name',
        'token_prefix',
        'token_hash',
        'scopes',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'user_id' => 'int',
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = ['token_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return !$this->isRevoked() && !$this->isExpired();
    }

    /**
     * @return array<int, string>
     */
    public function scopeList(): array
    {
        $scopes = $this->scopes;

        return is_array($scopes) ? array_values(array_filter(array_map('strval', $scopes))) : [];
    }
}
