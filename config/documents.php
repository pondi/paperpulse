<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported File Formats
    |--------------------------------------------------------------------------
    |
    | These settings define the supported file formats for receipts and documents.
    | The values should be comma-separated lists of file extensions.
    |
    */

    'supported_receipt_formats' => env('SUPPORTED_RECEIPT_FORMATS', 'jpg,jpeg,png,gif,bmp,pdf'),
    'supported_document_formats' => env('SUPPORTED_DOCUMENT_FORMATS', 'doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,pdf,rtf'),

    /*
    |--------------------------------------------------------------------------
    | Maximum File Sizes
    |--------------------------------------------------------------------------
    |
    | These values define the maximum file sizes (in MB) for receipts and documents.
    |
    */

    'max_receipt_size' => env('MAX_RECEIPT_SIZE', 10),
    'max_document_size' => env('MAX_DOCUMENT_SIZE', 50),

];