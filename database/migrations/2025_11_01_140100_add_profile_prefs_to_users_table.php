<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('language')->nullable()->after('city');
            $table->string('timezone')->nullable()->after('language');
            $table->string('theme')->nullable()->after('timezone');
            $table->boolean('notif_email')->default(true)->after('theme');
            $table->boolean('notif_push')->default(false)->after('notif_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['language', 'timezone', 'theme', 'notif_email', 'notif_push']);
        });
    }
};
