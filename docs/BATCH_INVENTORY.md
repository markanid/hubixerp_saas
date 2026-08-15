# HubixERP Batch Inventory

## Activation

1. Run `php artisan migrate`.
2. Open Company settings and set **Inventory Mode** to `Batch-wise inventory`.
3. Set **Expiry Alert Days**.
4. Enable **Batch Managed** only for products that require batch tracking.

The default is `standard`. In standard mode the original `stock` and
`stock_ledgers` workflows continue unchanged and no batch records are written.

## Design

- `stock_batches` stores the current quantity and available quantity per
  product/batch.
- `batch_movements` is the immutable audit/allocation ledger. It is required
  because one sale line can consume several batches under FIFO/FEFO.
- HubixERP's existing `stock` table remains an aggregate compatibility
  projection, so existing stock screens and reports continue to work.
- The service uses row locks and the surrounding transaction to prevent two
  users from allocating the same stock.
- Dated batches are allocated by earliest expiry, then ID. Batches without an
  expiry date are allocated last.
- A manually selected sale batch is honored and validated.
- Sale returns restore the exact movement allocations from the original sale.
- Purchase returns require a batch and cannot exceed its available quantity.

HubixERP currently uses a database-per-company style (`company` has one active
row). The new tables deliberately do not carry a hardcoded company ID. This is
compatible with stancl/tenancy when these migrations run on each tenant
database and all models use the tenant's active connection.

## Reports

The sidebar exposes these reports only in batch mode:

- Batch Stock
- Near Expiry
- Expired Stock
- Batch Movement
- Batch Ledger
- Batch Profitability

All reports support product, batch and date filters. Near Expiry uses the
company's `expiry_alert_days` setting.

## Deployment checklist

- Back up the database.
- Run migrations in staging, then production.
- Confirm existing companies have `inventory_mode = standard`.
- Create one batch-managed test product.
- Purchase the same batch twice and confirm one batch row is increased.
- Purchase two batches with different expiries.
- Make an automatic sale spanning both batches and confirm earliest expiry is
  consumed first.
- Make a manual-batch sale and confirm only that batch is reduced.
- Confirm overselling and over-returning are rejected.
- Return a sale using the original sale voucher and confirm the original
  batch allocations are restored.
- Return a purchase and confirm the selected batch is reduced.
- Edit and cancel purchase, sale and return documents and verify reversal
  movements and aggregate stock.
- Verify all six reports and expiry badge.
- Switch back to standard mode and repeat legacy purchase/sale/return smoke
  tests.
- Under stancl/tenancy, run the migration for every tenant database and repeat
  the isolation test with two tenants.

## Automated verification

Run:

```bash
php artisan test --testsuite=Unit
```

The focused tests cover standard-mode isolation, duplicate batch merging,
earliest-expiry allocation, manual-batch stock validation and sale payload
preservation.
