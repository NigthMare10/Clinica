<?php

return [
    'disk' => env('INVOICE_PDF_DISK', env('MEDICAL_DOCUMENT_DISK', 'local')),
    'encryption_enabled' => filter_var(env('INVOICE_PDF_ENCRYPTION', true), FILTER_VALIDATE_BOOL),
    // The fixed fiscal zones reserve the bottom of the page for QR, authorizations and marks.
    'max_items' => (int) env('INVOICE_PDF_MAX_ITEMS', 8),
    'footer_y' => (float) env('INVOICE_PDF_FOOTER_Y', 220),
    'institutional_marks' => [
        'signature' => ['x' => 28, 'y' => 231, 'width' => 39],
        'stamp' => ['x' => 78, 'y' => 227, 'width' => 34],
        // A compact 36 mm combined mark leaves the verification QR independent and readable.
        'SIGNATURE_STAMP_COMBINED' => ['x' => 88, 'y' => 236, 'width' => 36],
    ],
];
