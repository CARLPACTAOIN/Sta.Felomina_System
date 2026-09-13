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
            $table->timestamp('archived_at')->nullable()->after('returned_by_id');
            $table->foreignId('archived_by_id')
                ->nullable()
                ->after('archived_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('archive_reason')->nullable()->after('archived_by_id');

            $table->timestamp('revoked_at')->nullable()->after('archive_reason');
            $table->foreignId('revoked_by_id')
                ->nullable()
                ->after('revoked_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('revoke_reason')->nullable()->after('revoked_by_id');

            $table->timestamp('superseded_at')->nullable()->after('revoke_reason');
            $table->foreignId('superseded_by_id')
                ->nullable()
                ->after('superseded_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('supersede_reason')->nullable()->after('superseded_by_id');
            $table->foreignId('superseded_by_document_record_id')
                ->nullable()
                ->after('supersede_reason')
                ->constrained('document_records')
                ->nullOnDelete();
            $table->text('supersede_reference')->nullable()->after('superseded_by_document_record_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('superseded_by_document_record_id');
            $table->dropConstrainedForeignId('superseded_by_id');
            $table->dropConstrainedForeignId('revoked_by_id');
            $table->dropConstrainedForeignId('archived_by_id');
            $table->dropColumn([
                'archived_at',
                'archive_reason',
                'revoked_at',
                'revoke_reason',
                'superseded_at',
                'supersede_reason',
                'supersede_reference',
            ]);
        });
    }
};
