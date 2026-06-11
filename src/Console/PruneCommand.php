<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Models\JwtRefreshToken;

final class PruneCommand extends Command
{
    protected $signature = 'sp-jwt-auth:prune {--expired-days=30} {--revoked-days=30} {--dry-run}';

    protected $description = 'Delete expired and revoked JWT token records outside the retention window.';

    public function handle(): int
    {
        $expiredCutoff = now()->subDays((int) $this->option('expired-days'));
        $revokedCutoff = now()->subDays((int) $this->option('revoked-days'));
        $queries = [
            JwtAccessToken::query()->where('expires_at', '<', $expiredCutoff),
            JwtAccessToken::query()->whereNotNull('revoked_at')->where('revoked_at', '<', $revokedCutoff),
            JwtRefreshToken::query()->where('expires_at', '<', $expiredCutoff),
            JwtRefreshToken::query()->whereNotNull('revoked_at')->where('revoked_at', '<', $revokedCutoff),
        ];
        $count = 0;

        foreach ($queries as $query) {
            $count += (clone $query)->count();

            if (! $this->option('dry-run')) {
                $query->delete();
            }
        }

        $this->info(($this->option('dry-run') ? 'Would prune ' : 'Pruned ') . $count . ' token records.');

        return self::SUCCESS;
    }
}
