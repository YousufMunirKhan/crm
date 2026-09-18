<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Blade only treats `@thing` as a directive when the character before it is not
 * a word character - its own pattern starts `\B@`. So this compiles:
 *
 *     @section('heading'){{ $name }}@endsection
 *
 * and this silently does not:
 *
 *     @section('heading')Where you are today@endsection
 *
 * The second leaves the section open and prints its text, plus the literal
 * "@endsection", above the layout - which is what went out in a real email.
 * Nothing warns; it is only visible by reading the rendered output.
 */
class BladeDirectiveHygieneTest extends TestCase
{
    public function test_no_template_hides_a_directive_behind_a_word(): void
    {
        $directives = 'endsection|endif|endforeach|endforelse|endwhile|endphp|endverbatim|endpush|endprepend|endcomponent|endslot|endfor|endunless|endisset|endempty|endauth|endguest|section|yield|extends|include|php|if|else|elseif|foreach|push|component';

        $offenders = [];

        foreach ($this->templates() as $path) {
            $lines = file($path, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $number => $line) {
                if (preg_match('/\w@('.$directives.')\b/', $line, $m)) {
                    $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path)
                        .':'.($number + 1).'  @'.$m[1];
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Blade will not compile these - put whitespace or a newline before the directive:\n  "
                .implode("\n  ", $offenders)
        );
    }

    /** @return string[] */
    private function templates(): array
    {
        $found = [];
        $dir = new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS);

        foreach (new \RecursiveIteratorIterator($dir) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }
}
