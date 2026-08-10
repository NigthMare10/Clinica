<?php

namespace App\Enums;

enum MedicalDocumentStatus: string
{
    case DRAFT = 'DRAFT';
    case PROCESSING = 'PROCESSING';
    case REVIEW_REQUIRED = 'REVIEW_REQUIRED';
    case READY = 'READY';
    case ISSUED = 'ISSUED';
    case REVOKED = 'REVOKED';
    case REPLACED = 'REPLACED';
    case FAILED = 'FAILED';
}
