<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('medical_document_code', 100)->nullable()->after('medical_document_id');
            $table->string('medical_document_type', 60)->nullable()->after('medical_document_code');
            $table->string('service_professional', 255)->nullable()->after('medical_document_type');
            $table->date('service_date')->nullable()->after('service_professional');
            $table->time('service_time')->nullable()->after('service_date');
        });

        DB::table('invoices')->whereNotNull('medical_document_id')->orderBy('id')->each(function (object $invoice): void {
            $document = DB::table('medical_documents')
                ->leftJoin('patients', 'patients.id', '=', 'medical_documents.patient_id')
                ->leftJoin('doctors', 'doctors.id', '=', 'medical_documents.doctor_id')
                ->where('medical_documents.id', $invoice->medical_document_id)
                ->select([
                    'medical_documents.consultation_date', 'medical_documents.consultation_time',
                    'medical_documents.public_code', 'medical_documents.certificate_kind',
                    'medical_documents.type', 'patients.first_name', 'patients.last_name',
                    'patients.document_number', 'doctors.professional_name', 'doctors.first_name as doctor_first_name',
                    'doctors.last_name as doctor_last_name',
                ])->first();
            if (! $document) {
                return;
            }

            $professional = $document->professional_name ?: trim(($document->doctor_first_name ?: '').' '.($document->doctor_last_name ?: ''));
            $values = [
                'service_date' => $invoice->service_date ?: $document->consultation_date,
                'service_time' => $invoice->service_time ?: $document->consultation_time,
                'medical_document_code' => $invoice->medical_document_code ?: $document->public_code,
                'medical_document_type' => $invoice->medical_document_type ?: ($document->certificate_kind ?: $document->type),
                'service_professional' => $invoice->service_professional ?: ($professional ?: null),
            ];
            if (! $invoice->recipient_name && $document->first_name) {
                $values['recipient_name'] = trim($document->first_name.' '.$document->last_name);
            }
            if (! $invoice->recipient_tax_id && $document->document_number) {
                $values['recipient_tax_id'] = $document->document_number;
            }

            DB::table('invoices')->where('id', $invoice->id)->update($values);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'service_date',
                'service_time',
                'medical_document_code',
                'medical_document_type',
                'service_professional',
            ]);
        });
    }
};
