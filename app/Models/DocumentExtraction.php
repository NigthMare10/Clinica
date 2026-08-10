<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DocumentExtraction extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['candidates' => 'array', 'warnings' => 'array', 'quality_score' => 'decimal:2'];
    }

    public function document()
    {
        return $this->belongsTo(MedicalDocument::class, 'medical_document_id');
    }
}
