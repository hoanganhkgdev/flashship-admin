<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserRoleSyncSeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::all() as $user) {
            switch ($user->user_type) {
                case 'admin':
                    $user->syncRoles(['admin']);
                    break;

                case 'dispatcher':
                    $user->syncRoles(['dispatcher']);
                    break;

                case 'accountant':
                    $user->syncRoles(['accountant']);
                    break;

                case 'driver':
                    $user->syncRoles(['driver']);
                    break;
            }
        }
    }
}
