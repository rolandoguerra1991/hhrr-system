<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('departments')->whereNotNull('manager_id')->exists()) {
            throw new RuntimeException(
                'The departments.manager_id column still references users. Map every department manager to an employee before running this migration.',
            );
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')
                ->references('id')
                ->on('employees')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('departments')->whereNotNull('manager_id')->exists()) {
            throw new RuntimeException(
                'The departments.manager_id column still references employees. Clear or remap every department manager before rolling back this migration.',
            );
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
