<?php
namespace App\Http\Controllers;
use App\Models\{ActivityLog, Product, Transaction};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TransactionController extends Controller {
    public function index(Request $r) {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'search' => 'nullable|string|max:50']);
        return Transaction::with(['user:id,name', 'items'])
            ->when($r->from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($r->to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($r->search, fn ($q, $s) => $q->where('invoice', 'like', "%$s%"))
            ->latest()
            ->paginate(min((int) $r->get('per_page', 15), 100));
    }

    public function show(Transaction $transaction) {
        return $transaction->load(['user:id,name', 'items']);
    }

    public function store(Request $r) {
        $data = $r->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => 'required|integer|min:1|max:9999',
        ]);

        // Gabungkan baris produk yang sama, lalu urutkan berdasarkan id: urutan kunci yang konsisten mencegah deadlock.
        $lines = collect($data['items'])->groupBy('product_id')
            ->map(fn ($g, $id) => ['product_id' => (int) $id, 'quantity' => (int) $g->sum('quantity')])
            ->sortKeys()->values();

        try {
            $trx = DB::transaction(function () use ($lines) {
                $trx = Transaction::create(['user_id' => auth()->id(), 'invoice' => $this->invoiceNumber(), 'total' => 0]);
                $total = 0;
                foreach ($lines as $l) {
                    $p = Product::lockForUpdate()->find($l['product_id']); // cegah stok ganda
                    if (!$p) throw new \RuntimeException("Produk #{$l['product_id']} tidak tersedia");
                    if (!$p->is_active || $p->stock < $l['quantity']) {
                        throw new \RuntimeException("Stok {$p->name} tidak cukup (tersisa {$p->stock})");
                    }
                    $p->decrement('stock', $l['quantity']);
                    $sub = round($p->price * $l['quantity'], 2);
                    $total += $sub;
                    $trx->items()->create([
                        'product_id' => $p->id, 'product_name' => $p->name,
                        'quantity' => $l['quantity'], 'price' => $p->price, 'subtotal' => $sub,
                    ]);
                }
                $trx->update(['total' => $total]);
                return $trx;
            });
        } catch (\RuntimeException $e) {
            // Penolakan bisnis (stok kurang) bukan error sistem, jadi masuk Activity Log, bukan Error Log.
            // Semua DB::transaction di atas sudah di-rollback: stok tidak berubah.
            ActivityLog::record('transaction.rejected', $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
        ActivityLog::record('transaction.create', $trx->invoice);
        return response()->json($trx->load(['user:id,name', 'items']), 201);
    }

    /** INV-260109-K7F2QX. Acak 6 karakter + cek keunikan, menggantikan rand(10,99) yang bisa bentrok. */
    private function invoiceNumber(): string {
        do {
            $inv = 'INV-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
        } while (Transaction::where('invoice', $inv)->exists());
        return $inv;
    }
}
