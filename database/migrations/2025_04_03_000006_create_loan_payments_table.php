<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['scheduled', 'extra', 'manual'])->default('scheduled');
            $table->timestamps();
            $table->index('loan_id');
        });
    }
    public function down(): void { Schema::dropIfExists('loan_payments'); }
};
