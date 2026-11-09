<?php
namespace App\Http\Controllers;
use App\Models\{ActivityLog, Category};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller {
    public function index() { return Category::withCount('products')->orderBy('name')->get(); }

    public function store(Request $r) {
        $c = Category::create($this->valid($r));
        ActivityLog::record('category.create', $c->name);
        return response()->json($c, 201);
    }

    public function update(Request $r, Category $category) {
        $category->update($this->valid($r, $category->id));
        ActivityLog::record('category.update', $category->name);
        return $category;
    }

    public function destroy(Category $category) {
        // withTrashed: produk yang di-soft-delete tetap menahan FK category_id
        $n = $category->products()->withTrashed()->count();
        abort_if($n > 0, 422, "Kategori masih dipakai {$n} produk. Pindahkan produknya terlebih dahulu.");
        $category->delete();
        ActivityLog::record('category.delete', $category->name);
        return ['ok' => true];
    }

    private function valid(Request $r, $id = null) {
        return $r->validate(['name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($id)]]);
    }
}
