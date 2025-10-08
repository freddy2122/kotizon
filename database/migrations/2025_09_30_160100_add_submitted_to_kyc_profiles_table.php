<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->boolean('submitted')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->dropColumn('submitted');
        });
    }
};
