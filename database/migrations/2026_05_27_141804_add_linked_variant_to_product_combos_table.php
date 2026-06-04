<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add variant-level linking to product combos.
     *
     * MySQL refuses to drop a unique index if a foreign key depends on it.
     * We use raw SQL with FOREIGN_KEY_CHECKS=0 to safely restructure.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::statement('
            ALTER TABLE product_combos
            DROP INDEX product_combo_unique,
            ADD COLUMN linked_variant_id BIGINT UNSIGNED NULL AFTER combo_product_id,
            ADD CONSTRAINT product_combos_linked_variant_id_foreign
                FOREIGN KEY (linked_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
            ADD UNIQUE KEY product_combo_variant_unique (product_id, combo_product_id, linked_variant_id),
            ADD INDEX product_combos_linked_variant_id_index (linked_variant_id)
        ');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::statement('
            ALTER TABLE product_combos
            DROP INDEX product_combo_variant_unique,
            DROP INDEX product_combos_linked_variant_id_index,
            DROP FOREIGN KEY product_combos_linked_variant_id_foreign,
            DROP COLUMN linked_variant_id,
            ADD UNIQUE KEY product_combo_unique (product_id, combo_product_id)
        ');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
