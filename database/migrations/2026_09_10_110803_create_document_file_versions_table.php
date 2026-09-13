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
        Schema::create('document_file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_record_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->boolean('is_current')->default(true);
            $table->timestamp('approved_at');
            $table->foreignId('approved_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['document_record_id', 'version_number']);
            $table->index(['document_record_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_file_versions');
    }
};
