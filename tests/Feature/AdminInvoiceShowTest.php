<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\InvoiceAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminInvoiceShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_eager_loads_and_renders_the_invoice_audit_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Audit User',
            'role' => UserRole::SUPER_ADMIN,
        ]);
        $clinic = Clinic::create([
            'code' => 'AUDIT',
            'slug' => 'invoice-audit-test',
            'name' => 'Invoice Audit Clinic',
            'department' => 'Test',
        ]);
        $invoice = Invoice::create([
            'clinic_id' => $clinic->id,
            'created_by' => $user->id,
            'recipient_name' => 'Test Recipient',
        ]);
        $audit = InvoiceAudit::create([
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'action' => 'CREATED',
        ]);

        $this->actingAs($user)->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Invoices/Show')
                ->where('invoice.audits.0.id', $audit->id)
                ->where('invoice.audits.0.user.id', $user->id)
                ->where('invoice.audits.0.user.name', 'Audit User'));
    }
}
