<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Segment;
use App\Models\User;
use App\Models\UserAccess;
use App\Services\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function grant(User $user, string $role, int $segment, ?int $branch = null): void
    {
        (new UserAccess)->forceFill(['user_id' => $user->id, 'role_id' => Role::findByName($role)->id, 'segment_id' => $segment, 'branch_id' => $branch])->save();
    }

    public function test_guest_inactive_unverified_and_missing_two_factor_are_blocked(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('super-admin');
        $this->actingAs($user)->get('/admin/dashboard')->assertRedirect('/admin/seguranca');
        $this->get('/admin/seguranca')->assertOk();
        $user->forceFill(['email_verified_at' => null])->save();
        $this->get('/admin/dashboard')->assertRedirect('/email/verify');
        $user->forceFill(['email_verified_at' => now(), 'active' => false])->save();
        $this->get('/admin/dashboard')->assertForbidden();
    }

    public function test_grants_do_not_borrow_permissions_from_another_segment_or_branch(): void
    {
        $user = User::factory()->create(['active' => true]);
        $pharmacy = Segment::where('slug', 'farmacia')->firstOrFail();
        $commercial = Segment::where('slug', 'comercial')->firstOrFail();
        $branches = Branch::all();
        $this->grant($user, 'stock-manager', $pharmacy->id, $branches[0]->id);
        $this->grant($user, 'employee', $commercial->id);
        $access = app(Access::class);
        $this->assertTrue($access->allows($user, 'stock.update', $pharmacy->id, $branches[0]->id));
        $this->assertFalse($access->allows($user, 'stock.update', $pharmacy->id, $branches[1]->id));
        $this->assertFalse($access->allows($user, 'stock.update', $commercial->id, $branches[0]->id));
        $this->actingAs($user)->get('/admin/configuracoes')->assertForbidden();
        $this->get('/admin/utilizadores')->assertForbidden();
        $this->get('/admin/logs')->assertForbidden();
        $this->post('/admin/perfis', ['name' => 'elevated', 'permissions' => ['users.assign_roles']])->assertForbidden();
    }

    public function test_segment_manager_can_open_create_form_but_cannot_change_segment_or_publish_global_content(): void
    {
        $user = User::factory()->create(['active' => true]);
        $segment = Segment::where('slug', 'farmacia')->firstOrFail();
        $this->grant($user, 'manager', $segment->id);
        $this->actingAs($user)->get('/admin/produtos/criar')->assertOk();
        $this->get('/admin/paginas/criar')->assertForbidden();
        $other = Segment::where('slug', 'comercial')->firstOrFail();
        $this->post('/admin/categorias', ['segment_id' => $other->id, 'name' => 'Negado', 'slug' => 'negado', 'sort_order' => 0, 'active' => true])->assertForbidden();
    }

    public function test_last_super_admin_cannot_be_revoked_or_disabled(): void
    {
        $user = User::factory()->create(['active' => true, 'two_factor_confirmed_at' => now()]);
        $user->assignRole('super-admin');
        $this->actingAs($user)->deleteJson('/admin/acessos/'.$user->id, ['role_id' => Role::findByName('super-admin')->id])->assertUnprocessable();
        $this->patchJson('/admin/utilizadores/'.$user->id, ['name' => $user->name, 'email' => $user->email, 'active' => false])->assertUnprocessable();
        $this->assertTrue($user->fresh()->active);
    }

    public function test_login_works_and_invalid_password_is_rejected(): void
    {
        $user = User::factory()->create(['active' => true, 'password' => 'a-secure-password-123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors();
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'a-secure-password-123'])->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
