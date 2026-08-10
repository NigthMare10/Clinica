<?php

namespace Tests\Feature;

use App\Services\MedicalDocuments\DocumentHashService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OriginalDocumentPreservationTest extends TestCase
{
    public function test_issued_writes_do_not_replace_original(): void
    {
        Storage::fake('local');
        $original = 'medical/original/id-random.pdf';
        $issued = 'medical/issued/id-random.pdf';
        Storage::disk('local')->put($original, "%PDF-1.4\noriginal");
        $hash = (new DocumentHashService)->file(Storage::disk('local')->path($original));
        Storage::disk('local')->put($issued, "%PDF-1.4\nissued");
        $this->assertTrue((new DocumentHashService)->equals(Storage::disk('local')->path($original), $hash));
        $this->assertNotSame(Storage::disk('local')->get($original), Storage::disk('local')->get($issued));
    }
}
