<?php

namespace App\Services\MedicalDocuments;

use Carbon\CarbonImmutable;

class MedicalDocumentConsistencyService
{
    public function check(array $candidates, ?array $confirmed = null, ?array $doctor = null): array
    {
        $grouped = [];
        foreach ($candidates as $candidate) {
            $item = $candidate instanceof FieldCandidate ? $candidate->toArray() : $candidate;
            $grouped[$item['field']][] = trim((string) $item['value']);
        }
        $issues = [];
        foreach (['issue_date', 'consultation_date', 'start_date', 'end_date', 'days', 'patient_name', 'doctor_name', 'doctor_credential', 'clinic_name'] as $field) {
            $values = array_values(array_unique(array_map(fn ($v) => $this->normalize($field, $v), $grouped[$field] ?? [])));
            if (count($values) > 1 && ! array_key_exists($field, $confirmed ?? [])) {
                $code = in_array($field, ['consultation_date', 'issue_date'], true) ? 'CONSULTATION_DATE_CONFLICT' : 'conflicting_candidates';
                $issues[] = $this->issue($field, $code, true, $values);
            }
        }
        $consultationDates = array_values(array_unique(array_map(fn ($value) => $this->normalize('consultation_date', $value),
            [...($grouped['consultation_date'] ?? []), ...($grouped['issue_date'] ?? [])])));
        if (count($consultationDates) > 1 && ! array_key_exists('consultation_date', $confirmed ?? []) && ! array_key_exists('issue_date', $confirmed ?? [])) {
            $issues[] = $this->issue('consultation_date', 'CONSULTATION_DATE_CONFLICT', true, $consultationDates);
        }
        $defaults = [];
        foreach ($grouped as $field => $items) {
            $defaults[$field] = count(array_unique(array_map(fn ($value) => $this->normalize($field, $value), $items))) === 1 ? $items[0] : null;
        }
        $values = array_replace($defaults, $confirmed ?? []);
        if (! empty($values['birth_date']) && isset($values['age'])) {
            if (($date = $this->date($values['issue_date'] ?? '')) && ($birth = $this->date($values['birth_date']))) {
                $calculated = $birth->diffInYears($date);
                if (abs($calculated - (int) $values['age']) > 1) {
                    $issues[] = $this->issue('age', 'birth_date_age_mismatch', true, [$calculated, $values['age']]);
                }
            } else {
                $issues[] = $this->issue('birth_date', 'invalid_date', true);
            }
        }
        if (! empty($values['start_date']) && ! empty($values['end_date']) && isset($values['days'])) {
            if (($start = $this->date($values['start_date'])) && ($end = $this->date($values['end_date'])) && $end->greaterThanOrEqualTo($start)) {
                $days = (int) $start->diffInDays($end) + 1;
                if ($days !== (int) $values['days']) {
                    $issues[] = $this->issue('days', 'date_range_days_mismatch', true, [$days, $values['days']]);
                }
            } else {
                $issues[] = $this->issue('date_range', 'invalid_date', true);
            }
        }
        if ($doctor && ! empty($values['doctor_credential']) && $this->identifier((string) ($doctor['credential_number'] ?? '')) !== $this->identifier((string) $values['doctor_credential'])) {
            $issues[] = $this->issue('doctor_credential', 'doctor_record_mismatch', true);
        }
        if ($doctor && ! empty($values['doctor_name'])) {
            $recordName = trim(($doctor['professional_name'] ?? '') ?: (($doctor['first_name'] ?? '').' '.($doctor['last_name'] ?? '')));
            if ($this->name($recordName) !== $this->name((string) $values['doctor_name'])) {
                $issues[] = $this->issue('doctor_name', 'doctor_name_record_mismatch', true);
            }
        }
        foreach (['patient_name', 'issue_date', 'doctor_name', 'doctor_credential'] as $required) {
            if (empty($values[$required])) {
                $issues[] = $this->issue($required, 'required_field_missing', true);
            }
        }

        return $issues;
    }

    public function hasBlockers(array $issues): bool
    {
        return collect($issues)->contains(fn ($i) => (bool) ($i['blocking'] ?? false));
    }

    private function issue(string $field, string $code, bool $blocking, array $values = []): array
    {
        return compact('field', 'code', 'blocking', 'values');
    }

    private function date(string $value): ?CarbonImmutable
    {
        $value = mb_strtolower(trim($value));
        $months = ['enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04', 'mayo' => '05', 'junio' => '06',
            'julio' => '07', 'agosto' => '08', 'septiembre' => '09', 'setiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12'];
        if (preg_match('/^(\d{1,2})\s+de\s+([a-záéíóú]+)\s+(?:de|del)\s+(\d{4})$/u', $value, $match) && isset($months[$match[2]])) {
            $value = sprintf('%s-%s-%02d', $match[3], $months[$match[2]], $match[1]);
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match)) {
            $value = $match[0];
        } elseif (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $value, $match)) {
            $value = sprintf('%s-%02d-%02d', $match[3], $match[2], $match[1]);
        } else {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

            return $date->format('Y-m-d') === $value ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalize(string $field, string $value): string
    {
        if (str_contains($field, 'date')) {
            return $this->date($value)?->format('Y-m-d') ?? 'invalid:'.mb_strtolower($value);
        }

        return in_array($field, ['doctor_name', 'patient_name'], true) ? $this->name($value) : $this->identifier($value);
    }

    private function identifier(string $value): string
    {
        return mb_strtolower((string) preg_replace('/[^\pL\pN]/u', '', $value));
    }

    private function name(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($value)) ?: mb_strtolower($value);
        $ascii = preg_replace('/\b(?:dr|dra|doctor|doctora)\b\.?/i', '', $ascii);

        return trim((string) preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/i', '', $ascii)));
    }
}
