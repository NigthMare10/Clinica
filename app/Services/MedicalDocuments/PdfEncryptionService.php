<?php

namespace App\Services\MedicalDocuments;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class PdfEncryptionService
{
    public function __construct(private PdfToolAvailabilityService $tools) {}

    public function decrypt(string $input, string $output): void
    {
        $qpdf = $this->tools->path('qpdf');
        if ($qpdf) {
            $this->run($this->process([$qpdf, '--decrypt', '--object-streams=disable', $input, $output]), 'PDF decryption failed.');

            return;
        }

        $gs = $this->tools->path('gs') ?? throw new RuntimeException('qpdf y Ghostscript no están disponibles.');
        $this->run($this->process([
            $gs, '-q', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=pdfwrite',
            '-sPDFPassword=', '-sOutputFile='.$output, $input,
        ]), 'PDF decryption failed.');
    }

    public function encrypt(string $input, string $output): void
    {
        $qpdf = $this->tools->path('qpdf');
        if (! $qpdf) {
            $this->encryptWithGhostscript($input, $output);

            return;
        }
        $this->withArgumentFile($input, $output, function (string $argumentFile) use ($qpdf): void {
            $process = $this->process([$qpdf, '@'.$argumentFile]);
            $this->run($process, 'PDF encryption failed.');
        });
    }

    public function assertEncrypted(string $path): void
    {
        $qpdf = $this->tools->path('qpdf');
        if (! $qpdf) {
            if (! str_contains((string) file_get_contents($path), '/Encrypt')) {
                throw new RuntimeException('El PDF final no quedó cifrado.');
            }

            return;
        }
        $process = $this->process([$qpdf, '--is-encrypted', $path]);
        $process->setTimeout(config('medical_documents.process_timeout'))->disableOutput()->run();
        if ($process->getExitCode() !== 0) {
            throw new RuntimeException('El PDF final no quedó cifrado.');
        }
    }

    private function run(Process $process, string $message): void
    {
        $process->setTimeout(config('medical_documents.process_timeout'))->run();
        if (! $process->isSuccessful() && str_contains($process->getErrorOutput(), 'entropy source strength too weak')) {
            usleep(200_000);
            $process->restart();
        }
        if (! $process->isSuccessful()) {
            Log::error('PDF security process failed.', ['exit_code' => $process->getExitCode(), 'error' => mb_substr($process->getErrorOutput(), 0, 1000)]);
            throw new RuntimeException($message);
        }
    }

    protected function process(array $command): Process
    {
        $runtime = storage_path('runtime/process');
        if (! is_dir($runtime)) {
            mkdir($runtime, 0700, true);
        }

        return new Process($command, $runtime, array_merge(getenv(), [
            'QPDF_CRYPTO_PROVIDER' => 'native',
            'TMPDIR' => storage_path('runtime/tmp'),
        ]));
    }

    private function encryptWithGhostscript(string $input, string $output): void
    {
        $gs = $this->tools->path('gs') ?? throw new RuntimeException('qpdf y Ghostscript no están disponibles.');
        $password = $this->ownerPassword();
        if ($password === '') {
            throw new RuntimeException('PDF password is not configured.');
        }
        $this->run($this->process([
            $gs,
            '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.7', '-dEncryptionR=3', '-dKeyLength=128', '-dPermissions=4',
            '-sUserPassword=', '-sOwnerPassword='.$password,
            '-sOutputFile='.$output, $input,
        ]), 'PDF encryption failed.');
    }

    private function withArgumentFile(string $input, string $output, callable $callback): void
    {
        $password = $this->ownerPassword();
        if ($password === '') {
            throw new RuntimeException('PDF password is not configured.');
        }

        $path = tempnam(sys_get_temp_dir(), 'csa-qpdf-args-');
        $arguments = implode("\n", [
            '--encrypt',
            // The recipient may open and print; the configured secret only protects owner permissions.
            '--user-password=',
            '--owner-password='.$password,
            '--bits=256',
            '--modify=none',
            '--extract=n',
            '--annotate=n',
            '--form=n',
            '--assemble=n',
            '--print=full',
            '--',
            $input,
            $output,
        ]);
        if ($path === false || file_put_contents($path, $arguments, LOCK_EX) === false) {
            throw new RuntimeException('Unable to prepare protected PDF processing.');
        }
        @chmod($path, 0600);

        try {
            $callback($path);
        } finally {
            if (is_file($path)) {
                file_put_contents($path, str_repeat("\0", max(1, filesize($path) ?: 1)), LOCK_EX);
                @unlink($path);
            }
        }
    }

    private function ownerPassword(): string
    {
        return (string) config('medical_documents.pdf_password');
    }
}
