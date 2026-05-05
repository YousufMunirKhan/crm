<?php

/*
| Bulk retry from Email Management → Report → Retry queue.
| Optional .env: MAIL_RETRY_CHUNK_SIZE, MAIL_RETRY_CHUNK_DELAY_SECONDS
*/

return [
    'chunk_size' => max(1, (int) env('MAIL_RETRY_CHUNK_SIZE', 5)),
    'chunk_delay_seconds' => max(0, (int) env('MAIL_RETRY_CHUNK_DELAY_SECONDS', 15)),
];
