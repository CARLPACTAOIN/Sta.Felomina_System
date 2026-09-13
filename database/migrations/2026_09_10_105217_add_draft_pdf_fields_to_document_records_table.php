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
        Schema::table('document_records', function (Blueprint $table) {
            $table->string('draft_pdf_path')->nullable()->after('verification_token');
            $table->string('draft_pdf_original_name')->nullable()->after('draft_pdf_path');
            $table->string('draft_pdf_mime_type')->nullable()->after('draft_pdf_original_name');
            $table->unsignedBigInteger('draft_pdf_size')->nullable()->after('draft_pdf_mime_type');
            $table->timestamp('draft_pdf_uploaded_at')->nullable()->after('draft_pdf_size');
            $table->foreignId('draft_pdf_uploaded_by_id')
                ->nullable()
                ->after('draft_pdf_uploaded_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('draft_pdf_uploaded_by_id');
            $table->dropColumn([
                'draft_pdf_path',
                'draft_pdf_original_name',
                'draft_pdf_mime_type',
                'draft_pdf_size',
                'draft_pdf_uploaded_at',
            ]);
        });
    }
};
