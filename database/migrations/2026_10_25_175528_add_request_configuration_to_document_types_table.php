<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->boolean('is_requestable')->default(false)->after('is_active');
            $table->boolean('requests_enabled')->default(false)->after('is_requestable');
            $table->decimal('standard_fee', 12, 2)->nullable()->after('requests_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn(['is_requestable', 'requests_enabled', 'standard_fee']);
        });
    }
};
