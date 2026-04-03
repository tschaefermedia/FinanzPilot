<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recurring_templates', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('frequency', ['weekly', 'monthly', 'quarterly', 'yearly']);
            $table->date('next_due_date');
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_generate')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('recurring_templates'); }
};
