<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class ListUsers extends Command
{
    protected $signature = 'users:list {--role= : Filter by role}';
    protected $description = 'List all users in the system';

    public function handle()
    {
        $this->info('📋 System Users');
        $this->newLine();

        $query = User::query();

        if ($role = $this->option('role')) {
            $query->where('role', $role);
            $this->line("Filtering by role: {$role}");
            $this->newLine();
        }

        $users = $query->orderBy('role')->orderBy('email')->get();

        if ($users->isEmpty()) {
            $this->warn('No users found');
            return 0;
        }

        $headers = ['Email', 'Name', 'Role', 'Agency', 'Active', 'Verified'];
        $rows = [];

        foreach ($users as $user) {
            $rows[] = [
                $user->email,
                $user->first_name . ' ' . $user->last_name,
                $user->role,
                $user->agency ?? '-',
                $user->is_active ? '✓' : '✗',
                $user->email_verified_at ? '✓' : '✗',
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info('Total users: ' . $users->count());

        // Show role breakdown
        $this->newLine();
        $this->line('Users by role:');
        $roleCount = User::selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->get();

        foreach ($roleCount as $role) {
            $this->line("  {$role->role}: {$role->count}");
        }

        return 0;
    }
}
