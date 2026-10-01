<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * TASK-463 (TASK-394 and TASK-419 leftovers). Form controls are component
 * boundaries and WCAG wants 3:1 there; border-slate-200/300 render at about
 * 1.5:1 on white. A control without its own text colour inherits whatever
 * the theme gives the page, which under the dark theme on a white field was
 * light grey on white.
 *
 * Every <input>, <select> and <textarea> in the views must therefore carry a
 * border no lighter than slate-400 and, unless it is a checkbox or radio,
 * name its own text colour and background. This scans the source so a new
 * control cannot slip back to the old classes unnoticed.
 */
class FormControlContrastTest extends TestCase
{
    /** Views that are dead and awaiting removal (TASK-474). */
    private const SKIP = ['show-pilot-car-job-0.blade.php'];

    public function test_every_form_control_has_a_visible_border_a_text_colour_and_a_background(): void
    {
        $problems = [];

        $finder = Finder::create()->files()->in(resource_path('views'))->name('*.blade.php');

        foreach ($finder as $file) {
            if (in_array($file->getFilename(), self::SKIP, true)) {
                continue;
            }

            $source = $file->getContents();

            preg_match_all('/<(input|select|textarea)\b((?:->|"[^"]*"|\'[^\']*\'|[^>"\'])*)>/i', $source, $tags, PREG_SET_ORDER);

            foreach ($tags as [$tag, $name, $attrs]) {
                if (preg_match('/type="(hidden|file)"/', $attrs)) {
                    continue;
                }

                if (! preg_match('/class="([^"]*)"/', $attrs, $cm)) {
                    continue; // a component root that merges $attributes
                }

                $classes = $cm[1];

                if (preg_match('/(^|\s)hidden(\s|$)/', $classes)) {
                    continue;
                }

                $isCheck = (bool) preg_match('/type="(checkbox|radio)"/', $attrs);
                $found = [];

                if (preg_match('/\bborder-slate-(200|300)\b/', $classes)) {
                    $found[] = 'light border';
                }
                if (! $isCheck && ! preg_match('/(^|\s)text-/', $classes)) {
                    $found[] = 'no text colour';
                }
                if (! $isCheck && ! preg_match('/(^|\s)bg-/', $classes)) {
                    $found[] = 'no background';
                }

                if ($found) {
                    $problems[] = $file->getRelativePathname().': <'.$name.'> '.implode(', ', $found).' :: '.substr($classes, 0, 80);
                }
            }
        }

        $this->assertSame([], $problems, "Form controls with contrast problems:\n".implode("\n", $problems));
    }

    public function test_the_input_component_defaults_to_a_visible_border_dark_text_and_a_white_field(): void
    {
        $source = file_get_contents(resource_path('views/components/input.blade.php'));

        $this->assertStringContainsString('border-slate-400', $source);
        $this->assertStringContainsString('text-slate-900', $source);
        $this->assertStringContainsString('bg-white', $source);
        $this->assertStringNotContainsString("'class' => 'border-gray-300", $source);
    }
}
