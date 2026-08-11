<?php

namespace Tests\Unit;

use App\Services\MedicalDocuments\PdfEncryptionService;
use App\Services\MedicalDocuments\PdfToolAvailabilityService;
use Mockery;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PdfEncryptionServiceTest extends TestCase
{
    public function test_decryption_normalizes_object_streams_without_an_open_password(): void
    {
        config(['medical_documents.process_timeout' => 1]);
        $tools = Mockery::mock(PdfToolAvailabilityService::class);
        $tools->shouldReceive('path')->with('qpdf')->andReturn('qpdf');
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('setTimeout')->andReturnSelf();
        $process->shouldReceive('run')->once();
        $process->shouldReceive('isSuccessful')->andReturnTrue();
        $service = new class($tools, $process) extends PdfEncryptionService
        {
            public array $command = [];

            public function __construct(PdfToolAvailabilityService $tools, private Process $fake)
            {
                parent::__construct($tools);
            }

            protected function process(array $command): Process
            {
                $this->command = $command;

                return $this->fake;
            }
        };

        $service->decrypt('input.pdf', 'output.pdf');

        $this->assertContains('--object-streams=disable', $service->command);
        $this->assertNotContains('--password-file', $service->command);
    }

    public function test_encryption_password_never_appears_in_process_arguments_or_error(): void
    {
        config(['medical_documents.password' => 'top-secret-password', 'medical_documents.process_timeout' => 1]);
        $tools = Mockery::mock(PdfToolAvailabilityService::class);
        $tools->shouldReceive('path')->with('qpdf')->andReturn('qpdf');
        $process = Mockery::mock(Process::class);
        $process->shouldReceive('setTimeout')->andReturnSelf();
        $process->shouldReceive('run')->once();
        $process->shouldReceive('isSuccessful')->andReturnFalse();
        $process->shouldReceive('getExitCode')->andReturn(2);
        $process->shouldReceive('getErrorOutput')->andReturn('Controlled qpdf failure.');
        $service = new class($tools, $process) extends PdfEncryptionService
        {
            public array $command = [];

            public function __construct(PdfToolAvailabilityService $tools, private Process $fake)
            {
                parent::__construct($tools);
            }

            protected function process(array $command): Process
            {
                $this->command = $command;

                return $this->fake;
            }
        };

        try {
            $service->encrypt('input.pdf', 'output.pdf');
            $this->fail('A failed qpdf process was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString('top-secret-password', implode(' ', $service->command));
            $this->assertStringNotContainsString('top-secret-password', $exception->getMessage());
            $this->assertStringStartsWith('@', $service->command[1]);
            $this->assertFileDoesNotExist(substr($service->command[1], 1));
        }
    }
}
