<?php

namespace Tests\Feature;

use App\Modules\CRM\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Some text was written as UTF-8, read back as Windows-1252 and re-encoded, so
 * an apostrophe became "a EUR (tm)" and an accented letter gained a stray A.
 * The repair is the exact inverse, and must refuse anything it cannot reverse
 * cleanly - a wrong "fix" on customer data is worse than leaving it.
 */
class MojibakeRepairTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $name): Customer
    {
        return Customer::create([
            'name' => $name,
            'phone' => '4477009012'.random_int(10, 99),
        ]);
    }

    public function test_it_repairs_a_mangled_apostrophe(): void
    {
        $customer = $this->customer("Marie\u{00e2}\u{20ac}\u{2122}s");

        $this->artisan('data:repair-mojibake', ['--confirm' => true])->assertSuccessful();

        $this->assertSame("Marie\u{2019}s", $customer->fresh()->name);
    }

    public function test_it_repairs_a_mangled_accent(): void
    {
        $customer = $this->customer("Hill Side Caf\u{00c3}\u{00a9}");

        $this->artisan('data:repair-mojibake', ['--confirm' => true])->assertSuccessful();

        $this->assertSame("Hill Side Caf\u{00e9}", $customer->fresh()->name);
    }

    public function test_clean_text_is_left_untouched(): void
    {
        $clean = "Marie\u{2019}s Caf\u{00e9}";
        $customer = $this->customer($clean);

        $this->artisan('data:repair-mojibake', ['--confirm' => true])->assertSuccessful();

        $this->assertSame($clean, $customer->fresh()->name);
    }

    public function test_plain_ascii_is_left_untouched(): void
    {
        $customer = $this->customer('Acme Trading Ltd');

        $this->artisan('data:repair-mojibake', ['--confirm' => true])->assertSuccessful();

        $this->assertSame('Acme Trading Ltd', $customer->fresh()->name);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $mangled = "Marie\u{00e2}\u{20ac}\u{2122}s";
        $customer = $this->customer($mangled);

        $this->artisan('data:repair-mojibake')->assertSuccessful();

        $this->assertSame($mangled, $customer->fresh()->name);
    }
}
