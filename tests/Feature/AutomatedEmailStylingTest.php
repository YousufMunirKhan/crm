<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The automated emails share one layout and that layout carries no stylesheet,
 * because half of what a stylesheet can say is thrown away by Outlook and by
 * Gmail's clipping. Everything is styled inline instead.
 *
 * A class attribute in one of these therefore does nothing at all, and does it
 * silently: a label and its value written as two spans with `class="label"` and
 * `class="value"` ran together into "Who is comingAamir Ali" in a real
 * customer's inbox, because the rule that separated them had been removed from
 * the layout and nothing pointed out that four templates still referred to it.
 */
class AutomatedEmailStylingTest extends TestCase
{
    public function test_no_automated_email_relies_on_a_stylesheet(): void
    {
        $offenders = [];

        foreach (glob(resource_path('views/emails/automated/**/*.blade.php')) + glob(resource_path('views/emails/automated/*.blade.php')) as $path) {
            foreach (file($path, FILE_IGNORE_NEW_LINES) as $number => $line) {
                if (preg_match('/\sclass="[^"]+"/', $line, $m)) {
                    $offenders[] = basename($path).':'.($number + 1).' '.trim($m[0]);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These style nothing - the shared layout has no stylesheet, so use inline styles:\n  "
                .implode("\n  ", $offenders)
        );
    }

    public function test_the_layout_still_has_no_stylesheet_to_rely_on(): void
    {
        // If one is ever added, the rule above stops being true and this test
        // is the place that says so rather than a mailbox six months later.
        $layout = file_get_contents(resource_path('views/emails/automated/layout.blade.php'));

        $this->assertStringNotContainsString('<style', $layout);
    }
}
