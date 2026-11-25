<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('locale', 5)->default('ar')->after('password');
            $table->string('theme', 10)->default('system')->after('locale');
            $table->foreignId('branch_id')->nullable()->after('theme')->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->string('job_title')->nullable()->after('department_id');
            $table->string('avatar_path')->nullable()->after('job_title');
            $table->boolean('is_active')->default(true)->after('avatar_path');
            $table->boolean('two_factor_enabled')->default(false)->after('is_active');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_secret');
            $table->softDeletes();
        });

        Schema::create('branch_user', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->primary(['branch_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['department_id']);
            $table->dropColumn([
                'username', 'phone', 'locale', 'theme', 'branch_id', 'department_id',
                'job_title', 'avatar_path', 'is_active', 'two_factor_enabled',
                'two_factor_secret', 'last_login_at', 'deleted_at',
            ]);
            $table->string('email')->nullable(false)->change();
        });
    }
};
