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
            $table->timestamp('submitted_at')->nullable()->after('draft_pdf_uploaded_by_id');
            $table->foreignId('submitted_by_id')
                ->nullable()
                ->after('submitted_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('submitted_by_id');
            $table->foreignId('approved_by_id')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('return_comment')->nullable()->after('approved_by_id');
            $table->timestamp('returned_at')->nullable()->after('return_comment');
            $table->foreignId('returned_by_id')
                ->nullable()
                ->after('returned_at')
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
            $table->dropConstrainedForeignId('returned_by_id');
            $table->dropConstrainedForeignId('approved_by_id');
            $table->dropConstrainedForeignId('submitted_by_id');
            $table->dropColumn([
                'submitted_at',
                'approved_at',
                'return_comment',
                'returned_at',
            ]);
        });
    }
};
