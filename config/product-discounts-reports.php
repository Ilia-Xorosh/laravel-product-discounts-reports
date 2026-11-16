<?php

return [
    'recording' => true,
    'admin' => [
        'enabled' => false,
        'prefix' => 'admin/product-discount-reports',
        'middleware' => ['web', 'auth', 'can:view-product-discount-reports'],
    ],
    // Prevent unbounded spreadsheet memory use. Narrow filters for larger reports.
    'export_max_rows' => 50000,
];
