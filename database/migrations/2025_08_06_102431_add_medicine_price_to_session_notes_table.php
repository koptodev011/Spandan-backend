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
        Schema::table('session_notes', function (Blueprint $table) {
            $table->decimal('medicine_price', 10, 2)->nullable()->after('mental_health_notes');
            $table->text('voice_notes_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('session_notes', function (Blueprint $table) {
            $table->dropColumn('medicine_price');
            // Reverting voice_notes_path back to string if needed
            $table->string('voice_notes_path')->nullable()->change();
        });
    }
};
