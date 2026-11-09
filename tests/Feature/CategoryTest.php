<?php
namespace Tests\Feature;

class CategoryTest extends EOpsTestCase {
    public function test_admin_crud(): void {
        $this->as('admin');
        $id = $this->postJson('/api/categories', ['name' => 'Alat Tulis'])->assertCreated()->json('id');
        $this->putJson("/api/categories/$id", ['name' => 'ATK'])->assertOk()->assertJsonPath('name', 'ATK');
        $this->deleteJson("/api/categories/$id")->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $id]);
    }

    public function test_name_must_be_unique(): void {
        $this->as('admin');
        $this->postJson('/api/categories', ['name' => 'ATK'])->assertCreated();
        $this->postJson('/api/categories', ['name' => 'ATK'])->assertStatus(422);
    }

    public function test_cannot_delete_category_still_used_even_by_soft_deleted_product(): void {
        $this->as('admin');
        $p = $this->product();
        $p->delete();
        $this->deleteJson("/api/categories/{$p->category_id}")->assertStatus(422);
    }

    public function test_non_admin_can_read_but_not_write(): void {
        $this->as('kasir');
        $this->getJson('/api/categories')->assertOk();
        $this->postJson('/api/categories', ['name' => 'X'])->assertForbidden();
    }
}
