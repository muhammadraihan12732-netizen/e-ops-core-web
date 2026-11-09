<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // User Management: akun bisa dinonaktifkan tanpa dihapus (riwayat tetap utuh)
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_active')->default(true);
        });
        // Pembeda data demo (seeder) dan transaksi sungguhan
        Schema::table('transactions', function (Blueprint $t) {
            $t->boolean('is_demo')->default(false);
            $t->index('created_at');
        });
        Schema::table('transaction_items', function (Blueprint $t) {
            $t->index('created_at');
        });
        // Riwayat metrik sistem untuk grafik Monitoring
        Schema::create('system_metrics', function (Blueprint $t) {
            $t->id();
            $t->float('cpu_pct')->nullable();      // null jika OS tidak menyediakan
            $t->float('memory_pct')->nullable();   // memori sistem terpakai (%)
            $t->float('disk_pct');                 // storage terpakai (%)
            $t->float('db_ms');                    // waktu respons query ke database
            $t->boolean('db_up')->default(true);
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }
    public function down(): void {
        Schema::dropIfExists('system_metrics');
        Schema::table('transaction_items', fn (Blueprint $t) => $t->dropIndex(['created_at']));
        Schema::table('transactions', function (Blueprint $t) {
            $t->dropIndex(['created_at']);
            $t->dropColumn('is_demo');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_active'));
    }
};
