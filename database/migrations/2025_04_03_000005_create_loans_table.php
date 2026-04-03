<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['bank', 'informal']);
            $table->decimal('principal', 12, 2);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->date('start_date');
            $table->integer('term_months')->nullable();
            $table->integer('payment_day')->nullable();
            $table->enum('direction', ['owed_by_me', 'owed_to_me']);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('loans'); }
};
