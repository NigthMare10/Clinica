<?php

namespace App\Services\MedicalDocuments;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PdfTemplate;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class PdfTemplateRenderService
{
    public function render(string $kind, Patient $patient, Doctor $doctor, Clinic $clinic, array $fields, string $output, ?PdfTemplate $template = null): void
    {
        $pdf = new Fpdi('P', 'mm', 'Letter');
        $pdf->SetAutoPageBreak(false);
        $this->addPage($pdf, $clinic);

        $patientName = trim($patient->first_name.' '.$patient->last_name);
        $age = $fields['age_at_consultation'] ?? $patient->age;
        $date = $this->date($fields['consultation_date']);
        $time = $this->time($fields['consultation_time'] ?? null);
        $this->patientBand($pdf, $patientName, $age, $date, $time);

        $pdf->SetY(73);
        $pdf->SetTextColor(10, 45, 76);
        $pdf->SetFont('Helvetica', 'B', 17);
        $pdf->Cell(0, 9, $this->text($kind === 'incapacidad' ? 'Incapacidad Médica' : 'Constancia Médica'), 0, 1, 'C');
        $pdf->Ln(4);

        $body = trim((string) ($fields['free_text'] ?? ''));
        if ($body === '') {
            $body = trim((string) ($fields['medical_reason'] ?? ''));
        }
        if ($this->wrappedLineCount($pdf, $body, 170) > 19) {
            throw new RuntimeException('Contenido demasiado extenso para formato de una página.');
        }
        $this->narrative($pdf, $body);
        $this->signatureArea($pdf);
        $pdf->Output('F', $output);
    }

    private function addPage(Fpdi $pdf, Clinic $clinic): void
    {
        $pdf->AddPage();
        $pdf->SetMargins(23, 12, 23);
        $pdf->SetDrawColor(12, 105, 169);
        $pdf->SetFillColor(242, 248, 252);

        $headerLogo = resource_path('pdf/caduceus-header.png');
        $watermark = resource_path('pdf/caduceus-watermark.png');
        if (! is_file($headerLogo) || ! is_file($watermark)) {
            throw new RuntimeException('El logo médico institucional no está disponible.');
        }
        $pdf->Image($watermark, 77, 88, 62);
        $pdf->Image($headerLogo, 24, 12, 13.5);
        $pdf->SetTextColor(11, 101, 164);
        $pdf->SetXY(43, 12);
        $pdf->SetFont('Helvetica', 'B', 16.5);
        $pdf->Cell(0, 7, $this->text(mb_strtoupper(config('institution.name'))), 0, 1);
        $pdf->SetX(43);
        $pdf->SetTextColor(10, 45, 76);
        $pdf->SetFont('Helvetica', 'B', 8.7);
        $pdf->Cell(0, 4.5, $this->text(config('institution.provider.name')), 0, 1);
        $pdf->SetX(43);
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->Cell(0, 4, $this->text(config('institution.provider.credential_type').': '.config('institution.provider.credential_number')), 0, 1);
        $pdf->SetX(43);
        $pdf->SetFont('Helvetica', '', 7.2);
        $address = implode(', ', array_map(fn (string $line) => rtrim(trim($line), ','), explode("\n", $clinic->address ?: config('institution.address'))));
        $pdf->MultiCell(145, 3.4, $this->text($address), 0, 'L');
        $pdf->SetX(43);
        $pdf->SetFont('Helvetica', '', 7.4);
        $pdf->Cell(82, 3.7, $this->text('Tel: '.config('institution.phone')), 0, 0);
        $pdf->SetFont('Helvetica', 'B', 7.8);
        $pdf->SetTextColor(11, 101, 164);
        $pdf->Cell(63, 3.7, $this->text('Atención 24/7'), 0, 1, 'R');

        $y = 44;
        $pdf->SetDrawColor(11, 101, 164);
        $pdf->SetLineWidth(0.8);
        $pdf->Line(23, $y, 193, $y);
        $pdf->SetLineWidth(0.25);
        $pdf->Line(23, $y + 2.2, 193, $y + 2.2);
        $pdf->SetY(50);
    }

    private function patientBand(Fpdi $pdf, string $name, mixed $age, string $date, string $time): void
    {
        $y = $pdf->GetY();
        $pdf->SetFillColor(247, 251, 254);
        $pdf->SetDrawColor(11, 101, 164);
        $pdf->Rect(23, $y, 170, 17, 'DF');
        $pdf->SetXY(28, $y + 2.5);
        $pdf->SetTextColor(11, 101, 164);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->Cell(70, 3.5, 'PACIENTE:', 0, 0);
        $pdf->Cell(26, 3.5, 'EDAD:', 0, 0);
        $pdf->Cell(44, 3.5, 'FECHA DE CONSULTA:', 0, 0);
        $pdf->Cell(20, 3.5, 'HORA:', 0, 1);
        $pdf->SetX(28);
        $pdf->SetTextColor(10, 45, 76);
        $pdf->SetFont('Helvetica', 'B', 8.7);
        $pdf->Cell(70, 5.5, $this->text($name), 0, 0);
        $pdf->Cell(26, 5.5, $this->text($age !== null ? $age.' AÑOS' : 'NO INDICADA'), 0, 0);
        $pdf->Cell(44, 5.5, $this->text($date), 0, 0);
        $pdf->Cell(20, 5.5, $this->text($time), 0, 1);
        $pdf->SetY($y + 17);
    }

    private function narrative(Fpdi $pdf, string $body): void
    {
        $body = preg_replace('/\*\*(.*?)\*\*/su', '$1', $body);
        $body = preg_replace('/__(.*?)__/su', '$1', (string) $body);
        $body = preg_replace('/^\s{0,3}#{1,6}\s*/mu', '', (string) $body);
        $body = trim((string) preg_replace("/\r\n?/", "\n", (string) $body));
        $paragraphs = preg_split('/\n\s*\n/u', $body) ?: [];

        $pdf->SetTextColor(28, 45, 57);
        $pdf->SetFont('Helvetica', '', 10.2);
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) preg_replace('/[ \t]*\n[ \t]*/u', ' ', $paragraph));
            if ($paragraph === '') {
                continue;
            }
            $pdf->MultiCell(0, 5.2, $this->text($paragraph), 0, 'J');
            $pdf->Ln(2.2);
        }
        if ($pdf->GetY() > 190) {
            throw new RuntimeException('Contenido demasiado extenso para formato de una página.');
        }
    }

    private function signatureArea(Fpdi $pdf): void
    {
        // Keep short narratives compact while reserving a safe separation after longer text.
        $pdf->SetY(max(164, min(194, $pdf->GetY() + 12)));

        $pdf->SetTextColor(28, 45, 57);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->Cell(0, 6, $this->text('Atentamente.'), 0, 1);
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(100, 5, $this->text(config('institution.provider.name')), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->Cell(100, 5, $this->text(config('institution.provider.credential_type').': '.config('institution.provider.credential_number')), 0, 1, 'C');
        $pdf->SetXY(28, 248);
        $pdf->SetDrawColor(10, 45, 76);
        $pdf->Line(38, 248, 118, 248);
        $pdf->SetXY(28, 244);
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->Cell(10, 4, '(F)');

        $pdf->SetXY(142, 256);
        $pdf->SetTextColor(10, 45, 76);
        $pdf->SetFont('Helvetica', '', 7.2);
        $pdf->MultiCell(48, 3.7, $this->text("Documento verificable digitalmente en\nClínica Médica Santa Ana"), 0, 'C');
    }

    private function wrappedLineCount(Fpdi $pdf, string $body, float $width): int
    {
        $body = trim((string) preg_replace('/\s+/u', ' ', preg_replace('/(?:\*\*|__|^\s{0,3}#{1,6}\s*)/mu', '', $body)));
        if ($body === '') {
            return 0;
        }
        $pdf->SetFont('Helvetica', '', 10.2);
        $lines = 1;
        $line = '';
        foreach (preg_split('/\s+/u', $this->text($body)) ?: [] as $word) {
            $candidate = $line === '' ? $word : "$line $word";
            if ($pdf->GetStringWidth($candidate) <= $width) {
                $line = $candidate;
            } else {
                $lines++;
                $line = $word;
            }
        }

        return $lines;
    }

    private function date(string $date): string
    {
        $parts = explode('-', $date);

        return count($parts) === 3 ? $parts[2].'/'.$parts[1].'/'.$parts[0] : $date;
    }

    private function time(?string $time): string
    {
        if (! $time) {
            return 'NO INDICADA';
        }

        [$hours, $minutes] = array_pad(explode(':', $time), 2, '00');
        $hour = (int) $hours;

        return ($hour % 12 ?: 12).':'.$minutes.' '.($hour < 12 ? 'a. m.' : 'p. m.');
    }

    private function text(string $value): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
    }
}
