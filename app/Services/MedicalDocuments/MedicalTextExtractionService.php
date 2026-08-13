<?php

namespace App\Services\MedicalDocuments;

use Carbon\CarbonImmutable;

class MedicalTextExtractionService
{
    private const MONTHS = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
        'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
        'noviembre' => 11, 'diciembre' => 12,
    ];

    private const NUMBERS = [
        'un' => 1, 'uno' => 1, 'una' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5,
        'seis' => 6, 'siete' => 7, 'ocho' => 8, 'nueve' => 9, 'diez' => 10, 'once' => 11,
        'doce' => 12, 'trece' => 13, 'catorce' => 14, 'quince' => 15,
    ];

    public function extract(string $source, string $kind = 'constancia'): array
    {
        $text = $this->normalize($source);
        $fields = [
            'patient_name' => $this->match($text, [
                '/(?:se\s+hace\s+constar\s+que\s+)?(?:el|la)?\s*paciente\s+([\p{L}][\p{L}\p{N}\s]+?)(?=,\s*(?:de\s+)?\d{1,3}\s+años|,\s*(?:con\s+)?(?:número|numero|DNI|identidad))/iu',
                '/se\s+hace\s+constar\s+que\s+([\p{L}][\p{L}\p{N}\s]+?)(?=,\s*(?:de\s+)?\d{1,3}\s+años|,\s*(?:con\s+)?(?:número|numero|DNI|identidad))/iu',
                '/\b([\p{L}][\p{L}\s]+?)\s+(?=se\s+present[oó]|acudi[oó]|fue\s+atendid[oa])/',
            ]),
            'age' => $this->match($text, ['/(?:de\s+)?(\d{1,3})\s+años(?:\s+de\s+edad)?/iu']),
            'identity' => $this->match($text, ['/(?:número|numero)\s+de\s+identidad\s*(?:n[.ºo°]+\s*)?[:#-]?\s*([0-9][0-9\s-]{7,20})/iu', '/\b(?:identidad|DNI)\s*(?:n[.ºo°]+\s*)?[:#-]?\s*([0-9][0-9\s-]{7,20})/iu']),
            'consultation_date' => $this->extractConsultationDate($text),
            'consultation_time' => $this->extractTime($text),
            'symptoms' => $this->extractClause($text, ['caracterizado por', 'por presentar'], ['Durante la valoración', 'Durante la valoracion', 'Durante el examen', 'Durante la evaluación']),
            'diagnosis' => $this->extractDiagnosis($text),
            'recommendations' => $this->extractRecommendations($text),
            'leave_days' => $this->extractDays($text),
            'leave_start_date' => null,
            'leave_end_date' => null,
            'body' => $this->cleanPresentation($source),
        ];
        $fields['identity'] = $fields['identity'] ? preg_replace('/\D+/', '', $fields['identity']) : null;
        $fields['age'] = $fields['age'] !== null ? (int) $fields['age'] : null;
        [$fields['leave_start_date'], $fields['leave_end_date']] = $this->extractPeriod($text, $fields['consultation_date']);

        $required = ['patient_name', 'identity', 'consultation_date', 'diagnosis'];
        if ($kind === 'incapacidad') {
            array_push($required, 'leave_days', 'leave_start_date', 'leave_end_date');
        }
        $checks = [];
        foreach ($required as $field) {
            $checks[$field] = ! empty($fields[$field]);
        }
        $conflicts = $this->validate($fields, $kind, $text);
        $detected = count(array_filter($checks));

        return [
            'fields' => $fields,
            'checks' => $checks,
            'score' => count($checks) > 0 ? round($detected / count($checks), 2) : 0,
            'conflicts' => $conflicts,
            'requires_review' => in_array(false, $checks, true) || $conflicts !== [],
        ];
    }

    public function cleanPresentation(string $text): string
    {
        $text = preg_replace('/\*\*(.*?)\*\*/su', '$1', $text);
        $text = preg_replace('/__(.*?)__/su', '$1', (string) $text);
        $text = preg_replace('/^\s{0,3}#{1,6}\s*/mu', '', (string) $text);

        return trim((string) preg_replace("/\r\n?|\x{00A0}/u", "\n", (string) $text));
    }

    private function normalize(string $text): string
    {
        $text = $this->cleanPresentation($text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\n{3,}/u', "\n\n", (string) $text);

        return trim((string) $text);
    }

    private function match(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim((string) preg_replace('/\s+/u', ' ', $matches[1]));
            }
        }

        return null;
    }

    private function extractConsultationDate(string $text): ?string
    {
        $value = $this->match($text, [
            '/'.$this->consultationPrefix().'\s+(\d{1,2}\s+de\s+[\p{L}]+\s+de\s+\d{4})/iu',
            '/'.$this->consultationPrefix().'\s+(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{4})/iu',
        ]);

        return $this->date($value);
    }

    private function extractTime(string $text): ?string
    {
        if (! preg_match('/\ba\s+las\s+(\d{1,2}):(\d{2})\s*(a\.?\s*m\.?|p\.?\s*m\.?|AM|PM)?/iu', $text, $parts)) {
            return null;
        }
        $hour = (int) $parts[1];
        $minutes = (int) $parts[2];
        if ($hour > 23 || $minutes > 59) {
            return null;
        }
        $period = strtolower((string) ($parts[3] ?? ''));
        if ($period !== '' && ($hour < 1 || $hour > 12)) {
            return null;
        }
        if (str_contains($period, 'p') && $hour < 12) {
            $hour += 12;
        } elseif (str_contains($period, 'a') && $hour === 12) {
            $hour = 0;
        }

        return sprintf('%02d:%02d', $hour, $minutes);
    }

    private function extractClause(string $text, array $starts, array $ends): ?string
    {
        foreach ($starts as $start) {
            $position = mb_stripos($text, $start);
            if ($position === false) {
                continue;
            }
            $value = mb_substr($text, $position + mb_strlen($start));
            $limit = mb_strlen($value);
            foreach ($ends as $end) {
                $endPosition = mb_stripos($value, $end);
                if ($endPosition !== false) {
                    $limit = min($limit, $endPosition);
                }
            }

            return $this->cleanClause(mb_substr($value, 0, $limit));
        }

        return null;
    }

    private function extractDiagnosis(string $text): ?string
    {
        return $this->match($text, [
            '/(?:se\s+establece\s+diagn[oó]stico(?:\s+presuntivo)?\s+de|se\s+diagnostic[oó](?:\s+con)?|diagn[oó]stico\s+presuntivo\s+de)\s+(.+?)(?=,?\s*recomend[aá]ndose|\.(?:\s|$)|\n\n|$)/isu',
            '/diagn[oó]stico\s*[:\-]\s*(.+?)(?=\.(?:\s|$)|\n\n|$)/isu',
            '/compatibles?\s+con\s+(.+?)(?=,?\s*(?:limitando|recomend[aá]ndose)|\.|\n\n|$)/isu',
        ]);
    }

    private function extractRecommendations(string $text): ?string
    {
        $value = $this->match($text, ['/(?:recomend[aá]ndose|se\s+recomienda)\s+(.+?)(?=\.\s*(?:Por\s+lo\s+anterior|Se\s+extiende)|\n\n|$)/isu']);

        return $value ? $this->cleanClause($value) : null;
    }

    private function extractDays(string $text): ?int
    {
        if (! preg_match('/(?:se\s+extiende\s+incapacidad(?:\s+m[eé]dica)?\s+por|incapacidad\s+m[eé]dica\s+por|se\s+otorgan|reposo\s+por)\s+([\p{L}]+|\d+)\s*(?:\((\d+)\))?\s+d[ií]a(?:s)?/iu', $text, $matches)) {
            return null;
        }
        if (! empty($matches[2])) {
            return (int) $matches[2];
        }
        $value = mb_strtolower($matches[1]);

        return ctype_digit($value) ? (int) $value : (self::NUMBERS[$value] ?? null);
    }

    private function extractPeriod(string $text, ?string $consultationDate): array
    {
        $year = $consultationDate ? (int) substr($consultationDate, 0, 4) : (int) now(config('institution.timezone'))->year;
        if (preg_match('/correspondiente\s+al\s+(\d{1,2})\s+de\s+([\p{L}]+)(?:\s+de\s+(\d{4}))?/iu', $text, $parts)) {
            $year = ! empty($parts[3]) ? (int) $parts[3] : $year;
            $date = $this->fromParts((int) $parts[1], $parts[2], $year);

            return [$date, $date];
        }
        if (preg_match('/(?:correspondientes\s+al|del)\s+(\d{1,2})\s+(?:y|al)\s+(\d{1,2})\s+de\s+([\p{L}]+)(?:\s+de\s+(\d{4}))?/iu', $text, $parts)) {
            $year = ! empty($parts[4]) ? (int) $parts[4] : $year;

            return [$this->fromParts((int) $parts[1], $parts[3], $year), $this->fromParts((int) $parts[2], $parts[3], $year)];
        }
        if (preg_match('/(?:correspondientes\s+al\s+)?(\d{1,2}(?:\s*,\s*\d{1,2})+\s+y\s+\d{1,2})\s+de\s+([\p{L}]+)(?:\s+de\s+(\d{4}))?/iu', $text, $parts)) {
            preg_match_all('/\d{1,2}/', $parts[1], $days);
            $year = ! empty($parts[3]) ? (int) $parts[3] : $year;

            return [$this->fromParts((int) $days[0][0], $parts[2], $year), $this->fromParts((int) end($days[0]), $parts[2], $year)];
        }

        return [null, null];
    }

    private function validate(array $fields, string $kind, string $text): array
    {
        $conflicts = [];
        $consultationDates = $this->consultationDates($text);
        if (count($consultationDates) > 1) {
            $conflicts[] = ['field' => 'consultation_date', 'code' => 'consultation_date_conflict', 'message' => 'El texto contiene fechas de consulta diferentes.', 'blocking' => true];
        }
        if ($kind !== 'incapacidad' || ! $fields['leave_start_date'] || ! $fields['leave_end_date']) {
            return $conflicts;
        }
        $start = CarbonImmutable::parse($fields['leave_start_date']);
        $end = CarbonImmutable::parse($fields['leave_end_date']);
        if ($start->greaterThan($end)) {
            $conflicts[] = ['field' => 'leave_end_date', 'code' => 'start_after_end', 'message' => 'La fecha inicial no puede ser posterior a la fecha final.', 'blocking' => true];
        } elseif ($fields['leave_days'] && ($calculated = (int) $start->diffInDays($end) + 1) !== $fields['leave_days']) {
            $conflicts[] = ['field' => 'leave_days', 'code' => 'days_mismatch', 'message' => "El periodo corresponde a {$calculated} días inclusivos.", 'blocking' => true];
        }

        return $conflicts;
    }

    private function consultationDates(string $text): array
    {
        preg_match_all('/'.$this->consultationPrefix().'\s+(\d{1,2}\s+de\s+[\p{L}]+\s+de\s+\d{4}|\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{4})/iu', $text, $matches);

        return array_values(array_unique(array_filter(array_map(fn (string $value) => $this->date($value), $matches[1]))));
    }

    private function consultationPrefix(): string
    {
        return '(?:acudi[oó]\s+(?:para\s+)?(?:valoraci[oó]n\s+m[eé]dica|a\s+consulta(?:\s+m[eé]dica)?)\s+el(?:\s+d[ií]a)?|se\s+present[oó]\s+a\s+consulta(?:\s+m[eé]dica)?\s+el(?:\s+d[ií]a)?|fue\s+atendid[oa](?:\s+en\s+consulta(?:\s+m[eé]dica)?)?\s+el|consulta\s+realizada\s+el)';
    }

    private function date(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        $normalized = mb_strtolower(trim($value));
        if (preg_match('/^(\d{1,2})\s+de\s+([\p{L}]+)\s+de\s+(\d{4})$/u', $normalized, $parts)) {
            return $this->fromParts((int) $parts[1], $parts[2], (int) $parts[3]);
        }
        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $normalized, $parts)) {
            return CarbonImmutable::createSafe((int) $parts[3], (int) $parts[2], (int) $parts[1])?->format('Y-m-d');
        }

        return null;
    }

    private function fromParts(int $day, string $month, int $year): ?string
    {
        $monthNumber = self::MONTHS[mb_strtolower($month)] ?? null;
        if (! $monthNumber) {
            return null;
        }

        return CarbonImmutable::createSafe($year, $monthNumber, $day)?->format('Y-m-d');
    }

    private function cleanClause(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value), " \t\n\r\0\x0B,.;");
    }
}
