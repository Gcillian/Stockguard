<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // StockGuard runs with QUEUE_CONNECTION=sync. Queue records are not
        // needed by the local application, so remove them if an older install
        // created the tables.
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('failed_jobs');
    }

    public function down(): void
    {
        // Intentionally irreversible: these tables are not part of the
        // supported StockGuard database schema.
    }
};
