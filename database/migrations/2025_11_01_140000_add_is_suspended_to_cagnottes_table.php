<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cagnottes', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false)->after('est_publiee');
            $table->string('suspension_reason')->nullable()->after('is_suspended');
        });
    }

    public function down(): void
    {
        Schema::table('cagnottes', function (Blueprint $table) {
            $table->dropColumn(['is_suspended', 'suspension_reason']);
        });
    }
};
