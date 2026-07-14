<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Organization;
use Laravel\Fortify\Features;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Laravel\Jetstream\Http\Controllers\Inertia\Concerns\ConfirmsTwoFactorAuthentication;
use Laravel\Jetstream\Agent;
use App\Actions\SendUserNotification;
use App\Models\Invoice;
use App\Models\JobInvoice;
use App\Models\PilotCarJob;

class UserProfile extends Component
{
    use ConfirmsTwoFactorAuthentication;
    public $profile;

    public Int $merged_user;
    
    public $notificationTestStatus = null;
    public $notificationTestMessage = '';
    public $notificationTesting = false;

    // Which test notification to send. Falls back to the standard test for any
    // unrecognized value (see buildTestNotification()).
    public $notificationTestType = 'default';

    public function mount(Int $user){
        $this->profile = User::with('organization')->find($user);

        $this->authorize('update', $this->profile);
    }
    public function render(Request $request)
    {
       $confirmsTwoFactorAuthentication = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');

       if($confirmsTwoFactorAuthentication){
        $this->validateTwoFactorAuthenticationState($request);
       }
       $sessions = $this->sessions($request)->all();

        return view('livewire.user-profile', compact('sessions','confirmsTwoFactorAuthentication'));
    }

    public function sessions(Request $request)
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return collect(
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                    ->where('user_id', $request->user()->getAuthIdentifier())
                    ->orderBy('last_activity', 'desc')
                    ->get()
        )->map(function ($session) use ($request) {
            $agent = $this->createAgent($session);

            return (object) [
                'agent' => [
                    'is_desktop' => $agent->isDesktop(),
                    'platform' => $agent->platform(),
                    'browser' => $agent->browser(),
                ],
                'ip_address' => $session->ip_address,
                'is_current_device' => $session->id === $request->session()->getId(),
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ];
        });
    }

    protected function createAgent($session)
    {
        return tap(new Agent(), fn ($agent) => $agent->setUserAgent($session->user_agent));
    }

    public function testNotification(){
        $this->notificationTesting = true;
        $this->notificationTestStatus = null;
        $this->notificationTestMessage = '';

        try {

            $this->profile->notify(new \App\Notifications\JobUpdate());

            // Check if user has a valid recipient address
            $recipient = $this->profile->getSmsGatewayAddress() ?? $this->profile->email;

            if (empty($recipient)) {
                $this->notificationTestStatus = 'error';
                $this->notificationTestMessage = 'No valid recipient address found. Please set an email address or notification address in your profile.';
                return;
            }

            // Build the message + subject for the selected test type, falling
            // back to the standard test notification for unknown values.
            [$message, $subject, $label] = $this->buildTestNotification($this->notificationTestType);

            SendUserNotification::to($this->profile, $message, subject: $subject);

            $this->notificationTestStatus = 'success';
            $notificationType = $this->profile->getSmsGatewayAddress() ? 'SMS' : 'email';
            $this->notificationTestMessage = "{$label} sent successfully to {$recipient} via {$notificationType}!";
        } catch (\Exception $e) {
            $this->notificationTestStatus = 'error';
            $this->notificationTestMessage = 'Failed to send test notification: ' . $e->getMessage();
        } finally {
            $this->notificationTesting = false;
        }
    }

    /**
     * Build the [message, subject, humanLabel] tuple for a given test-notification
     * type. Unknown types fall back to the standard test notification.
     */
    protected function buildTestNotification(string $type): array
    {
        if ($type === 'job_assigned') {
            // A realistic but entirely FAKE "job assigned" SMS, mirroring the
            // format sent from ShowPilotCarJob::assign(). The job id is
            // intentionally nonexistent — this only exercises the text output
            // and how iOS renders/linkifies the URL, not a real job.
            $fakeJobId = 999999;
            $pickupAt = now()->addDay()->setTime(8, 0)->format('n/j/Y g:i A');
            $url = rtrim(config('app.url'), '/') . '/my/jobs/' . $fakeJobId;

            $message = "New job assignment [Lead car] for Acme Logistics (TEST). "
                . "Job NO. TEST-1001 | Load NO. 7. "
                . "Pickup: 100 Commercial St, Portland, ME @{$pickupAt} "
                . "This is a FAKE test notification — no real job exists. "
                . "For updates {$url}";

            return [$message, 'New Job 7 (TEST)', 'FAKE "Job Assigned" test notification'];
        }

        // default: standard test notification
        $message = 'This is a test notification from ' . $this->profile->organization?->name . '.';

        return [$message, 'test', 'Test notification'];
    }
    
    public function clearNotificationTestStatus(){
        $this->notificationTestStatus = null;
        $this->notificationTestMessage = '';
    }

    public function mergeToUser(){
        if(!empty($this->merged_user)){
            $user = User::find($this->merged_user);

            if($user && $this->profile->organization_id === $user->organization_id){
                \App\Models\UserLog::where('car_driver_id', $this->profile->id)->update(['car_driver_id'=>$user->id]);
                \App\Models\Vehicle::where('user_id', $this->profile->id)->update(['user_id'=>$user->id]);
                $this->profile->delete();

                return redirect()->route('user.profile', ['user'=> $user->id]);
            }else{
                echo 'ERROR. I could not find that user(s)!';
            }
        }

    }

    /**
     * Clear all invoices for the organization (super users only).
     */
    public function clearInvoices()
    {
        if (!auth()->user()->is_super) {
            session()->flash('error', __('Only super users can clear all invoices.'));
            return;
        }

        $organizationId = $this->profile->organization_id;
        $count = Invoice::where('organization_id', $organizationId)->count();

        // Delete pivot entries first
        $jobIds = PilotCarJob::where('organization_id', $organizationId)->pluck('id');
        JobInvoice::whereIn('pilot_car_job_id', $jobIds)->delete();

        // Then delete all invoices
        Invoice::where('organization_id', $organizationId)->forceDelete();

        session()->flash('success', __(':count invoices deleted.', ['count' => $count]));
    }
}
