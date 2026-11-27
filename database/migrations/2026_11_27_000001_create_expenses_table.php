<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('category', 100);
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('expense_date');
            $table->string('description', 500)->nullable();
            $table->string('receipt', 255)->nullable();
            $table->string('payment_method', 50);
            $table->boolean('is_approved')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['expense_date', 'category']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
