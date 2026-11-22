<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('scan_events', function (Blueprint $table) {
            // Keep an independent transaction_id index for the foreign key
            // before removing the composite unique index.
            $table->index('transaction_id', 'scan_events_transaction_id_index_for_fk');
            $table->dropUnique(['transaction_id', 'product_id']);
            $table->unique(['transaction_id', 'scanned_code'], 'scan_events_transaction_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('scan_events', function (Blueprint $table) {
            $table->dropUnique('scan_events_transaction_code_unique');
            $table->unique(['transaction_id', 'product_id']);
            $table->dropIndex('scan_events_transaction_id_index_for_fk');
        });
    }
};
