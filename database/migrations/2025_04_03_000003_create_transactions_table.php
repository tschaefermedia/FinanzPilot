<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->string('description');
            $table->string('counterparty')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('source', ['sparkasse', 'paypal', 'manual', 'recurring'])->default('manual');
            $table->string('reference')->nullable();
            $table->string('hash', 64)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('date');
            $table->index('category_id');
            $table->index('import_batch_id');
        });
    }
    public function down(): void { Schema::dropIfExists('transactions'); }
};
