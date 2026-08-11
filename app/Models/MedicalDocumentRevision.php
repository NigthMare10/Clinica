<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class MedicalDocumentRevision extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['source_snapshot' => 'array', 'current_snapshot' => 'array'];
    }

    public function document()
    {
        return $this->belongsTo(MedicalDocument::class, 'medical_document_id');
    }

    public function source()
    {
        return $this->belongsTo(MedicalDocument::class, 'source_document_id');
    }

    public function corrector()
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
