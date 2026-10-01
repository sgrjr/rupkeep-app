<?php

namespace App\Http\Controllers;

use App\Support\PublicOrganization;
use Illuminate\Support\Str;

/**
 * The Terms of Service and Privacy Policy pages (TASK-475). Jetstream's stubs
 * ("Edit this file to define...") sat unrouted in resources/markdown; these
 * render the real documents from the same files, with the operator's name
 * and contact details filled in from the organization the site speaks for.
 */
class LegalController extends Controller
{
    public function terms()
    {
        return $this->render('terms', __('Terms of Service'));
    }

    public function policy()
    {
        return $this->render('policy', __('Privacy Policy'));
    }

    private function render(string $file, string $title)
    {
        $organization = PublicOrganization::resolve();

        $markdown = file_get_contents(resource_path("markdown/{$file}.md"));

        $markdown = str_replace(
            ['{{company}}', '{{email}}', '{{phone}}', '{{address}}', '{{app}}', '{{url}}'],
            [
                $organization?->name ?: config('app.name'),
                $organization?->email ?: '',
                $organization?->telephone ?: '',
                trim(implode(', ', array_filter([$organization?->street, $organization?->city, trim(($organization?->state ?? '').' '.($organization?->zip ?? ''))]))),
                config('app.name'),
                config('app.url'),
            ],
            $markdown
        );

        return view('legal.show', [
            'title' => $title,
            'company' => $organization?->name ?: config('app.name'),
            'html' => Str::markdown($markdown),
        ]);
    }
}
