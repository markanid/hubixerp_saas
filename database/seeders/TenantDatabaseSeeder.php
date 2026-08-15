<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('company')->updateOrInsert(
            ['id' => 1],
            $this->onlyExistingColumns('company', [
                'company' => 'Demo Company',
                'licence_no' => 'DEMO-LIC-001',
                'address' => 'Demo Market Road',
                'phone' => '9999999999',
                'email' => 'demo@example.com',
                'website' => 'https://example.com',
                'gst_no' => '22AAAAA0000A1Z5',
                'bank_name' => 'Demo Bank',
                'bank_ifsc' => 'DEMO0001234',
                'bank_acno' => '1234567890',
                'bank_branch' => 'Demo Branch',
                'tags' => 'demo',
                'inventory_mode' => 'standard',
                'expiry_alert_days' => 30,
                'currency_code' => 'INR',
                'currency_symbol' => 'Rs.',
                'tax_type' => 'gst',
                'gst_scheme' => 'regular',
                'created_at' => $now,
                'updated_at' => $now,
            ])
        );

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@demo.test'],
            $this->onlyExistingColumns('users', [
                'user_name' => 'Demo Admin',
                'email' => 'admin@demo.test',
                'password' => Hash::make('password'),
                'user_role' => 'Super Admin',
                'created_at' => $now,
                'updated_at' => $now,
            ])
        );

        DB::table('groups')->updateOrInsert(
            ['groups' => 'General'],
            $this->onlyExistingColumns('groups', [
                'groups' => 'General',
                'status' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ])
        );

        DB::table('brand')->updateOrInsert(
            ['brand' => 'Demo'],
            $this->onlyExistingColumns('brand', [
                'brand' => 'Demo',
                'created_at' => $now,
                'updated_at' => $now,
            ])
        );

        $brandId = DB::table('brand')->where('brand', 'Demo')->value('id');
        $groupId = DB::table('groups')->where('groups', 'General')->value('id');

        DB::table('category')->updateOrInsert(
            ['category' => 'General'],
            $this->onlyExistingColumns('category', [
                'category' => 'General',
                'brandid' => $brandId,
                'status' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ])
        );

        $categoryId = DB::table('category')->where('category', 'General')->value('id');

        DB::table('subcategory')->updateOrInsert(
            ['subcategory' => 'Default'],
            $this->onlyExistingColumns('subcategory', [
                'subcategory' => 'Default',
                'categoryid' => $categoryId,
                'status' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ])
        );

        DB::table('excategory')->updateOrInsert(
            ['category' => 'General Expense'],
            $this->onlyExistingColumns('excategory', [
                'category' => 'General Expense',
                'status' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ])
        );

        $subcategoryId = DB::table('subcategory')->where('subcategory', 'Default')->value('id');

        DB::table('customer')->updateOrInsert(
            ['phone' => '9000000001'],
            $this->onlyExistingColumns('customer', [
                'customer' => 'Demo Customer',
                'email' => 'cust@demo.test',
                'phone' => '9000000001',
                'address' => 'Demo Customer Address',
                'gstin_no' => '22BBBBB0000B1Z5',
                'op_balance' => 0,
                'status' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ])
        );

        DB::table('vendor')->updateOrInsert(
            ['cp_phone' => '9000000002'],
            $this->onlyExistingColumns('vendor', [
                'cp_name' => 'Demo Supplier',
                'cp_phone' => '9000000002',
                'cp_email' => 'ven@demo.test',
                'cp_cname' => 'Demo Supplier Co',
                'cp_cphone' => '9000000002',
                'cp_address' => 'Demo Supplier Address',
                'cp_website' => 'example.com',
                'cp_gst_no' => '22CCCCC0000C1Z5',
                'op_balance' => 0,
                'status' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ])
        );

        DB::table('banking')->updateOrInsert(
            ['bk_account' => 'DEMO-CASH'],
            $this->onlyExistingColumns('banking', [
                'bk_bank' => 'Cash',
                'bk_account' => 'DEMO-CASH',
                'bk_branch' => 'Main',
                'bk_ifsc' => 'CASH0000001',
                'bk_opbalance' => 10000,
                'bk_clbalance' => 10000,
                'bk_status' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ])
        );

        foreach ($this->products($brandId, $categoryId, $subcategoryId, $groupId, $now) as $product) {
            DB::table('product')->updateOrInsert(
                ['product_code' => $product['product_code']],
                $this->onlyExistingColumns('product', $product)
            );

            DB::table('stock')->updateOrInsert(
                ['stock_product_id' => $product['product_code']],
                $this->onlyExistingColumns('stock', [
                    'stock_date' => $now->toDateString(),
                    'stock_product_id' => $product['product_code'],
                    'stock_qty' => $product['opening_stock'],
                    'status' => '1',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );

            if (Schema::hasTable('stock_batches')) {
                DB::table('stock_batches')->updateOrInsert(
                    [
                        'product_id' => $product['product_code'],
                        'batch_no' => 'OPENING',
                    ],
                    $this->onlyExistingColumns('stock_batches', [
                        'product_id' => $product['product_code'],
                        'batch_no' => 'OPENING',
                        'expiry_date' => null,
                        'purchase_rate' => $product['pprice'],
                        'mrp' => $product['mrp'],
                        'quantity' => $product['opening_stock'],
                        'available_quantity' => $product['opening_stock'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }
    }

    private function products($brandId, $categoryId, $subcategoryId, $groupId, $now): array
    {
        $base = [
            'hsn_code' => '0000',
            'margin' => 20,
            'unit' => 'PCS',
            'uqty' => 1,
            'gst' => 18,
            'maxquantity' => 100,
            'minquantity' => 5,
            'brandid' => $brandId,
            'categoryid' => $categoryId,
            'subcategoryid' => $subcategoryId,
            'groupid' => $groupId,
            'typeid' => 1,
            'is_batch_managed' => false,
            'status' => '1',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return [
            array_merge($base, [
                'product_code' => 'DEMO-001',
                'product' => 'Demo Product 1',
                'mrp' => 120,
                'price' => 100,
                'pprice' => 80,
                'bar_code' => '890000000001',
                'opening_stock' => 25,
            ]),
            array_merge($base, [
                'product_code' => 'DEMO-002',
                'product' => 'Demo Product 2',
                'mrp' => 240,
                'price' => 200,
                'pprice' => 160,
                'bar_code' => '890000000002',
                'opening_stock' => 15,
            ]),
            array_merge($base, [
                'product_code' => 'DEMO-003',
                'product' => 'Demo Service Item',
                'mrp' => 500,
                'price' => 500,
                'pprice' => 0,
                'bar_code' => '890000000003',
                'opening_stock' => 0,
            ]),
        ];
    }

    private function onlyExistingColumns(string $table, array $values): array
    {
        return collect($values)
            ->filter(fn ($value, string $column) => Schema::hasColumn($table, $column))
            ->all();
    }
}
