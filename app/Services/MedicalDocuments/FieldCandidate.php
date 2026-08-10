<?php

namespace App\Services\MedicalDocuments;

final readonly class FieldCandidate
{
    public function __construct(
        public string $field,
        public string $value,
        public string $source,
        public float $confidence = 1.0,
        public ?string $raw = null,
    ) {}

    public function toArray(): array
    {
        return ['field' => $this->field, 'value' => $this->value, 'source' => $this->source,
            'confidence' => $this->confidence, 'raw' => $this->raw];
    }
}
