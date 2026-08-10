<?php

namespace App\Enums;

enum MedicalDocumentType: string
{
    case MEDICAL_CERTIFICATE = 'MEDICAL_CERTIFICATE';
    case MEDICAL_REPORT = 'MEDICAL_REPORT';
    case PRESCRIPTION = 'PRESCRIPTION';
    case LAB_RESULT = 'LAB_RESULT';
    case REFERRAL = 'REFERRAL';
    case OTHER = 'OTHER';
}
