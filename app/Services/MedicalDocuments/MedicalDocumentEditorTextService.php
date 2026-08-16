<?php

namespace App\Services\MedicalDocuments;

class MedicalDocumentEditorTextService
{
    /** Remove presentation-only markers without rewriting clinical content. */
    public function clean(string $text): string
    {
        $text = preg_replace('/\*\*(.*?)\*\*/su', '$1', $text);
        $text = preg_replace('/__(.*?)__/su', '$1', (string) $text);
        $text = preg_replace('/`([^`]+)`/su', '$1', (string) $text);

        return trim((string) preg_replace('/^[ \t]{0,3}#{1,6}[ \t]*/mu', '', (string) $text));
    }
}
