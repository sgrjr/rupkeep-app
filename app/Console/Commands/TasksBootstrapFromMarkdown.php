<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TasksBootstrapFromMarkdown extends Command
{
    protected $signature = 'tasks:bootstrap-from-markdown
        {--source=TASKS.md : Markdown file to parse (relative to base_path)}
        {--bugs=docs/BUGS.md : Optional BUGS.md to inline into descriptions}
        {--out=docs/tasks.jsonld : Output JSON-LD path (relative to base_path)}';

    protected $description = 'One-time: parse TASKS.md into docs/tasks.jsonld (the structured bridge file)';

    /** Map of TASKS.md section headings to default status/type. */
    private const SECTION_DEFAULTS = [
        'Now / In Progress'      => ['status' => 'in_progress', 'type' => 'feature'],
        'Open Bugs'              => ['status' => 'open',        'type' => 'bug'],
        'Feature Backlog'        => ['status' => 'open',        'type' => 'feature'],
        'Verification Backlog'   => ['status' => 'verifying',   'type' => 'verify'],
        'Tech Debt / Cleanup'   => ['status' => 'open',        'type' => 'debt'],
        'Triage (incoming)'      => ['status' => 'triage',      'type' => 'feature'],
        'Recently Shipped'       => ['status' => 'done',        'type' => 'feature'],
    ];

    /** Priority emoji → enum. */
    private const PRIORITY_MAP = [
        '🔴' => 'blocker',
        '🟠' => 'high',
        '🟡' => 'medium',
        '🟢' => 'low',
    ];

    public function handle(): int
    {
        $sourcePath = base_path($this->option('source'));
        $bugsPath   = base_path($this->option('bugs'));
        $outPath    = base_path($this->option('out'));

        if (!is_file($sourcePath)) {
            $this->error("Source file not found: {$sourcePath}");
            return self::FAILURE;
        }

        $markdown = file_get_contents($sourcePath);
        $bugsMarkdown = is_file($bugsPath) ? file_get_contents($bugsPath) : '';

        $bugsAnchors = $this->indexBugsAnchors($bugsMarkdown);

        $tasks = $this->parseTasks($markdown, $bugsAnchors);
        $labels = $this->collectLabels($tasks);

        $doc = $this->buildJsonLd($tasks, $labels);

        if (!is_dir(dirname($outPath))) {
            mkdir(dirname($outPath), 0775, true);
        }

        file_put_contents(
            $outPath,
            json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );

        $this->info("Wrote {$outPath}");
        $this->line("Tasks: " . count($tasks));
        $this->line("Labels: " . count($labels));

        $byStatus = collect($tasks)->groupBy('status')->map->count();
        foreach ($byStatus as $status => $count) {
            $this->line("  {$status}: {$count}");
        }

        return self::SUCCESS;
    }

    /**
     * Walk the markdown and produce an array of parsed task records.
     */
    private function parseTasks(string $markdown, array $bugsAnchors): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $markdown);
        $tasks = [];

        $currentSection = null;
        $currentEpic   = null;
        $shipCounter   = 0;

        foreach ($lines as $line) {
            // Top-level section headings ## emoji Title
            if (preg_match('/^##\s+(?:\S+\s+)?(.+?)\s*$/u', $line, $m) && !str_starts_with(trim($line), '###')) {
                $title = $this->stripLeadingEmoji(trim($m[1]));
                $currentSection = $this->normalizeSection($title);
                $currentEpic = null;
                continue;
            }

            // Epic subheadings: ### Epic: Foo
            if (preg_match('/^###\s+Epic:\s*(.+?)\s*$/u', $line, $m)) {
                $currentEpic = 'epic:' . Str::slug($m[1]);
                continue;
            }

            // Other ### headings reset the current epic
            if (preg_match('/^###\s+/u', $line)) {
                $currentEpic = null;
                continue;
            }

            // Stop parsing when we hit References, Next ID, etc.
            if ($currentSection === '__skip__') {
                continue;
            }

            // Data rows in a markdown table — split by | and look for a TASK-### code
            if (!str_starts_with($line, '|') || str_contains($line, '---')) {
                continue;
            }

            $cells = $this->splitTableRow($line);
            if (count($cells) < 2) {
                continue;
            }

            // Skip header rows
            $first = $cells[0];
            if (in_array(strtolower($first), ['id', 'date', 'what to verify'], true)) {
                continue;
            }

            if ($currentSection === null) {
                continue;
            }

            // Recently Shipped rows have shape: | Date | Title |
            if ($currentSection === 'Recently Shipped') {
                $task = $this->parseShippedRow($cells, ++$shipCounter);
                if ($task) {
                    $tasks[] = $task;
                }
                continue;
            }

            // Standard rows: first cell is TASK-### (or skip if not)
            if (!preg_match('/^TASK-\d+$/i', $first)) {
                continue;
            }

            $defaults = self::SECTION_DEFAULTS[$currentSection] ?? null;
            if ($defaults === null) {
                continue;
            }

            $task = $this->parseRow($cells, $defaults, $currentEpic, $bugsAnchors);
            if ($task) {
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    private function parseRow(array $cells, array $defaults, ?string $epic, array $bugsAnchors): ?array
    {
        $code = strtoupper($cells[0]);
        $title = $cells[1] ?? '';

        if ($title === '') {
            return null;
        }

        // Find priority by scanning all cells for emoji
        $priority = $defaults['priority'] ?? 'medium';
        foreach ($cells as $cell) {
            foreach (self::PRIORITY_MAP as $emoji => $value) {
                if (str_contains($cell, $emoji)) {
                    $priority = $value;
                    break 2;
                }
            }
        }

        // Description: every cell beyond the title, minus the priority cell
        $descCells = [];
        foreach (array_slice($cells, 2) as $cell) {
            $stripped = $this->stripPriorityEmoji($cell);
            if ($stripped !== '' && !preg_match('/^(blocker|high|medium|low)$/i', $stripped)) {
                $descCells[] = $stripped;
            }
        }
        $description = trim(implode("\n\n", $descCells));

        // If any description cell is a BUGS.md anchor link, inline the bug body
        $description = $this->inlineBugsAnchor($description, $code, $bugsAnchors);

        $labels = [];
        if ($epic) {
            $labels[] = $epic;
        }

        return [
            'code' => $code,
            'title' => $title,
            'description' => $description,
            'type' => $defaults['type'],
            'status' => $defaults['status'],
            'priority' => $priority,
            'isPublic' => false,
            'labels' => $labels,
        ];
    }

    private function parseShippedRow(array $cells, int $counter): ?array
    {
        // Row layout: | Date | Title |
        $date = $cells[0] ?? '';
        $title = $cells[1] ?? '';

        if ($title === '' || strtolower($date) === 'date') {
            return null;
        }

        // Synthesize a SHIP-### code so it doesn't collide with TASK-###
        $code = 'SHIP-' . str_pad((string) $counter, 3, '0', STR_PAD_LEFT);

        // Try parsing date as ISO 8601-ish
        $shippedAt = null;
        try {
            $shippedAt = \Carbon\Carbon::parse($date)->toIso8601String();
        } catch (\Throwable) {
            // ignore
        }

        return [
            'code' => $code,
            'title' => $title,
            'description' => $shippedAt ? "Shipped on {$date}." : '',
            'type' => 'feature',
            'status' => 'done',
            'priority' => 'medium',
            'isPublic' => false,
            'labels' => ['historical'],
            'updatedAt' => $shippedAt,
        ];
    }

    /**
     * Index BUGS.md anchors so we can inline their bodies into task descriptions.
     */
    private function indexBugsAnchors(string $bugsMarkdown): array
    {
        if ($bugsMarkdown === '') {
            return [];
        }

        $anchors = [];
        $sections = preg_split('/^## /m', $bugsMarkdown);
        foreach ($sections as $section) {
            if (!preg_match('/^(TASK-\d+)/i', $section, $m)) {
                continue;
            }
            $code = strtoupper($m[1]);
            $body = trim($section);
            $anchors[$code] = $body;
        }

        return $anchors;
    }

    private function inlineBugsAnchor(string $description, string $taskCode, array $anchors): string
    {
        if (isset($anchors[$taskCode])) {
            $description .= "\n\n---\n\n" . $anchors[$taskCode];
        }
        return $description;
    }

    private function splitTableRow(string $line): array
    {
        $line = trim($line);
        $line = preg_replace('/^\||\|$/', '', $line);
        $cells = array_map('trim', explode('|', $line));
        // drop empty trailing cells
        while (!empty($cells) && end($cells) === '') {
            array_pop($cells);
        }
        return $cells;
    }

    private function stripLeadingEmoji(string $text): string
    {
        return trim(preg_replace('/^[\p{So}\p{Sk}\p{Sm}\s]+/u', '', $text));
    }

    private function stripPriorityEmoji(string $cell): string
    {
        foreach (array_keys(self::PRIORITY_MAP) as $emoji) {
            $cell = str_replace($emoji, '', $cell);
        }
        return trim($cell);
    }

    /**
     * Map a section heading (with emoji stripped) to a canonical key.
     */
    private function normalizeSection(string $title): string
    {
        $title = trim($title);
        foreach (array_keys(self::SECTION_DEFAULTS) as $key) {
            if (str_starts_with($title, $key)) {
                return $key;
            }
        }
        // Sections we want to ignore
        if (in_array($title, ['References', 'How to use this file'], true)) {
            return '__skip__';
        }
        // Default: skip unknown sections
        return '__skip__';
    }

    private function collectLabels(array $tasks): array
    {
        $labels = [];
        foreach ($tasks as $t) {
            foreach ($t['labels'] ?? [] as $name) {
                if (!isset($labels[$name])) {
                    $labels[$name] = [
                        '@type' => 'Label',
                        'name' => $name,
                        'color' => $this->defaultColorFor($name),
                        'description' => null,
                    ];
                }
            }
        }
        return array_values($labels);
    }

    private function defaultColorFor(string $name): ?string
    {
        if (str_starts_with($name, 'epic:')) return '#fb923c'; // orange
        if ($name === 'historical') return '#94a3b8'; // slate
        return null;
    }

    private function buildJsonLd(array $tasks, array $labels): array
    {
        return [
            '@context' => [
                '@vocab' => 'https://rupkeep.app/schema/tasks/v1#',
                'code' => '@id',
                'labels' => ['@container' => '@set'],
                'comments' => ['@container' => '@list'],
                'createdAt' => ['@type' => 'http://www.w3.org/2001/XMLSchema#dateTime'],
                'updatedAt' => ['@type' => 'http://www.w3.org/2001/XMLSchema#dateTime'],
            ],
            '@type' => 'TaskCollection',
            'schemaVersion' => '1.0',
            'exportedAt' => now()->toIso8601String(),
            'exportedBy' => 'tasks:bootstrap-from-markdown',
            'tasks' => array_map(fn ($t) => ['@type' => 'Task'] + $t, $tasks),
            'labels' => $labels,
        ];
    }
}
