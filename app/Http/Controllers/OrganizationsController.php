<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Jetstream;

class OrganizationsController extends Controller
{
    use AuthorizesRequests;

    /**
     * The columns a form may set. user_id and deleted_at are fillable on the
     * model but are never accepted from a request (TASK-432): the owner goes
     * through the updateOwner policy and deletion through delete().
     */
    private const RULES = [
        'name' => ['required', 'string', 'max:255'],
        'primary_contact' => ['nullable', 'string', 'max:255'],
        'telephone' => ['nullable', 'string', 'max:50'],
        'fax' => ['nullable', 'string', 'max:50'],
        'email' => ['nullable', 'email', 'max:255'],
        'street' => ['nullable', 'string', 'max:255'],
        'city' => ['nullable', 'string', 'max:255'],
        'state' => ['nullable', 'string', 'max:50'],
        'zip' => ['nullable', 'string', 'max:20'],
        'logo_url' => ['nullable', 'string', 'max:2048'],
        'website_url' => ['nullable', 'string', 'max:2048'],
        'owner_email' => ['nullable', 'email', 'max:255'],
    ];

    public function store(Request $request)
    {
        $this->authorize('create', Organization::class);

        $input = $request->validate(self::RULES);

        // An unknown owner email falls back to the super user rather than
        // failing, so a new org is never left ownerless.
        $owner = null;
        if (! empty($input['owner_email'])) {
            $owner = User::where('email', $input['owner_email'])->first();
        }
        $owner ??= User::superUser();

        $organization = Organization::create(
            array_merge($this->attributes($input), ['user_id' => $owner->id])
        );

        return redirect()->route('organizations.index')
            ->with('message', __(':name created.', ['name' => $organization->name]));
    }

    public function update(Request $request, $organization)
    {
        $organization = Organization::findOrFail($organization);

        $this->authorize('update', $organization);

        $input = $request->validate(self::RULES);

        $attributes = $this->attributes($input);

        // Changing the owner is a separate, super-only permission. An org
        // admin editing their own org keeps whatever owner it has.
        if (! empty($input['owner_email']) && $input['owner_email'] !== $organization->owner?->email) {
            $this->authorize('updateOwner', $organization);

            $owner = User::where('email', $input['owner_email'])->first();

            if (! $owner) {
                return back()->withErrors(['owner_email' => __('No user has that email address.')])->withInput();
            }

            $attributes['user_id'] = $owner->id;
        }

        $organization->update($attributes);

        return redirect()->route('organizations.index')
            ->with('message', __(':name updated.', ['name' => $organization->name]));
    }

    public function delete(Request $request, $organization)
    {
        $organization = Organization::findOrFail($organization);

        $this->authorize('delete', $organization);

        $organization->delete();

        return redirect()->route('organizations.index');
    }

    public function createUser(Request $request, $organization)
    {
        $organization = Organization::findOrFail($organization);

        $this->authorize('createUser', $organization);

        Validator::make($request->except('_method'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        $organization->createUser($request->except('_method'));

        return back();
    }

    /**
     * The validated organization columns, minus the owner email which is
     * resolved separately.
     *
     * @return array<string, mixed>
     */
    private function attributes(array $input): array
    {
        unset($input['owner_email']);

        return $input;
    }
}
