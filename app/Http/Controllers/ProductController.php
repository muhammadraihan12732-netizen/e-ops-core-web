<?php
namespace App\Http\Controllers;
use App\Models\{ActivityLog, Product};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller {
    public function index(Request $r) {
        return Product::with('category')
            ->when($r->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%")))
            ->orderBy('name')
            ->paginate(min((int) $r->get('per_page', 10), 100));
    }
    public function store(Request $r) {
        $p = Product::create($this->valid($r));
        ActivityLog::record('product.create', $p->name);
        return response()->json($p, 201);
    }
    public function update(Request $r, Product $product) {
        $oldStock = (int) $product->stock;
        $product->update($this->valid($r, $product->id));
        $note = $oldStock !== (int) $product->stock ? " (stok {$oldStock} → " . (int) $product->stock . ')' : '';
        ActivityLog::record('product.update', $product->name . $note);
        return $product;
    }
    public function destroy(Product $product) {
        $product->delete(); // soft delete: riwayat transaksi tetap utuh
        ActivityLog::record('product.delete', $product->name);
        return ['ok' => true];
    }

    private function valid(Request $r, $id = null) {
        return $r->validate([
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($id)],
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'max_stock' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);
    }
}
