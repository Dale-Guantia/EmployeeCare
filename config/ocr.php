<?php

return [
    // Below this many non-whitespace chars on a page, the page is treated as
    // having no usable text layer and is routed to OCR. Documents can be
    // mixed: some pages keep their native text, only the deficient pages
    // are rasterized and OCR'd.
    'min_text_chars_per_page' => env('OCR_MIN_TEXT_CHARS_PER_PAGE', 100),

    // Rasterization DPI for pages routed to OCR (pdftoppm -r).
    'dpi' => env('OCR_DPI', 300),

    // Tesseract engine mode. 1 = LSTM only.
    'oem' => env('OCR_OEM', 1),

    // Tesseract page segmentation mode. 3 = fully automatic, 6 = uniform block of text.
    'psm' => env('OCR_PSM', 3),

    // Tesseract mean word-confidence (0-100, from TSV output) below which a
    // document is flagged needs_review instead of being ingested as active.
    'min_confidence' => env('OCR_MIN_CONFIDENCE', 60),

    // Max seconds a single page's tesseract call may run before that page
    // is marked failed and the rest of the document continues.
    'page_timeout' => env('OCR_PAGE_TIMEOUT', 60),

    // Max OCR page processes run concurrently per document.
    'max_concurrency' => env('OCR_MAX_CONCURRENCY', 2),
];
