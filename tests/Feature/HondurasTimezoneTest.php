<?php

namespace Tests\Feature;

use Tests\TestCase;

class HondurasTimezoneTest extends TestCase
{
    public function test_application_and_institution_use_honduras_timezone(): void
    {
        $this->assertSame('America/Tegucigalpa', config('app.timezone'));
        $this->assertSame('America/Tegucigalpa', config('institution.timezone'));
        $this->assertSame('America/Tegucigalpa', now()->getTimezone()->getName());
    }
}
