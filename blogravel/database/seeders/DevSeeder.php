<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DevSeeder extends Seeder
{
    protected $seeders = [
        UserSeeder::class,
    ];

    public function run(): void
    {
        foreach ($this->seeders as $seeder) {
            $this->call($seeder);
        }
    }
}
