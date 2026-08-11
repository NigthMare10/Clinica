<?php

return [
    'disk' => env('INVOICE_PDF_DISK', env('MEDICAL_DOCUMENT_DISK', 'local')),
    'encryption_enabled' => filter_var(env('INVOICE_PDF_ENCRYPTION', true), FILTER_VALIDATE_BOOL),
    'max_items' => (int) env('INVOICE_PDF_MAX_ITEMS', 12),
    'max_item_lines' => (int) env('INVOICE_PDF_MAX_ITEM_LINES', 13),
    'description_characters_per_line' => (int) env('INVOICE_PDF_DESCRIPTION_CHARACTERS_PER_LINE', 52),
    'institutional_marks' => [
        'signature' => ['x' => 28, 'y' => 231, 'width' => 39],
        'stamp' => ['x' => 78, 'y' => 227, 'width' => 34],
        'SIGNATURE_STAMP_COMBINED' => ['x' => 28, 'y' => 222, 'width' => 78],
    ],
];
