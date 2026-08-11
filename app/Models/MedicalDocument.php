<?php

namespace App\Models;

use App\Enums\MedicalDocumentStatus;
use App\Enums\MedicalDocumentType;
use App\Enums\UserRole;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalDocument extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'type', 'clinic_id', 'source_kind', 'certificate_kind', 'patient_id', 'doctor_id', 'specialty_id', 'pdf_template_id', 'original_filename',
        'age_at_consultation', 'consultation_date', 'consultation_time', 'symptoms', 'medical_reason',
        'diagnosis', 'leave_start_date', 'leave_end_date', 'leave_days', 'recommendations', 'confirmed_fields', 'status',
    ];

    protected $hidden = ['token_hash', 'original_path', 'issued_path'];

    protected function casts(): array
    {
        return ['type' => MedicalDocumentType::class, 'status' => MedicalDocumentStatus::class,
            'confirmed_fields' => 'array', 'inconsistencies' => 'array', 'processing_metadata' => 'array', 'template_snapshot' => 'array',
            'consultation_date' => 'date', 'leave_start_date' => 'date', 'leave_end_date' => 'date', 'generated_at' => 'datetime', 'revision_number' => 'integer', 'is_current_revision' => 'boolean',
            'digital_signature_detected' => 'boolean', 'reviewed_at' => 'datetime', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            if ($document->isDirty('status')) {
                $from = MedicalDocumentStatus::tryFrom((string) $document->getRawOriginal('status'));
                $to = $document->status;
                $allowed = [
                    MedicalDocumentStatus::DRAFT->value => [MedicalDocumentStatus::PROCESSING],
                    MedicalDocumentStatus::PROCESSING->value => [MedicalDocumentStatus::REVIEW_REQUIRED, MedicalDocumentStatus::FAILED],
                    MedicalDocumentStatus::REVIEW_REQUIRED->value => [MedicalDocumentStatus::REVIEW_REQUIRED, MedicalDocumentStatus::READY],
                    MedicalDocumentStatus::READY->value => [MedicalDocumentStatus::READY, MedicalDocumentStatus::REVIEW_REQUIRED, MedicalDocumentStatus::ISSUED],
                    MedicalDocumentStatus::ISSUED->value => [MedicalDocumentStatus::REVOKED, MedicalDocumentStatus::REPLACED],
                    MedicalDocumentStatus::REVOKED->value => [MedicalDocumentStatus::REPLACED],
                    MedicalDocumentStatus::REPLACED->value => [],
                    MedicalDocumentStatus::FAILED->value => [MedicalDocumentStatus::PROCESSING],
                ];
                if (! $from || ! in_array($to, $allowed[$from->value] ?? [], true)) {
                    throw new \DomainException('Invalid medical document status transition.');
                }
            }

            $terminal = [MedicalDocumentStatus::ISSUED, MedicalDocumentStatus::REVOKED, MedicalDocumentStatus::REPLACED];
            $originalStatus = MedicalDocumentStatus::tryFrom((string) $document->getRawOriginal('status'));
            if (! in_array($originalStatus, $terminal, true)) {
                return;
            }

            $lifecycle = ['status', 'revoked_at', 'revoked_by', 'revocation_reason', 'replaced_by_id', 'is_current_revision', 'updated_at'];
            $dirtyProtected = array_diff(array_keys($document->getDirty()), $lifecycle);
            if ($dirtyProtected !== []) {
                throw new \DomainException('Issued, revoked, and replaced document content is immutable; create a reissue.');
            }

            $newStatus = $document->status;
            if ($originalStatus === MedicalDocumentStatus::REVOKED && $newStatus === MedicalDocumentStatus::ISSUED) {
                throw new \DomainException('A revoked document cannot be issued again.');
            }
            if ($originalStatus === MedicalDocumentStatus::REPLACED && $newStatus !== MedicalDocumentStatus::REPLACED) {
                throw new \DomainException('A replaced document cannot transition.');
            }
        });
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(UserRole::SUPER_ADMIN)) {
            return $query;
        }

        return $query->where(fn (Builder $builder) => $builder->whereNull('clinic_id')->orWhereIn('clinic_id', $user->accessibleClinicIds()));
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }

    public function template()
    {
        return $this->belongsTo(PdfTemplate::class, 'pdf_template_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function reissueOf()
    {
        return $this->belongsTo(self::class, 'reissue_of_id');
    }

    public function reissues()
    {
        return $this->hasMany(self::class, 'reissue_of_id');
    }

    public function revision()
    {
        return $this->hasOne(MedicalDocumentRevision::class);
    }

    public function replacedBy()
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    public function revoker()
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function extractions()
    {
        return $this->hasMany(DocumentExtraction::class);
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(DocumentAuditLog::class);
    }

    public function verificationLogs()
    {
        return $this->hasMany(DocumentVerificationLog::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
