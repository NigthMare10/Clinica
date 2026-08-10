<?php

namespace App\Services\MedicalDocuments;

class MedicalDocumentParser
{
    /** @return array<int, FieldCandidate> */
    public function parse(string $text): array
    {
        $patterns = [
            'patient_name' => '/(?:paciente|nombre(?:\s+del\s+paciente)?)\s*[:\-]?\s*([^\r\n]+)/iu',
            'patient_document' => '/(?:c[eé]dula|dni|documento|identificaci[oó]n)\s*(?:n(?:ro|[úu]mero)?\.?\s*)?[:\-]?\s*([A-Z0-9.-]{5,})/iu',
            'medical_record' => '/(?:historia\s+cl[ií]nica|expediente)\s*(?:n(?:ro|[úu]mero)?\.?)?\s*[:\-]?\s*([A-Z0-9.-]+)/iu',
            'birth_date' => '/(?:fecha\s+de\s+nacimiento|f\.\s*nacimiento)\s*[:\-]\s*(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4})/iu',
            'age' => '/(?:edad(?:\s+de)?)\s*[:\-]?\s*(\d{1,3})\s*(?:a[nñ]os)?/iu',
            'sex' => '/(?:sexo|g[eé]nero)\s*[:\-]\s*([\p{L}]+)/iu',
            'issue_date' => '/(?:fecha(?:\s+de\s+(?:emisi[oó]n|expedici[oó]n))?)\s*[:\-]\s*(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4})/iu',
            'consultation_date' => '/(?:FECHA\s*[:\-]\s*|acudi[oó]\s+a\s+consulta(?:\s+[^\r\n.]{0,100}?)?\s+el\s+d[ií]a\s+)(\d{1,2}(?:[\/.-]\d{1,2}[\/.-]\d{4}|\s+de\s+[a-záéíóú]+\s+(?:de|del)\s+\d{4}))/iu',
            'start_date' => '/(?:desde|inicio|fecha\s+inicial)\s*[:\-]\s*(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4})/iu',
            'end_date' => '/(?:hasta|fin|fecha\s+final)\s*[:\-]\s*(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4})/iu',
            'days' => '/(?:reposo|incapacidad|descanso)(?:\s+m[eé]dico)?\s*(?:por|de|:|-)?\s*(\d{1,3})\s*d[ií]as?/iu',
            'diagnosis' => '/(?:diagn[oó]stico|impresi[oó]n\s+diagn[oó]stica)(?:\s+(?:de|es))?\s*[:\-]?\s*([^\r\n.]+[.]?)/iu',
            'treatment' => '/(?:tratamiento|medicaci[oó]n)\s*[:\-]\s*([^\r\n]+)/iu',
            'recommendations' => '/(?:recomendaciones?|indicaciones?)\s*[:\-]\s*([^\r\n]+)/iu',
            'observations' => '/(?:observaciones?)\s*[:\-]\s*([^\r\n]+)/iu',
            'doctor_name' => '/(?:dr\.?|dra\.?|m[eé]dico)\s*[:\-]?\s*([\p{L} .\'-]{4,})/iu',
            'doctor_credential' => '/(?:colegiatura|registro|cmp|c[oó]digo\s+m[eé]dico|licencia|c[eé]dula\s+profesional)\s*(?:n(?:ro|[úu]mero)?\.?)?\s*[:\-]?\s*([A-Z0-9.-]{3,})/iu',
            'clinic_name' => '/(?:cl[ií]nica|centro\s+m[eé]dico|hospital)\s*[:\-]?\s*([^\r\n]+)/iu',
            'clinic_address' => '/(?:direcci[oó]n)\s*[:\-]\s*([^\r\n]+)/iu',
        ];
        $candidates = [];
        $split = max(1, (int) floor(strlen($text) * .3));
        foreach ($patterns as $field => $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $index => [$value, $offset]) {
                    $value = trim(preg_replace('/\s+/', ' ', $value), " \t\n\r\0\x0B.,;");
                    if ($value !== '') {
                        $candidates[] = new FieldCandidate($field, $value, $offset <= $split ? 'header' : 'body', .9, $matches[0][$index][0] ?? null);
                    }
                }
            }
        }

        return $candidates;
    }
}
