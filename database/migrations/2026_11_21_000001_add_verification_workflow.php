<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('scan_events', function (Blueprint $table) {
            $table->string('scanned_code', 100)->nullable();
            $table->unique(['transaction_id', 'product_id']);
        });

        Schema::table('vision_events', function (Blueprint $table) {
            $table->string('evidence_path')->nullable();
            $table->json('bounding_boxes')->nullable();
            $table->string('source', 20)->default('yolo');
        });

        Schema::table('anomalies', function (Blueprint $table) {
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->unique(['transaction_id', 'product_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action', 60);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('audit_logs');
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropUnique(['transaction_id', 'product_id']));
        Schema::table('anomalies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewer_id');
            $table->dropColumn(['review_note', 'reviewed_at']);
        });
        Schema::table('vision_events', fn (Blueprint $table) => $table->dropColumn(['evidence_path', 'bounding_boxes', 'source']));
        Schema::table('scan_events', function (Blueprint $table) {
            $table->dropUnique(['transaction_id', 'product_id']);
            $table->dropColumn('scanned_code');
        });
    }
};
