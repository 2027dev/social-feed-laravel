<?php

namespace Database\Seeders;

use App\Auth\RedisUserProvider;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's data.
     */
    public function run(): void
    {
        app(RedisUserProvider::class)->create('Test User', 'test@example.com', 'password');
    }
}
