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
        $qpdf = $this->tools->path('qpdf') ?? throw new RuntimeException('qpdf is unavailable.');
        $this->withPasswordFile(function (string $passwordFile) use ($qpdf, $input, $output): void {
            $process = $this->process([$qpdf, '--password-file='.$passwordFile, '--decrypt', '--object-streams=disable', $input, $output]);
            $this->run($process, 'PDF decryption failed.');
        });
    }

    public function encrypt(string $input, string $output): void
    {
        $qpdf = $this->tools->path('qpdf') ?? throw new RuntimeException('qpdf is unavailable.');
        $this->withArgumentFile($input, $output, function (string $argumentFile) use ($qpdf): void {
            $process = $this->process([$qpdf, '@'.$argumentFile]);
            $this->run($process, 'PDF encryption failed.');
        });
    }

    public function assertEncrypted(string $path): void
    {
        $qpdf = $this->tools->path('qpdf') ?? throw new RuntimeException('qpdf is unavailable.');
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
        return new Process($command, null, array_merge(getenv(), ['QPDF_CRYPTO_PROVIDER' => 'native']));
    }

    private function withPasswordFile(callable $callback): void
    {
        $password = (string) config('medical_documents.password');
        if ($password === '') {
            throw new RuntimeException('PDF password is not configured.');
        }

        $path = tempnam(sys_get_temp_dir(), 'csa-pdf-');
        if ($path === false || file_put_contents($path, $password) === false) {
            throw new RuntimeException('Unable to prepare protected PDF processing.');
        }
        @chmod($path, 0600);

        try {
            $callback($path);
        } finally {
            if (is_file($path)) {
                file_put_contents($path, str_repeat("\0", max(1, filesize($path) ?: 1)));
                @unlink($path);
            }
        }
    }

    private function withArgumentFile(string $input, string $output, callable $callback): void
    {
        $password = (string) config('medical_documents.password');
        if ($password === '') {
            throw new RuntimeException('PDF password is not configured.');
        }

        $path = tempnam(sys_get_temp_dir(), 'csa-qpdf-args-');
        $ownerPassword = hash_hmac('sha256', $password, (string) config('app.key'));
        $arguments = implode("\n", [
            '--encrypt',
            '--user-password='.$password,
            '--owner-password='.$ownerPassword,
            '--bits=256',
            '--modify=none',
            '--extract=n',
            '--annotate=n',
            '--form=n',
            '--assemble=n',
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
}
