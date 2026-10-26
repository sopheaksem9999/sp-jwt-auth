<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Sopheak\JwtAuth\Models\Device;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
use Sopheak\JwtAuth\Support\DeviceConfig;

final class PruneDevicesCommand extends Command
{
    protected $signature = 'sp-jwt-auth:prune-devices {--inactive-days=} {--chunk=1000} {--dry-run}';

    protected $description = 'Delete devices (and their own tokens) that are inactive or were revoked outside the retention window.';

    public function handle(): int
    {
        $days = $this->option('inactive-days') !== null ? max(1, (int) $this->option('inactive-days')) : DeviceConfig::pruneInactiveDays();
        $cutoff = now()->subDays($days);
        $chunk = max(1, (int) $this->option('chunk'));
        $query = Device::query()->where(static fn (Builder $q) => $q
            ->where('last_seen_at', '<', $cutoff)
            ->orWhere(static fn (Builder $n) => $n->whereNull('last_seen_at')->where('created_at', '<', $cutoff))
            ->orWhere(static fn (Builder $r) => $r->whereNotNull('revoked_at')->where('revoked_at', '<', $cutoff)));
        $count = 0;

        if ($this->option('dry-run')) {
            $count = (clone $query)->count();
        } else {
            do {
                $ids = (clone $query)->limit($chunk)->pluck('id');

                if ($ids->isNotEmpty()) {
                    $keys = $ids->map(static fn (mixed $id): string => (string) $id)->all();
                    // No foreign key from the token tables to devices, so remove them here (refresh first).
                    JwtRefreshToken::query()->where('user_type', Device::class)->whereIn('user_id', $keys)->delete();
                    JwtAccessToken::query()->where('user_type', Device::class)->whereIn('user_id', $keys)->delete();
                    $count += Device::query()->whereKey($ids->all())->delete(); // links cascade
                }
            } while ($ids->count() === $chunk);
        }

        $this->info(($this->option('dry-run') ? 'Would prune ' : 'Pruned ') . $count . ' devices.');

        return self::SUCCESS;
    }
}
