<?php
namespace Database\Seeders;
use App\Models\{Category, Product, Role, Ticket, TicketReply, Transaction, TransactionItem, User};
use Illuminate\Database\Seeder;

/**
 * DATA DEMO. Semua transaksi hasil seeder ditandai is_demo = true,
 * sehingga laporan/CSV bisa membedakannya dari transaksi operasional sungguhan.
 */
class DatabaseSeeder extends Seeder {
    public function run(): void {
        mt_srand(20260101); // data demo relatif konsisten antar-run

        $roles = collect(['admin', 'kasir', 'analyst', 'support'])->mapWithKeys(fn ($n) => [$n => Role::create(['name' => $n])->id]);
        foreach (['admin' => 'Admin', 'kasir' => 'Kasir', 'analyst' => 'Data Analyst', 'support' => 'IT Support'] as $k => $n) {
            User::create(['name' => $n, 'email' => "$k@eops.test", 'password' => 'password', 'role_id' => $roles[$k], 'is_active' => true]);
        }
        $cats = collect(['Kitchen Appliances', 'Office Appliances', 'Electronics'])->map(fn ($n) => Category::create(['name' => $n]));
        $names = ['Bread Knife', 'Black Marker', 'Black Pen', 'Cake Moulds', 'Cookies Moulds', 'Cutter', 'Duster', 'Drawing Book', 'Eraser', 'Fruit Bowl', 'Stapler', 'Desk Lamp'];
        foreach ($names as $i => $n) {
            $stock = mt_rand(0, 3) ? mt_rand(100, 900) : 0;
            Product::create([
                'category_id' => $cats[$i % 3]->id, 'sku' => 'BS' . (34000 + $i * 37), 'name' => $n,
                'price' => mt_rand(5, 300) * 1000, 'stock' => $stock, 'is_active' => $stock > 0,
            ]);
        }

        $kasir = User::where('email', 'kasir@eops.test')->first();
        $products = Product::all();
        for ($i = 0; $i < 150; $i++) {
            $d = now()->subDays(mt_rand(0, 330))->setTime(mt_rand(8, 20), mt_rand(0, 59));
            $trx = Transaction::create([
                'user_id' => $kasir->id, 'invoice' => 'INV-S' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'total' => 0, 'is_demo' => true, 'created_at' => $d, 'updated_at' => $d,
            ]);
            $total = 0;
            foreach ($products->random(mt_rand(1, 3)) as $p) {
                $q = mt_rand(1, 5); $s = $p->price * $q; $total += $s;
                TransactionItem::create(['transaction_id' => $trx->id, 'product_id' => $p->id, 'product_name' => $p->name, 'quantity' => $q, 'price' => $p->price, 'subtotal' => $s, 'created_at' => $d, 'updated_at' => $d]);
            }
            $trx->update(['total' => $total]);
        }

        // Tiket contoh agar halaman Helpdesk tidak kosong saat demo
        $support = User::where('email', 'support@eops.test')->first();
        $t = Ticket::create(['user_id' => $kasir->id, 'title' => 'Printer struk tidak mencetak', 'description' => 'Printer di meja kasir 1 tidak merespons sejak pagi.', 'category' => 'Perangkat', 'priority' => 'high', 'status' => 'in_progress']);
        TicketReply::create(['ticket_id' => $t->id, 'user_id' => $support->id, 'message' => 'Sedang dicek, mohon cabut dan pasang ulang kabel USB.']);
        Ticket::create(['user_id' => $kasir->id, 'title' => 'Lupa password akun', 'description' => 'Tidak bisa login ke aplikasi kasir.', 'category' => 'Akun', 'priority' => 'medium', 'status' => 'open']);
    }
}
