<?php

namespace Tests\Feature;

use App\Enums\FiscalAuthorizationStatus;
use App\Models\Clinic;
use App\Models\FiscalAuthorization;
use App\Services\Fiscal\ImportReferenceInvoiceAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceInvoiceFiscalImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_values_are_normalized_and_render_without_duplicated_prefixes(): void
    {
        $reference = config('fiscal_reference.reference_invoice_import');

        $this->assertSame('08019995307719', $reference['rtn']);
        $this->assertSame('3A2E5B-5C6C48-E738E0-63BE03-0909F2-F8', $reference['cai']);
        $this->assertSame('FACTURA_CONTADO', $reference['document_type']);
        $this->assertSame('008', $reference['establishment']);
        $this->assertSame('001', $reference['point_of_issue']);
        $this->assertSame('01', $reference['ncf_type']);
        $this->assertSame('008-001-01-', $reference['ncf_prefix']);
        $this->assertSame(134099, $reference['sequence_start']);
        $this->assertSame(342000, $reference['sequence_end']);
        $this->assertSame('008-001-01-00134099', $reference['full_range_start']);
        $this->assertSame('008-001-01-00342000', $reference['full_range_end']);
        $this->assertSame('2027-05-09', $reference['valid_until']);
        $this->assertSame('REFERENCE_INVOICE_IMPORT', $reference['source']);

        $authorization = new FiscalAuthorization($reference + [
            'range_start' => $reference['sequence_start'],
            'range_end' => $reference['sequence_end'],
        ]);

        $this->assertSame('008-001-01-00134099', $authorization->rangeStartNcf());
        $this->assertSame('008-001-01-00342000', $authorization->rangeEndNcf());
        $this->assertSame('008-001-01-00134100', $authorization->formatNcf(134100));
    }

    public function test_artisan_import_is_idempotent_and_preserves_consumed_sequence_state(): void
    {
        $clinic = Clinic::create(['code' => 'HN-08', 'slug' => 'reference-clinic', 'name' => 'Reference clinic', 'department' => 'Reference']);

        $this->artisan('fiscal:import-reference-invoice')->assertSuccessful();
        $authorization = FiscalAuthorization::sole();
        $authorization->forceFill(['next_number' => 134105, 'status' => FiscalAuthorizationStatus::DISABLED, 'is_active' => false])->save();

        $this->artisan('fiscal:import-reference-invoice')->assertSuccessful();

        $this->assertSame(1, FiscalAuthorization::count());
        $authorization->refresh();
        $this->assertSame($clinic->id, $authorization->clinic_id);
        $this->assertSame(134105, $authorization->next_number);
        $this->assertSame(FiscalAuthorizationStatus::DISABLED, $authorization->status);
        $this->assertFalse($authorization->is_active);
        $this->assertSame('008-001-01-00134099', $authorization->full_range_start);
        $this->assertSame('008-001-01-00342000', $authorization->full_range_end);
        $this->assertSame('REFERENCE_INVOICE_IMPORT', $authorization->source);
        $this->assertSame('01', $authorization->ncf_type);
        $this->assertSame('2027-05-09', $authorization->valid_until->toDateString());
    }

    public function test_import_upgrades_a_matching_legacy_row_instead_of_duplicating_it(): void
    {
        $clinic = Clinic::create(['code' => 'HN-08', 'slug' => 'legacy-clinic', 'name' => 'Legacy clinic', 'department' => 'Legacy']);
        $legacy = FiscalAuthorization::create([
            'clinic_id' => $clinic->id,
            'cai' => config('fiscal_reference.reference_invoice_import.cai'),
            'rtn' => config('fiscal_reference.reference_invoice_import.rtn'),
            'establishment' => '008',
            'point_of_issue' => '001',
            'document_type' => 'FACTURA_CONTADO',
            'ncf_prefix' => '008-001-01-',
            'range_start' => 134099,
            'range_end' => 342000,
            'next_number' => 134120,
            'number_padding' => 8,
            'valid_from' => today(),
            'valid_until' => '2027-05-09',
        ]);

        $imported = app(ImportReferenceInvoiceAuthorization::class)->import();

        $this->assertSame($legacy->id, $imported->id);
        $this->assertSame(1, FiscalAuthorization::count());
        $this->assertSame(134120, $imported->next_number);
        $this->assertSame('008-001-01-00134099', $imported->full_range_start);
    }
}
