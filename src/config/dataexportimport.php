<?php

return [
    'default_export_format' => 'csv', // Default export format (csv, xlsx, pdf)
    'import_chunk_size' => 100,       // Number of records processed per batch during import
    'log_imports' => true,            // Whether to log imports in the database
    'log_exports' => true,            // Whether to log exports in the database
    // Add any other options here
];
