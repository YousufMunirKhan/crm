<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Links that point at pages which do not exist.
 *
 * The route is `/followups`. Three places - the morning notification, the
 * owner's dashboard and the rep's dashboard - all linked to `/follow-ups`, and
 * every one of them landed on a 404. The existing test asserted the string
 * matched what the code emitted, which it did: both were wrong together.
 *
 * A link is only correct if the router has somewhere to send it, so that is
 * what these check.
 */
class NavigationLinksTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Real URLs on this domain that the SPA router deliberately does not own -
     * the Filament back office is served by Laravel, not by Vue.
     */
    private const NOT_SPA_ROUTES = ['/admin'];

    /**
     * Every path the SPA router will answer to.
     *
     * @return list<string>
     */
    private function routerPaths(): array
    {
        $router = file_get_contents(resource_path('js/router/index.js'));

        preg_match_all("/path:\s*'([^']*)'/", $router, $matches);

        $absolute = array_values(array_filter($matches[1], fn ($p) => str_starts_with($p, '/')));

        // Children are written relative to their parent - `/hr` with a child
        // `attendance-report` is the real `/hr/attendance-report` - so pair
        // every relative segment with every parent rather than trying to track
        // nesting through the braces.
        $relative = array_values(array_filter(
            $matches[1],
            fn ($p) => $p !== '' && ! str_starts_with($p, '/')
        ));

        $paths = $absolute;

        foreach ($absolute as $parent) {
            foreach ($relative as $child) {
                $paths[] = rtrim($parent, '/').'/'.$child;
            }
        }

        $this->assertNotEmpty($paths, 'Could not read any routes out of the router.');

        return $paths;
    }

    /** Does the router have somewhere to send this, ignoring any query string? */
    private function isRoutable(string $link, array $paths): bool
    {
        $path = rtrim(strtok($link, '?'), '/') ?: '/';

        foreach ($paths as $candidate) {
            // A route segment written as :id matches anything in that position.
            $pattern = '#^'.preg_replace('#:[A-Za-z0-9_]+#', '[^/]+', preg_quote($candidate, '#')).'$#';
            $pattern = str_replace(['\:', '\[\^/\]\+'], [':', '[^/]+'], $pattern);

            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    public function test_every_morning_notification_links_somewhere_real(): void
    {
        $salesRole = Role::firstOrCreate(['name' => 'Sales'], ['nav_permissions' => null]);
        $managerRole = Role::firstOrCreate(['name' => 'Manager'], ['nav_permissions' => null]);

        $rep = User::factory()->create(['role_id' => $salesRole->id, 'is_active' => true]);
        User::factory()->create(['role_id' => $managerRole->id, 'is_active' => true]);

        $customer = Customer::create(['name' => 'Shop', 'phone' => '07700900111']);

        // One lead for each kind of notification the command can raise.
        Lead::create([
            'customer_id' => $customer->id, 'stage' => 'lead',
            'assigned_to' => $rep->id, 'next_follow_up_at' => now()->subDays(5),
        ]);

        $old = Lead::create(['customer_id' => $customer->id, 'stage' => 'lead', 'assigned_to' => $rep->id]);
        $old->forceFill(['created_at' => now()->subDays(90)])->saveQuietly();

        Lead::create(['customer_id' => $customer->id, 'stage' => 'lead', 'assigned_to' => null]);

        $this->artisan('crm:daily-worklist')->assertSuccessful();

        $notifications = Notification::whereNotNull('data')->get();

        $this->assertNotEmpty($notifications, 'Nothing was raised, so nothing was checked.');

        $paths = $this->routerPaths();

        foreach ($notifications as $notification) {
            $route = $notification->data['route'] ?? null;

            if ($route === null) {
                continue;
            }

            $this->assertTrue(
                $this->isRoutable($route, $paths),
                "The \"{$notification->type}\" notification links to {$route}, which is not a route. "
                    .'Everyone who taps it gets a 404.'
            );
        }
    }

    public function test_no_screen_links_to_a_page_that_does_not_exist(): void
    {
        $paths = $this->routerPaths();
        $broken = [];

        foreach ($this->vueFiles() as $file) {
            $source = file_get_contents($file);

            // Static links only. A bound `:to` is an expression and cannot be
            // read this way; those stay the job of the pages themselves.
            preg_match_all('/(?<![:\w])to="(\/[^"{}]*)"/', $source, $inTemplate);
            preg_match_all("/\bto:\s*'(\/[^'\${}]*)'/", $source, $inScript);

            foreach (array_merge($inTemplate[1], $inScript[1]) as $link) {
                if (in_array($link, self::NOT_SPA_ROUTES, true)) {
                    continue;
                }

                if (! $this->isRoutable($link, $paths)) {
                    $broken[] = basename($file).' → '.$link;
                }
            }
        }

        $this->assertSame([], $broken, "These links point at pages that do not exist:\n".implode("\n", $broken));
    }

    /** @return list<string> */
    private function vueFiles(): array
    {
        $out = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'vue') {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }
}
