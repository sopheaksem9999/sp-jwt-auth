<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Models\JwtRefreshToken;

final class PruneCommand extends Command
{
    protected $signature = 'sp-jwt-auth:prune {--expired-days=30} {--revoked-days=30} {--chunk=1000} {--dry-run}';

    protected $description = 'Delete expired and revoked JWT token records outside the retention window.';

    public function handle(): int
    {
        $expiredCutoff = now()->subDays((int) $this->option('expired-days'));
        $revokedCutoff = now()->subDays((int) $this->option('revoked-days'));
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        // Refresh tokens first: access tokens cascade-delete their refresh tokens,
        // so a live refresh token must never lose its access-token parent.
        $queries = [
            [JwtRefreshToken::class, $this->stale(JwtRefreshToken::query(), $expiredCutoff, $revokedCutoff)],
            [JwtAccessToken::class, $this->stale(JwtAccessToken::query(), $expiredCutoff, $revokedCutoff)
                ->whereNotExists(static fn ($query) => $query->selectRaw('1')
                    ->from('sp_jwt_refresh_tokens')
                    ->whereColumn('sp_jwt_refresh_tokens.access_token_id', 'sp_jwt_access_tokens.id')
                    ->whereNull('sp_jwt_refresh_tokens.revoked_at')
                    ->where('sp_jwt_refresh_tokens.expires_at', '>', now()))],
        ];
        $count = 0;

        foreach ($queries as [$model, $query]) {
            if ($dryRun) {
                $count += (clone $query)->count();

                continue;
            }

            /** @var class-string<Model> $model */
            do {
                $ids = (clone $query)->limit($chunk)->pluck('id');
                $count += $ids->isEmpty() ? 0 : $model::query()->whereKey($ids->all())->delete();
            } while ($ids->count() === $chunk);
        }

        $this->info(($dryRun ? 'Would prune ' : 'Pruned ') . $count . ' token records.');

        return self::SUCCESS;
    }

    private function stale(Builder $query, mixed $expiredCutoff, mixed $revokedCutoff): Builder
    {
        return $query->where(static fn (Builder $q) => $q
            ->where('expires_at', '<', $expiredCutoff)
            ->orWhere(static fn (Builder $r) => $r->whereNotNull('revoked_at')->where('revoked_at', '<', $revokedCutoff)));
    }
}
