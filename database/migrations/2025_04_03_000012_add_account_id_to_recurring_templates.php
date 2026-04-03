<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('recurring_templates', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('auto_generate')->constrained()->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('recurring_templates', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
