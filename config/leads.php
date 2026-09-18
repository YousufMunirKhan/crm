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
    | There is no setting for this on purpose. Having a target set for the month
    | is the whole test, whatever role somebody holds - the owner and the manager
    | on this system create more leads than half the sales team, and a rule
    | written in roles had them watching a number they were personally missing.
    |
    | The targets screen is the single switch: set somebody a target and they
    | start being told where they stand; leave it unset and they are not.
    |
    |--------------------------------------------------------------------------
    | Who sees everybody
    |--------------------------------------------------------------------------
    |
    | The team table is a management view, so this one is a role. Anybody here
    | who also has a target of their own gets both: their figure, and the room's.
    |
    */

    'summary_roles' => ['Admin', 'System Admin', 'Manager'],

];
