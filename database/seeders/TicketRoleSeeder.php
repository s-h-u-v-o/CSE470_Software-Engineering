<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Role;
use Illuminate\Database\Seeder;

/**
 * Synthetic users with roles for exercising the F16/F18 ticket screens.
 * Run with: php artisan db:seed --class=TicketRoleSeeder
 */
class TicketRoleSeeder extends Seeder
{
    public function run(): void
    {
        $people = [
            ['dept@biomedops.test', 'Dana (Department User)', Role::DEPARTMENT_USER],
            ['engineer1@biomedops.test', 'Eli (Biomedical Engineer)', Role::ENGINEER],
            ['engineer2@biomedops.test', 'Esha (Biomedical Engineer)', Role::ENGINEER],
            ['supervisor@biomedops.test', 'Sam (Supervisor)', Role::SUPERVISOR],
            ['auditor@biomedops.test', 'Ari (Auditor)', Role::AUDITOR],
        ];

        foreach ($people as [$email, $name, $role]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => bcrypt('password')]);
            $user->role = $role;
            $user->save();
        }

        $this->command?->info('Ticket role users seeded (password: password).');
    }
}
