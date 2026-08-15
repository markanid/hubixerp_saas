<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('barcode_settings')) {
            Schema::create('barcode_settings', function (Blueprint $table) {
                $table->id();
                $table->string('context', 30)->unique();
                $table->json('selected_fields');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('stock_batches') && !Schema::hasColumn('stock_batches', 'sale_price')) {
            Schema::table('stock_batches', function (Blueprint $table) {
                $table->decimal('sale_price', 15, 2)->default(0)->after('mrp');
            });

            DB::table('stock_batches')->orderBy('id')->chunkById(500, function ($batches) {
                $prices = DB::table('product')
                    ->whereIn('product_code', $batches->pluck('product_id')->unique()->all())
                    ->pluck('price', 'product_code');

                foreach ($batches as $batch) {
                    DB::table('stock_batches')->where('id', $batch->id)->update([
                        'sale_price' => (float) ($prices[$batch->product_id] ?? 0),
                    ]);
                }
            });
        }

        if (!Schema::hasTable('inventory_labels')) {
            return;
        }

        if (!Schema::hasColumn('inventory_labels', 'signature')) {
            Schema::table('inventory_labels', function (Blueprint $table) {
                $table->string('signature', 64)->nullable()->after('barcode');
            });
        }

        DB::table('inventory_labels')->orderBy('id')->chunkById(500, function ($labels) {
            foreach ($labels as $label) {
                $source = $label->label_type === 'mrp'
                    ? DB::table('mrp_stock_lots')->where('id', $label->mrp_stock_lot_id)->first()
                    : DB::table('stock_batches')->where('id', $label->stock_batch_id)->first();

                $signature = $source
                    ? $this->signatureFor($label->label_type, $label->product_id, $source)
                    : hash('sha256', "legacy|{$label->label_type}|{$label->product_id}|{$label->id}");

                DB::table('inventory_labels')->where('id', $label->id)->update(['signature' => $signature]);
            }
        });

        $duplicates = DB::table('inventory_labels')
            ->select('product_id', 'label_type', 'signature', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->whereNotNull('signature')
            ->groupBy('product_id', 'label_type', 'signature')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('inventory_labels')
                ->where('product_id', $duplicate->product_id)
                ->where('label_type', $duplicate->label_type)
                ->where('signature', $duplicate->signature)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('inventory_labels', function (Blueprint $table) {
            $table->unique(
                ['product_id', 'label_type', 'signature'],
                'inventory_labels_slot_signature_unique'
            );
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_labels') && Schema::hasColumn('inventory_labels', 'signature')) {
            Schema::table('inventory_labels', function (Blueprint $table) {
                $table->dropUnique('inventory_labels_slot_signature_unique');
                $table->dropColumn('signature');
            });
        }

        if (Schema::hasTable('stock_batches') && Schema::hasColumn('stock_batches', 'sale_price')) {
            Schema::table('stock_batches', fn (Blueprint $table) => $table->dropColumn('sale_price'));
        }

        Schema::dropIfExists('barcode_settings');
    }

    private function signatureFor(string $type, string $productId, object $source): string
    {
        $parts = [
            $type,
            $productId,
            $this->money($source->purchase_rate ?? 0),
            $this->money($source->mrp ?? 0),
            $this->money($source->sale_price ?? 0),
        ];

        if ($type === 'batch') {
            $parts[] = trim((string) ($source->batch_no ?? ''));
            $parts[] = (string) ($source->expiry_date ?? '');
        }

        return hash('sha256', implode('|', $parts));
    }

    private function money(mixed $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }
};
