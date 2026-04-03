<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('category_rules', function (Blueprint $table) {
            $table->id();
            $table->string('pattern');
            $table->boolean('is_regex')->default(false);
            $table->foreignId('target_category_id')->constrained('categories')->cascadeOnDelete();
            $table->integer('priority')->default(0);
            $table->decimal('confidence', 5, 2)->default(0.50);
            $table->integer('hit_count')->default(0);
            $table->timestamps();
            $table->index('target_category_id');
        });
    }
    public function down(): void { Schema::dropIfExists('category_rules'); }
};
