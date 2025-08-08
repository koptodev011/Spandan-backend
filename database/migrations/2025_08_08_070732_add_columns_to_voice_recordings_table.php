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
        Schema::table('voice_recordings', function (Blueprint $table) {
            $table->string('original_name')->after('recording_path');
            $table->unsignedBigInteger('file_size')->after('original_name');
            $table->string('mime_type')->after('file_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voice_recordings', function (Blueprint $table) {
            $table->dropColumn(['original_name', 'file_size', 'mime_type']);
        });
    }
};
