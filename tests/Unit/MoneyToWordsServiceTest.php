<?php

namespace Tests\Unit;

use App\Services\Fiscal\MoneyToWordsService;
use PHPUnit\Framework\TestCase;

class MoneyToWordsServiceTest extends TestCase
{
    public function test_it_converts_institutional_lempira_amounts(): void
    {
        $service = new MoneyToWordsService;
        $this->assertSame('QUINIENTOS CINCUENTA LEMPIRAS EXACTOS', $service->lempiras(550));
        $this->assertSame('MIL CIEN LEMPIRAS EXACTOS', $service->lempiras(1100));
        $this->assertSame('MIL QUINIENTOS LEMPIRAS CON 50/100', $service->lempiras(1500.50));
        $this->assertSame('DIEZ MIL LEMPIRAS EXACTOS', $service->lempiras(10000));
    }
}
