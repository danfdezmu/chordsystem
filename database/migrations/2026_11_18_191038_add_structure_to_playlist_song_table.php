<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playlist_song', function (Blueprint $table) {
            $table->text('structure')->nullable()->after('key_override');
        });
    }

    public function down(): void
    {
        Schema::table('playlist_song', function (Blueprint $table) {
            $table->dropColumn('structure');
        });
    }
};