<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DocumentVerificationLog extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['successful' => 'boolean', 'identity_verified' => 'boolean', 'verified_at' => 'datetime', 'context' => 'array'];
    }

    public function document()
    {
        return $this->belongsTo(MedicalDocument::class, 'medical_document_id');
    }
}
