<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resident_document_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 24)->unique();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('purok_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('document_record_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('resident_name_snapshot');
            $table->string('address_snapshot');
            $table->string('household_code_snapshot');
            $table->string('household_head_snapshot');
            $table->string('purok_name_snapshot');
            $table->string('purpose', 500);
            $table->text('intake_notes')->nullable();
            $table->decimal('expected_fee', 12, 2);
            $table->string('status')->default('draft');
            $table->boolean('open_guard')->nullable();
            $table->text('latest_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('released_to_name')->nullable();
            $table->timestamps();

            $table->unique(['resident_id', 'document_type_id', 'open_guard']);
            $table->index(['purok_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resident_document_requests');
    }
};
