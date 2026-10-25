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
        if (DB::table('users')->whereNotNull('department_id')->exists()) {
            throw new RuntimeException(
                'The users.department_id column still contains department membership. Move every user into an employment assignment before running this migration.',
            );
        }

        // SQLite keeps explicit indexes when it rebuilds a table to drop a
        // foreign key, so the helper index must go before the column.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['department_id']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            $table->index('department_id');
        });
    }
};
