<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('roles', function (Blueprint $t) {
            $t->id(); $t->string('name')->unique(); $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained();
            $t->string('sku')->unique();
            $t->string('name');
            $t->decimal('price', 12, 2);
            $t->unsignedInteger('stock')->default(0);
            $t->unsignedInteger('min_stock')->default(10);
            $t->unsignedInteger('max_stock')->default(1000);
            $t->boolean('is_active')->default(true);
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->string('invoice')->unique();
            $t->decimal('total', 14, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('transaction_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained();
            $t->string('product_name'); // snapshot: riwayat aman walau produk diubah/dihapus
            $t->unsignedInteger('quantity');
            $t->decimal('price', 12, 2);
            $t->decimal('subtotal', 14, 2);
            $t->timestamps();
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action'); $t->string('description')->nullable(); $t->timestamps();
        });
        Schema::create('error_logs', function (Blueprint $t) {
            $t->id(); $t->text('message'); $t->text('context')->nullable(); $t->timestamps();
        });
        Schema::create('tickets', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained();
            $t->string('title'); $t->text('description');
            $t->string('category'); $t->string('priority')->default('medium');
            $t->string('status')->default('open'); $t->timestamps();
        });
        Schema::create('ticket_replies', function (Blueprint $t) {
            $t->id(); $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained(); $t->text('message'); $t->timestamps();
        });
    }
    public function down(): void {
        foreach (['ticket_replies','tickets','error_logs','activity_logs','transaction_items','transactions','products','categories'] as $n) Schema::dropIfExists($n);
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('role_id'));
        Schema::dropIfExists('roles');
    }
};
