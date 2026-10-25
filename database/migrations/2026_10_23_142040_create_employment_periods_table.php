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
        Schema::create('employment_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->string('start_reason', 255);
            $table->text('termination_reason')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('terminated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'started_on']);
            $table->index('ended_on');

            $table->integer('open_marker')
                ->nullable()
                ->storedAs('CASE WHEN ended_on IS NULL THEN 1 ELSE NULL END');

            $table->unique(['employee_id', 'open_marker']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_periods');
    }
};
