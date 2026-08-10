<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PdfTemplate extends Model
{
    use HasUuid;

    protected $fillable = ['clinic_id', 'name', 'document_type', 'certificate_kind', 'page_size', 'source_path', 'version',
        'qr_page', 'qr_x', 'qr_y', 'qr_width', 'qr_height', 'coordinates', 'field_schema', 'supersedes_id', 'is_active'];

    protected function casts(): array
    {
        return ['coordinates' => 'array', 'field_schema' => 'array', 'is_active' => 'boolean'];
    }

    public function documents()
    {
        return $this->hasMany(MedicalDocument::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function supersedes()
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }
}
