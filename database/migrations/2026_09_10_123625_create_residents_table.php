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
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('resident_code')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->string('normalized_full_name');
            $table->string('sex');
            $table->date('birthdate');
            $table->string('civil_status');
            $table->string('contact_number')->nullable();
            $table->string('address');
            $table->foreignId('purok_id')->constrained()->restrictOnDelete();
            $table->foreignId('household_id')->constrained()->restrictOnDelete();
            $table->boolean('is_registered_voter')->default(false);
            $table->string('status')->default('active');
            $table->string('birthplace')->nullable();
            $table->string('occupation')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purok_id', 'status', 'last_name']);
            $table->index(['purok_id', 'normalized_full_name', 'birthdate']);
            $table->index(['household_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
