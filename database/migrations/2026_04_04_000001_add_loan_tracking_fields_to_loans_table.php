<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('monthly_rate', 10, 2)->nullable()->after('payment_day');
            $table->decimal('initial_balance', 12, 2)->nullable()->after('monthly_rate');
            $table->string('match_description')->nullable()->after('initial_balance');
            $table->foreignId('account_id')->nullable()->after('match_description')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn(['monthly_rate', 'initial_balance', 'match_description', 'account_id']);
        });
    }
};
