# Financial Console Relation Audit

## Root Cause

Runtime error `Unknown column ledger_entries.payment_transaction_id` in SuperAdmin Financial Console.

The `PaymentTransaction` model's `ledgerEntries()` relationship and the `LedgerEntry` model's `transaction()` relationship both relied on Eloquent's default foreign key convention, generating `payment_transaction_id` as the join column. That column does not exist in the `ledger_entries` table.

## Incorrect Relation

**`app/Models/PaymentTransaction.php:50-53`**
```php
public function ledgerEntries(): HasMany
{
    return $this->hasMany(LedgerEntry::class);
    // Eloquent assumes foreign key = payment_transaction_id  <-- WRONG
}
```

**`app/Models/LedgerEntry.php:27-30`**
```php
public function transaction(): BelongsTo
{
    return $this->belongsTo(PaymentTransaction::class);
    // Eloquent assumes foreign key = payment_transaction_id  <-- WRONG
}
```

## Correct Relation

**`app/Models/PaymentTransaction.php:50-53`** (fixed)
```php
public function ledgerEntries(): HasMany
{
    return $this->hasMany(LedgerEntry::class, 'transaction_id');
}
```

**`app/Models/LedgerEntry.php:27-30`** (fixed)
```php
public function transaction(): BelongsTo
{
    return $this->belongsTo(PaymentTransaction::class, 'transaction_id');
}
```

## Database Schema (Migration)

`database/migrations/2026_07_01_000004_create_ledger_entries_table.php:13`

```php
$table->unsignedBigInteger('transaction_id')->nullable();
```

The column is named `transaction_id`, **not** `payment_transaction_id`. No migration is needed — the column already exists.

## Files Modified

| File | Change |
|---|---|
| `app/Models/LedgerEntry.php:30` | Added `'transaction_id'` as explicit foreign key to `belongsTo` |
| `app/Models/PaymentTransaction.php:52` | Added `'transaction_id'` as explicit foreign key to `hasMany` |

## Migration Required?

**No.** The column `transaction_id` already exists in the `ledger_entries` table (created by the original migration). Only Eloquent relationship definitions were wrong.

## Regression Result

| Component | Status | Reason |
|---|---|---|
| Financial Console (list) | No regression | Controller fetches `PaymentTransaction::with('ledgerEntries')` — now uses correct FK |
| Transaction Detail Drawer | No regression | Accesses `$txn->ledgerEntries` via the fixed relationship — now returns data |
| Ledger Entries Table | No regression | Renders from `txn.ledger` array (already mapped in controller transform) |
| Payment Review (SuperAdmin) | No regression | Unchanged — uses `PaymentIntent`, not `LedgerEntry` |
| Billing Dashboard (Admin) | No regression | Unchanged — uses `Subscription`, `PaymentIntent`, not `LedgerEntry` |
| `LedgerService` | No regression | Uses direct `where('transaction_id', ...)` queries — never relied on Eloquent relationship |
| Frontend build | Passes (0 errors) | No JS changes needed |

## Manual QA Verification

### Financial Console List Page
- Route: `GET /superadmin/financial`
- Controller: `SuperAdminFinancialController@index`
- Eager-loads `ledgerEntries` on `PaymentTransaction` — now resolves correctly via `transaction_id`

### Transaction Detail Drawer
- Component: `SuperAdmin/Financial/Index.jsx` → `TransactionDetailDrawer`
- Accesses `txn.ledger` (built from `$txn->ledgerEntries` in controller transform)
- Previously crashed with unknown column error; now renders ledger entries

### Ledger Entry Creation
- Service: `LedgerService::record()` — uses explicit `transaction_id` column
- No Eloquent relationship involved in creation flow — unaffected

### Other Components
- Payment Review, Billing Dashboard, Payment History — none query `LedgerEntry` via `PaymentTransaction`
- No schema changes — zero risk of migration conflicts
