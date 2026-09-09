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
        Schema::create('document_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->string('document_number', 64);
            $table->unsignedSmallInteger('document_year');
            $table->string('title');
            $table->date('document_date');
            $table->text('subject');
            $table->text('keywords')->nullable();
            $table->string('status')->default('draft');
            $table->string('verification_token', 64)->unique();
            $table->timestamps();

            $table->unique(['document_type_id', 'document_year', 'document_number']);
            $table->index(['status', 'document_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_records');
    }
};
