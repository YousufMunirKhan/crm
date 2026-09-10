<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Running the scheduler over HTTP
    |--------------------------------------------------------------------------
    |
    | This host has no crontab. Not restricted - absent: there is no binary and
    | no spool, because cron on this plan is set from the control panel and
    | nowhere else. So nothing on a schedule has ever run here, which is why a
    | quarter of attendance sat open, invoices were never marked overdue, and
    | the ticket SLA check has never once fired.
    |
    | With a token set, an outside pinger can hit the endpoint every minute and
    | the scheduler runs. It is a workaround and worth replacing with a real
    | cron entry when somebody can reach the panel; both do the same job, and
    | leaving this on alongside one is harmless - schedule:run works out what is
    | due and ignores the rest.
    |
    | Empty means the route does not exist. That is the default: an endpoint
    | that runs the application's jobs should have to be switched on deliberately.
    |
    */

    'http_token' => (string) env('SCHEDULER_HTTP_TOKEN', ''),

];
