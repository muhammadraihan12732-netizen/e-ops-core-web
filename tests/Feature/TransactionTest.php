<?php
namespace Tests\Feature;

class TransactionTest extends EOpsTestCase {
    public function test_happy_path_reduces_stock_and_computes_total(): void {
        $this->as('kasir');
        $a = $this->product(['price' => 10000, 'stock' => 10]);
        $b = $this->product(['price' => 2500, 'stock' => 5]);

        $res = $this->postJson('/api/transactions', ['items' => [
            ['product_id' => $a->id, 'quantity' => 3], ['product_id' => $b->id, 'quantity' => 2],
        ]]);

        $res->assertCreated();
        $this->assertEquals(35000, (float) $res->json('total')); // MySQL: string, SQLite: angka
        $this->assertSame(7, $a->fresh()->stock);
        $this->assertSame(3, $b->fresh()->stock);
        $this->assertCount(2, $res->json('items'));
    }

    public function test_same_product_in_multiple_lines_is_merged(): void {
        $this->as('kasir');
        $p = $this->product(['price' => 1000, 'stock' => 10]);

        $res = $this->postJson('/api/transactions', ['items' => [
            ['product_id' => $p->id, 'quantity' => 4], ['product_id' => $p->id, 'quantity' => 5],
        ]]);

        $res->assertCreated();
        $this->assertCount(1, $res->json('items'));
        $this->assertSame(9, $res->json('items.0.quantity'));
        $this->assertSame(1, $p->fresh()->stock);
    }

    public function test_split_lines_cannot_bypass_stock_check(): void {
        $this->as('kasir');
        $p = $this->product(['stock' => 5]);

        $this->postJson('/api/transactions', ['items' => [
            ['product_id' => $p->id, 'quantity' => 3], ['product_id' => $p->id, 'quantity' => 3],
        ]])->assertStatus(422);

        $this->assertSame(5, $p->fresh()->stock);
    }

    public function test_failure_rolls_back_everything(): void {
        $this->as('kasir');
        $ok = $this->product(['stock' => 10]);
        $low = $this->product(['stock' => 1]);

        $this->postJson('/api/transactions', ['items' => [
            ['product_id' => $ok->id, 'quantity' => 4], ['product_id' => $low->id, 'quantity' => 2],
        ]])->assertStatus(422);

        $this->assertSame(10, $ok->fresh()->stock, 'stok produk pertama harus kembali utuh');
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);
    }

    public function test_inactive_and_deleted_products_are_rejected(): void {
        $this->as('kasir');
        $inactive = $this->product(['is_active' => false]);
        $gone = $this->product();
        $gone->delete();

        $this->postJson('/api/transactions', ['items' => [['product_id' => $inactive->id, 'quantity' => 1]]])->assertStatus(422);
        $this->postJson('/api/transactions', ['items' => [['product_id' => $gone->id, 'quantity' => 1]]])->assertStatus(422);
    }

    public function test_validation_rejects_bad_payloads(): void {
        $this->as('kasir');
        $p = $this->product();
        $this->postJson('/api/transactions', ['items' => []])->assertStatus(422);
        $this->postJson('/api/transactions', ['items' => [['product_id' => $p->id, 'quantity' => 0]]])->assertStatus(422);
        $this->postJson('/api/transactions', ['items' => [['product_id' => 999999, 'quantity' => 1]]])->assertStatus(422);
    }

    public function test_invoice_numbers_are_unique(): void {
        $this->as('kasir');
        $p = $this->product(['stock' => 50]);
        $inv = collect(range(1, 5))->map(fn () => $this->postJson('/api/transactions', ['items' => [['product_id' => $p->id, 'quantity' => 1]]])->json('invoice'));
        $this->assertCount(5, $inv->unique());
    }

    public function test_only_admin_and_kasir_can_transact(): void {
        $p = $this->product();
        $this->as('analyst');
        $this->postJson('/api/transactions', ['items' => [['product_id' => $p->id, 'quantity' => 1]]])->assertForbidden();
    }
}
