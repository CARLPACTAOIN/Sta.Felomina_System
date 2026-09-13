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
            $table->foreignId('suggested_document_category_id')->nullable()->after('document_category_id')->constrained('document_categories')->restrictOnDelete();
            $table->foreignId('category_suggestion_rule_id')->nullable()->after('suggested_document_category_id')->constrained('categorization_rules')->restrictOnDelete();
            $table->json('category_suggestion_terms')->nullable()->after('category_suggestion_rule_id');
            $table->text('category_suggestion_reason')->nullable()->after('category_suggestion_terms');
            $table->timestamp('category_suggested_at')->nullable()->after('category_suggestion_reason');
            $table->timestamp('category_overridden_at')->nullable()->after('category_suggested_at');
            $table->foreignId('category_overridden_by_id')->nullable()->after('category_overridden_at')->constrained('users')->restrictOnDelete();

            $table->index(['document_category_id', 'status', 'document_date']);
            $table->index(['suggested_document_category_id', 'category_suggested_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_records', function (Blueprint $table) {
            $table->dropIndex(['document_category_id', 'status', 'document_date']);
            $table->dropIndex(['suggested_document_category_id', 'category_suggested_at']);
            $table->dropConstrainedForeignId('category_overridden_by_id');
            $table->dropColumn('category_overridden_at');
            $table->dropColumn('category_suggested_at');
            $table->dropColumn('category_suggestion_reason');
            $table->dropColumn('category_suggestion_terms');
            $table->dropConstrainedForeignId('category_suggestion_rule_id');
            $table->dropConstrainedForeignId('suggested_document_category_id');
        });
    }
};
