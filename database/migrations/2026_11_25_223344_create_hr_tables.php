<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_number', 40)->unique();
            $table->string('name');
            $table->string('department', 60)->nullable();
            $table->string('job_title')->nullable();
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->date('hired_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->decimal('hours_worked', 6, 2)->default(0);
            $table->string('status', 20)->default('present');
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 20);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('employees');
    }
};
