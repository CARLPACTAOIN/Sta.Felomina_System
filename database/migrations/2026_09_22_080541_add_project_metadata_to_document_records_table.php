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
            $table->boolean('is_project_related')->default(false)->after('keywords');
            $table->string('project_name')->nullable()->after('is_project_related');
            $table->string('project_location')->nullable()->after('project_name');
            $table->decimal('approved_budget', 14, 2)->nullable()->after('project_location');
            $table->date('project_start_date')->nullable()->after('approved_budget');
            $table->date('target_completion_date')->nullable()->after('project_start_date');
            $table->text('project_remarks')->nullable()->after('target_completion_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_records', function (Blueprint $table) {
            $table->dropColumn([
                'is_project_related',
                'project_name',
                'project_location',
                'approved_budget',
                'project_start_date',
                'target_completion_date',
                'project_remarks',
            ]);
        });
    }
};
