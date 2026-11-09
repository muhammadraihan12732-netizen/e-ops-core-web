<?php
namespace Tests\Feature;

use App\Models\{Category, Product, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class EOpsTestCase extends TestCase {
    use RefreshDatabase;

    protected function userWithRole(string $role, array $attrs = []): User {
        $r = Role::firstOrCreate(['name' => $role]);
        return User::create($attrs + [
            'name' => ucfirst($role), 'email' => "$role-" . uniqid() . '@eops.test',
            'password' => 'password', 'role_id' => $r->id, 'is_active' => true,
        ]);
    }
    protected function as(string $role): User {
        $u = $this->userWithRole($role);
        Sanctum::actingAs($u);
        return $u;
    }
    protected function product(array $attrs = []): Product {
        $c = Category::firstOrCreate(['name' => 'Umum']);
        return Product::create($attrs + [
            'category_id' => $c->id, 'sku' => 'SKU' . uniqid(), 'name' => 'Produk ' . uniqid(),
            'price' => 10000, 'stock' => 10, 'is_active' => true,
        ]);
    }
}
