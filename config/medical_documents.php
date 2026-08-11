<?php

return [
    'disk' => env('MEDICAL_DOCUMENT_DISK', 'local'),
    'max_upload_kb' => (int) env('MEDICAL_PDF_MAX_KB', 15360),
    // INSTITUTIONAL_PDF_PASSWORD is the preferred name; the legacy setting remains supported.
    'password' => env('INSTITUTIONAL_PDF_PASSWORD', env('MEDICAL_PDF_PASSWORD', '')),
    'encryption_enabled' => filter_var(env('MEDICAL_PDF_ENCRYPTION', true), FILTER_VALIDATE_BOOL),
    'binaries' => [
        'pdftotext' => env('PDFTOTEXT_BINARY', 'pdftotext'),
        'pdftoppm' => env('PDFTOPPM_BINARY', 'pdftoppm'),
        'pdfinfo' => env('PDFINFO_BINARY', 'pdfinfo'),
        'tesseract' => env('TESSERACT_BINARY', 'tesseract'),
        'qpdf' => env('QPDF_BINARY', 'qpdf'),
    ],
    'process_timeout' => (int) env('MEDICAL_PDF_PROCESS_TIMEOUT', 120),
    'ocr_languages' => env('MEDICAL_OCR_LANGUAGES', 'spa+eng'),
    'text_quality_threshold' => (float) env('MEDICAL_TEXT_QUALITY_THRESHOLD', 0.55),
    'qr' => ['size' => (int) env('MEDICAL_QR_SIZE', 300), 'margin' => (int) env('MEDICAL_QR_MARGIN', 20)],
    'stamp' => [
        'qr' => ['x' => 154, 'y' => 214, 'width' => 30],
        'code' => ['x' => 142, 'y' => 246, 'font_size' => 7.2],
    ],
    // Millimeter coordinates; QR remains at the lower-right and does not overlap these marks.
    'institutional_marks' => [
        'signature' => ['x' => 30, 'y' => 216, 'width' => 42],
        'stamp' => ['x' => 106, 'y' => 211, 'width' => 35],
        'SIGNATURE_STAMP_COMBINED' => ['x' => 30, 'y' => 205, 'width' => 82],
    ],
    'institutional_provider' => config('institution.provider'),
];
