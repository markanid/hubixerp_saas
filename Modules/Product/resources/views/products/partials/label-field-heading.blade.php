<strong class="heading-long">{{ $heading }}:</strong>
<strong class="heading-short">{{ (($thermalSettings['label_width_mm'] ?? 80) <= 32 ? [
    'product_code' => 'Code', 'product_name' => 'Name', 'purchase_price' => 'C',
    'mrp' => 'MRP', 'sale_price' => 'SP', 'batch_number' => 'B', 'expiry_date' => 'Exp',
] : [
    'product_code' => 'Code', 'product_name' => 'Name', 'purchase_price' => 'Cost',
    'mrp' => 'MRP', 'sale_price' => 'Price', 'batch_number' => 'Batch', 'expiry_date' => 'Exp',
])[$field] ?? $heading }}:</strong>
