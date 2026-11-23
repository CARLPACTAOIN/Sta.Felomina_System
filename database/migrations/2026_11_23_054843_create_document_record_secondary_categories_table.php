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
        Schema::create('document_record_secondary_categories', function (Blueprint $table) {
            $table->foreignId('document_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_category_id')->constrained()->restrictOnDelete();
            $table->primary(['document_record_id', 'document_category_id']);
            $table->index(['document_category_id', 'document_record_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_record_secondary_categories');
    }
};
