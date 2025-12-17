<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create 5 users for login
        $users = User::factory()
            ->count(2)
            ->sequence(
                ...collect(range(1, 2))->map(function ($index) {
                    return [
                        'name' => 'User '.$index,
                        'email' => 'user'.$index.'@example.com',
                        'password' => Hash::make('password'),
                        'balance' => 1000000,
                    ];
                })->toArray()
            )
            ->create();

        // Create assets for each user (BTC and ETH)
        foreach ($users as $user) {
            // Give each user some BTC
            Asset::create([
                'user_id' => $user->id,
                'symbol' => 'BTC',
                'amount' => '10.00', // 10 BTC
                'locked_amount' => '0.00',
            ]);

            // Give each user some ETH
            Asset::create([
                'user_id' => $user->id,
                'symbol' => 'ETH',
                'amount' => '100.00', // 100 ETH
                'locked_amount' => '0.00',
            ]);
        }
    }
}
