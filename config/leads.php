<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Daily lead target
    |--------------------------------------------------------------------------
    |
    | How many new leads each person on the phones or on the road is expected to
    | put on the board in a day. The targets in employee_targets are monthly and
    | about appointments and sales; this is the one number a rep is measured on
    | every morning, and it is the same for everybody.
    |
    */

    'daily_target' => (int) env('LEADS_DAILY_TARGET', 5),

    /*
    |--------------------------------------------------------------------------
    | Who is measured on it
    |--------------------------------------------------------------------------
    |
    | The roles that are expected to create leads, and so are the ones that get
    | told each morning where they stand. Everyone else appears in the summary
    | that goes to management, not in a message addressed to them.
    |
    */

    'target_roles' => ['Sales', 'CallAgent'],

    /*
    |--------------------------------------------------------------------------
    | Who sees everybody
    |--------------------------------------------------------------------------
    */

    'summary_roles' => ['Admin', 'System Admin', 'Manager'],

];
