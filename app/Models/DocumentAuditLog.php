<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DocumentAuditLog extends Model
{
    use HasUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['old_value' => 'array', 'new_value' => 'array', 'metadata' => 'array'];
    }

    public function document()
    {
        return $this->belongsTo(MedicalDocument::class, 'medical_document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
