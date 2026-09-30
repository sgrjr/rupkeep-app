<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * TASK-425: public/info.php called phpinfo() and was live on production,
 * exposing PHP config, paths and env-derived values. The web root should
 * contain exactly one PHP entry point.
 */
class PublicDirectoryHygieneTest extends TestCase
{
    public function test_index_is_the_only_php_file_in_the_web_root(): void
    {
        $files = array_map('basename', glob(public_path('*.php')));

        $this->assertSame(['index.php'], $files, 'unexpected PHP file(s) in public/: '.implode(', ', $files));
    }
}
