<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\BillingService;
use App\Models\Clinic;
use App\Models\FiscalAuthorization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FiscalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_service_and_profile_upsert_is_idempotent(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $clinic = $this->clinic('HN-F1');
        $payload = [
            'clinic_id' => $clinic->id,
            'kind' => 'CONSTANCIA',
            'service_code' => 'CONST-001',
            'service_name' => 'Constancia médica',
            'price' => 450,
            'tax_category' => 'EXENTO',
            'quantity' => 1,
            'default_payment_method' => 'EFECTIVO',
        ];

        $this->actingAs($user)->putJson(route('admin.fiscal-authorizations.billing-profile.upsert'), $payload)->assertOk();
        $this->actingAs($user)->putJson(route('admin.fiscal-authorizations.billing-profile.upsert'), array_merge($payload, ['price' => 500]))->assertOk();

        $this->assertSame(1, BillingService::count());
        $this->assertSame(1, BillingProfile::count());
        $this->assertSame('500.00', BillingService::sole()->default_price);
        $this->assertSame('500.00', BillingProfile::sole()->price_override);
    }

    public function test_profile_upsert_validates_kind_and_enforces_clinic_access(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRATOR]);
        $allowed = $this->clinic('HN-F2');
        $restricted = $this->clinic('HN-F3');
        $user->clinics()->attach($allowed, ['role' => UserRole::ADMINISTRATOR->value, 'is_active' => true]);
        $payload = [
            'clinic_id' => $restricted->id,
            'kind' => 'RECETA',
            'service_code' => 'INC-001',
            'service_name' => 'Incapacidad médica',
            'price' => 0,
            'tax_category' => 'EXENTO',
            'quantity' => 1,
            'default_payment_method' => 'EFECTIVO',
        ];

        $this->actingAs($user)->putJson(route('admin.fiscal-authorizations.billing-profile.upsert'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kind');

        $this->actingAs($user)->putJson(route('admin.fiscal-authorizations.billing-profile.upsert'), array_merge($payload, ['kind' => 'INCAPACIDAD']))
            ->assertForbidden();
        $this->assertDatabaseCount('billing_profiles', 0);
    }

    public function test_fiscal_page_exposes_exact_reference_range_and_consumption_metrics(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $clinic = $this->clinic('HN-F4');
        FiscalAuthorization::create([
            'clinic_id' => $clinic->id,
            'cai' => 'REFERENCE-CAI',
            'rtn' => '08019995307719',
            'establishment' => '008',
            'point_of_issue' => '001',
            'document_type' => 'FACTURA_CONTADO',
            'ncf_prefix' => '008-001-01-',
            'range_start' => 134099,
            'range_end' => 342000,
            'full_range_start' => '008-001-01-00134099',
            'full_range_end' => '008-001-01-00342000',
            'source' => 'REFERENCE_INVOICE_IMPORT',
            'next_number' => 134100,
            'number_padding' => 8,
            'valid_from' => '2026-08-10',
            'valid_until' => '2027-05-09',
        ]);

        $this->actingAs($user)->get(route('admin.fiscal-authorizations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/FiscalSettings/Index')
                ->where('authorizations.0.full_range_start', '008-001-01-00134099')
                ->where('authorizations.0.full_range_end', '008-001-01-00342000')
                ->where('authorizations.0.next_ncf', '008-001-01-00134100')
                ->where('authorizations.0.available_count', 207901)
                ->where('authorizations.0.source', 'REFERENCE_INVOICE_IMPORT'));
    }

    private function clinic(string $code): Clinic
    {
        return Clinic::create(['code' => $code, 'slug' => strtolower($code), 'name' => $code, 'department' => $code]);
    }
}
