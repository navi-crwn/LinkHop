<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Username;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Gives every account a username. With --import, usernames the apps already
 * use are taken over first (JSON: [{"hop_id": "12", "username": "rani",
 * "created_at": "2026-01-02T03:04:05Z"}, …], one file per app, exported by the
 * app). Conflicts go to whoever made theirs first; the others are listed and
 * get a generated name they can change in Settings.
 */
class HopUsernames extends Command
{
    protected $signature = 'hop:usernames {--import=* : JSON file(s) exported by the apps} {--dry-run : Only report}';

    protected $description = 'Assign usernames to accounts, importing the ones the apps already use';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $claims = [];
        foreach ((array) $this->option('import') as $file) {
            $rows = json_decode((string) file_get_contents($file), true);
            if (! is_array($rows)) {
                $this->error("$file: not a JSON array");

                return self::FAILURE;
            }
            foreach ($rows as $r) {
                $name = Username::normalize($r['username'] ?? '');
                $id = (int) ($r['hop_id'] ?? 0);
                if ($id === 0 || $name === '') {
                    continue;
                }
                $at = isset($r['created_at']) ? Carbon::parse($r['created_at']) : now();
                // The earliest claim on a name wins, across apps.
                if (! isset($claims[$name]) || $at->lt($claims[$name]['at'])) {
                    $claims[$name] = ['user_id' => $id, 'at' => $at, 'from' => basename($file)];
                }
            }
        }

        $assigned = 0;
        $conflicts = [];
        foreach ($claims as $name => $claim) {
            $user = User::find($claim['user_id']);
            if (! $user) {
                $conflicts[] = "$name: HOP ID {$claim['user_id']} does not exist ({$claim['from']})";

                continue;
            }
            if ($user->username === $name) {
                continue;
            }
            if ($user->username !== null) {
                $conflicts[] = "$name: {$user->email} already has @{$user->username}, kept";

                continue;
            }
            $why = Username::problem($name, $user);
            if ($why !== null) {
                $conflicts[] = "$name: {$user->email} ({$claim['from']}): $why";

                continue;
            }
            $this->line("@$name -> {$user->email} ({$claim['from']})");
            $dry || $user->forceFill(['username' => $name])->saveQuietly();
            $assigned++;
        }

        $generated = 0;
        User::query()->whereNull('username')->orderBy('id')->each(function (User $u) use (&$generated, $dry) {
            $name = Username::suggest($u->name ?: $u->email, $u);
            $this->line("@$name (generated) -> {$u->email}");
            $dry || $u->forceFill(['username' => $name])->saveQuietly();
            $generated++;
        });

        $this->info("imported: $assigned, generated: $generated, conflicts: ".count($conflicts).($dry ? ' (dry run)' : ''));
        foreach ($conflicts as $c) {
            $this->warn('  '.$c);
        }

        return self::SUCCESS;
    }
}
