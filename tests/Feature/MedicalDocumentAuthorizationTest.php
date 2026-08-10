<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalDocumentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_and_auditors_cannot_upload(): void
    {
        $this->get('/admin/documents')->assertRedirect('/login');
        $auditor = User::factory()->create(['role' => UserRole::AUDITOR]);
        $this->actingAs($auditor)->post('/admin/documents')->assertForbidden();
    }

    public function test_inactive_users_fail_role_middleware(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => false]);
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }
}
