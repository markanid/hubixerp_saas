<?php

namespace Modules\Contacts\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Contacts\app\Models\Customer;

class ContactsDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Customer::walkIn();
    }
}
