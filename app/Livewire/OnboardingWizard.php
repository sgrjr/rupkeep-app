<?php

namespace App\Livewire;

use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\PilotCarJob;
use App\Actions\SendUserNotification;
use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OnboardingWizard extends Component
{
    use WithFileUploads;

    public $currentStep = 1;
    public $organization = null;
    public $organizationId = null;

    // Step 1: Organization
    #[Validate('required|string|max:255')]
    public $org_name = '';
    #[Validate('required|string|max:255')]
    public $org_primary_contact = '';
    #[Validate('nullable|string|max:255')]
    public $org_telephone = '';
    #[Validate('nullable|string|max:255')]
    public $org_fax = '';
    #[Validate('nullable|email|max:255')]
    public $org_email = '';
    #[Validate('nullable|string|max:255')]
    public $org_street = '';
    #[Validate('nullable|string|max:255')]
    public $org_city = '';
    #[Validate('nullable|string|max:255')]
    public $org_state = '';
    #[Validate('nullable|string|max:255')]
    public $org_zip = '';
    #[Validate('nullable|string|max:255')]
    public $org_logo_url = '';
    #[Validate('nullable|string|max:255')]
    public $org_website_url = '';
    #[Validate('nullable|email|max:255')]
    public $org_owner_email = '';

    // Step 2: CSV Import
    #[Validate('nullable|file|mimes:csv,txt|max:10240')]
    public $csv_file = null;
    public $csv_imported = false;

    // Step 3: Users
    public $users = [];
    public $new_user_name = '';
    public $new_user_email = '';
    public $new_user_password = '';
    public $new_user_password_confirmation = '';
    public $new_user_role = User::ROLE_EMPLOYEE_STANDARD;

    // Step 4: Vehicles
    public $vehicles = [];
    public $new_vehicle_name = '';
    public $new_vehicle_odometer = '';

    // Step 5: Preferences (placeholder for future)
    public $preferences = [];

    // Step 6: Welcome Emails
    public $selected_users_for_email = [];
    public $email_sent = false;

    public function mount()
    {
        $user = Auth::user();
        if (! $user || ! $user->is_super) {
            abort(403, 'Only super users can access onboarding.');
        }
    }

    public function nextStep()
    {
        if ($this->currentStep === 1) {
            $this->validate([
                'org_name' => 'required|string|max:255',
                'org_primary_contact' => 'required|string|max:255',
            ]);
            $this->createOrganization();
        }

        if ($this->currentStep < 6) {
            $this->currentStep++;
        }
    }

    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function createOrganization()
    {
        $owner = null;
        if ($this->org_owner_email) {
            $owner = User::where('email', $this->org_owner_email)->first();
            if (! $owner) {
                $owner = User::superUser();
            }
        } else {
            $owner = User::superUser();
        }

        $this->organization = Organization::create([
            'name' => $this->org_name,
            'primary_contact' => $this->org_primary_contact,
            'telephone' => $this->org_telephone,
            'fax' => $this->org_fax,
            'email' => $this->org_email,
            'street' => $this->org_street,
            'city' => $this->org_city,
            'state' => $this->org_state,
            'zip' => $this->org_zip,
            'logo_url' => $this->org_logo_url,
            'website_url' => $this->org_website_url,
            'user_id' => $owner->id,
        ]);

        $this->organizationId = $this->organization->id;
    }

    public function importCsv()
    {
        if (! $this->csv_file || ! $this->organizationId) {
            return;
        }

        $originalName = $this->csv_file->getClientOriginalName();
        $this->csv_file->storeAs(path: 'jobs/org_'.$this->organizationId, name: $originalName);

        $files = [[
            'full_path' => $this->csv_file->getPathName(),
            'original_name' => $originalName,
        ]];

        PilotCarJob::import($files, $this->organizationId);
        $this->csv_imported = true;
        $this->csv_file = null;

        session()->flash('message', __('CSV imported successfully.'));
    }

    public function addUser()
    {
        $this->validate([
            'new_user_name' => 'required|string|max:255',
            'new_user_email' => 'required|email|max:255|unique:users,email',
            'new_user_password' => 'required|string|min:6|confirmed',
            'new_user_role' => 'required|string|in:'.implode(',', [User::ROLE_ADMIN, User::ROLE_EMPLOYEE_MANAGER, User::ROLE_EMPLOYEE_STANDARD, User::ROLE_CUSTOMER]),
        ], [], [
            'new_user_name' => 'name',
            'new_user_email' => 'email',
            'new_user_password' => 'password',
        ]);

        if (! $this->organizationId) {
            session()->flash('error', __('Please create the organization first.'));
            return;
        }

        $user = User::create([
            'name' => $this->new_user_name,
            'email' => $this->new_user_email,
            'password' => Hash::make($this->new_user_password),
            'organization_id' => $this->organizationId,
            'organization_role' => $this->new_user_role,
        ]);

        $this->users[] = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->organization_role,
        ];

        $this->new_user_name = '';
        $this->new_user_email = '';
        $this->new_user_password = '';
        $this->new_user_password_confirmation = '';
        $this->resetValidation();
    }

    public function removeUser($index)
    {
        if (isset($this->users[$index])) {
            $user = User::find($this->users[$index]['id']);
            if ($user) {
                $user->delete();
            }
            unset($this->users[$index]);
            $this->users = array_values($this->users);
        }
    }

    public function addVehicle()
    {
        $this->validate([
            'new_vehicle_name' => 'required|string|max:255',
            'new_vehicle_odometer' => 'nullable|integer|min:0',
        ], [], [
            'new_vehicle_name' => 'name',
            'new_vehicle_odometer' => 'odometer',
        ]);

        if (! $this->organizationId) {
            session()->flash('error', __('Please create the organization first.'));
            return;
        }

        $vehicle = Vehicle::create([
            'name' => $this->new_vehicle_name,
            'organization_id' => $this->organizationId,
            'odometer' => $this->new_vehicle_odometer ? (int) $this->new_vehicle_odometer : null,
            'odometer_updated_at' => $this->new_vehicle_odometer ? now() : null,
        ]);

        $this->vehicles[] = [
            'id' => $vehicle->id,
            'name' => $vehicle->name,
            'odometer' => $vehicle->odometer,
        ];

        $this->new_vehicle_name = '';
        $this->new_vehicle_odometer = '';
        $this->resetValidation();
    }

    public function removeVehicle($index)
    {
        if (isset($this->vehicles[$index])) {
            $vehicle = Vehicle::find($this->vehicles[$index]['id']);
            if ($vehicle) {
                $vehicle->delete();
            }
            unset($this->vehicles[$index]);
            $this->vehicles = array_values($this->vehicles);
        }
    }

    public function sendWelcomeEmails()
    {
        if (empty($this->selected_users_for_email) || ! $this->organizationId) {
            return;
        }

        $organization = Organization::find($this->organizationId);
        $users = User::whereIn('id', $this->selected_users_for_email)
            ->where('organization_id', $this->organizationId)
            ->get();

        $features = $this->getAvailableFeatures();

        foreach ($users as $user) {
            $message = $this->buildWelcomeMessage($user, $organization, $features);
            $subject = __('Welcome to :app', ['app' => config('app.name')]);

            try {
                Mail::to($user->email)->send(new \App\Mail\UserNotification($message, $subject));
            } catch (\Exception $e) {
                report($e);
            }
        }

        $this->email_sent = true;
        session()->flash('message', __('Welcome emails sent to :count users.', ['count' => $users->count()]));
    }

    protected function getAvailableFeatures()
    {
        return [
            __('Job Management') => __('Create, view, and manage pilot car jobs. Track job status, assign drivers, and view job history.'),
            __('Log Entry') => __('Submit job completion logs with mileage, expenses, and proof materials. Mobile-optimized for easy entry on the go.'),
            __('Invoicing') => __('Generate invoices from completed jobs. View invoice history, track payments, and export data for accounting.'),
            __('Customer Portal') => __('Customers can view their invoices, add comments, and flag invoices for attention.'),
            __('Vehicle Management') => __('Track vehicle assignments, maintenance schedules, and service history.'),
            __('User Management') => __('Managers can create and manage team members with role-based permissions.'),
        ];
    }

    protected function buildWelcomeMessage($user, $organization, $features)
    {
        $roleLabel = User::ROLE_LABELS[$user->organization_role] ?? $user->organization_role;
        $message = "Welcome to ".config('app.name')."!\n\n";
        $message .= "Your account has been created for ".$organization->name." with the role: ".$roleLabel.".\n\n";
        $message .= "Available Features:\n\n";

        foreach ($features as $feature => $description) {
            $message .= "• ".$feature.": ".$description."\n";
        }

        $message .= "\nYou can log in at: ".route('login')."\n";
        $message .= "Your email: ".$user->email."\n\n";
        $message .= "If you have any questions, please contact your organization administrator.\n\n";
        $message .= "Thank you!";

        return $message;
    }

    public function complete()
    {
        return redirect()->route('organizations.show', ['organization' => $this->organizationId])
            ->with('success', __('Onboarding completed successfully!'));
    }

    public function render()
    {
        $organization = $this->organizationId ? Organization::find($this->organizationId) : null;
        $allUsers = $this->organizationId ? User::where('organization_id', $this->organizationId)->get() : collect();
        $roles = User::roles();

        return view('livewire.onboarding-wizard', compact('organization', 'allUsers', 'roles'));
    }
}

