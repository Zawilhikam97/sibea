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
        Schema::table('status_histories', function (Blueprint $table) {
            $table->foreignId('pendaftaran_id')->nullable()->change();
            $table->string('action_type')->default('update')->after('user_id'); // update, failed, skipped
            $table->string('nim')->nullable()->after('action_type');
            $table->string('reason')->nullable()->after('note');
            $table->foreignId('periode_beasiswa_id')->nullable()->after('pendaftaran_id')
                ->constrained('periode_beasiswas')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('status_histories', function (Blueprint $table) {
            $table->dropForeign(['periode_beasiswa_id']);
            $table->dropColumn(['action_type', 'nim', 'reason', 'periode_beasiswa_id']);
        });
    }
};
