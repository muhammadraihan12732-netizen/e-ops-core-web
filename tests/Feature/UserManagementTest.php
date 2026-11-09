<?php
namespace Tests\Feature;

use App\Models\Role;

class UserManagementTest extends EOpsTestCase {
    public function test_non_admin_is_forbidden(): void {
        foreach (['kasir', 'analyst', 'support'] as $role) {
            $this->as($role);
            $this->getJson('/api/users')->assertForbidden();
        }
    }

    public function test_admin_creates_user_who_can_login(): void {
        $this->as('admin');
        $rid = Role::firstOrCreate(['name' => 'kasir'])->id;

        $this->postJson('/api/users', ['name' => 'Budi', 'email' => 'budi@eops.test', 'password' => 'rahasia123', 'role_id' => $rid])->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'budi@eops.test', 'password' => 'rahasia123'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_email_must_be_unique_and_password_min_8(): void {
        $admin = $this->as('admin');
        $this->postJson('/api/users', ['name' => 'X', 'email' => $admin->email, 'password' => 'rahasia123', 'role_id' => $admin->role_id])->assertStatus(422);
        $this->postJson('/api/users', ['name' => 'X', 'email' => 'x@eops.test', 'password' => 'pendek', 'role_id' => $admin->role_id])->assertStatus(422);
    }

    public function test_deactivated_user_cannot_login(): void {
        $this->userWithRole('kasir', ['email' => 'off@eops.test', 'is_active' => false]);
        $this->postJson('/api/login', ['email' => 'off@eops.test', 'password' => 'password'])->assertForbidden();
    }

    public function test_deactivating_revokes_existing_tokens(): void {
        $this->as('admin');
        $u = $this->userWithRole('kasir');
        $u->createToken('spa');
        $this->putJson("/api/users/{$u->id}", ['name' => $u->name, 'email' => $u->email, 'role_id' => $u->role_id, 'is_active' => false])->assertOk();
        $this->assertSame(0, $u->tokens()->count());
        $this->assertFalse($u->fresh()->is_active);
    }

    public function test_admin_cannot_demote_or_deactivate_self(): void {
        $admin = $this->as('admin');
        $kasir = Role::firstOrCreate(['name' => 'kasir']);
        $base = ['name' => $admin->name, 'email' => $admin->email];
        $this->putJson("/api/users/{$admin->id}", $base + ['role_id' => $kasir->id])->assertStatus(422);
        $this->putJson("/api/users/{$admin->id}", $base + ['role_id' => $admin->role_id, 'is_active' => false])->assertStatus(422);
    }

    public function test_blank_password_keeps_old_password(): void {
        $this->as('admin');
        $u = $this->userWithRole('kasir', ['email' => 'keep@eops.test']);
        $this->putJson("/api/users/{$u->id}", ['name' => 'Baru', 'email' => $u->email, 'role_id' => $u->role_id, 'password' => ''])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'keep@eops.test', 'password' => 'password'])->assertOk();
    }
}
