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
        Schema::create('employment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();
            $table->foreignId('department_id')
                ->constrained('departments')
                ->restrictOnDelete();
            $table->foreignId('position_id')
                ->nullable()
                ->constrained('positions')
                ->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('change_reason', 500);
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
            $table->index('effective_to');

            $table->integer('current_marker')
                ->nullable()
                ->storedAs('CASE WHEN effective_to IS NULL THEN 1 ELSE NULL END');

            $table->unique(['employee_id', 'current_marker']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_assignments');
    }
};
