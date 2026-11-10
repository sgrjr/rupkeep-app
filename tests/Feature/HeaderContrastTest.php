<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The app layout paints every page's <x-slot name="header"> on an orange
 * gradient. Grey, near-black or orange text placed straight onto it is hard
 * to read: the annual vehicle report's back link and subtitle were slate-400
 * and slate-500 there (drive 003). Text in the header is white; a control
 * with its own white background (a pill button) may use any colour.
 */
class HeaderContrastTest extends TestCase
{
    private const UNREADABLE_ON_ORANGE = '/\b(?<!hover:)(?<!dark:)text-(slate|gray|orange)-[3-9]00\b/';

    public function test_header_slots_put_no_grey_or_orange_text_on_the_orange_header(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $inside = false;
            foreach (preg_split('/\R/', $file->getContents()) as $number => $line) {
                if (str_contains($line, 'x-slot name="header"')) {
                    $inside = true;
                }

                if ($inside && ! str_contains($line, 'bg-white') && preg_match(self::UNREADABLE_ON_ORANGE, $line)) {
                    $offenders[] = $file->getRelativePathname().':'.($number + 1).': '.trim($line);
                }

                if ($inside && str_contains($line, '</x-slot>')) {
                    $inside = false;
                }
            }
        }

        $this->assertSame([], $offenders, "Unreadable text on the orange page header:\n".implode("\n", $offenders));
    }
}
