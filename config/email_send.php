<?php

/*
| Bulk / marketing email pacing (reduces SMTP 450 "too much mail" from the host).
| .env: MAIL_BULK_BETWEEN_MESSAGE_DELAY_MS (milliseconds between each send, default 1200)
*/

return [
    'between_message_delay_ms' => max(0, (int) env('MAIL_BULK_BETWEEN_MESSAGE_DELAY_MS', 1200)),
];
