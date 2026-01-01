<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;

class SetupController extends Controller
{
    public function index(Request $request): View
    {
        if (! config('setup-console.enabled')) {
            abort(404);
        }

        $authorized = Session::get('setup_console.authorized', false);

        return view('admin.setup', [
            'authorized' => $authorized,
            'lastOutput' => Session::get('setup_console.last_output'),
            'lastStatus' => Session::get('setup_console.last_status'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        if (! config('setup-console.enabled')) {
            abort(404);
        }

        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $expectedUser = config('setup-console.username');
        $expectedPass = config('setup-console.password');

        if ($expectedPass === null) {
            return back()->withErrors([
                'password' => __('Setup console password is not configured.'),
            ])->onlyInput('username');
        }

        $usernameMatches = hash_equals($expectedUser ?? '', $request->input('username'));
        $passwordMatches = hash_equals($expectedPass ?? '', $request->input('password'));

        if ($usernameMatches && $passwordMatches) {
            Session::put('setup_console.authorized', true);

            return redirect()->route('setup.index')
                ->with('success', __('Setup console unlocked.'));
        }

        return back()->withErrors([
            'password' => __('Invalid setup credentials.'),
        ])->onlyInput('username');
    }

    public function logout(Request $request): RedirectResponse
    {
        Session::forget('setup_console');

        return redirect()->route('setup.index')
            ->with('success', __('Setup console locked.'));
    }

    public function run(Request $request): RedirectResponse
    {
        if (! Session::get('setup_console.authorized')) {
            abort(403);
        }

        $request->validate([
            'action' => ['required', 'string', 'in:db-reset'],
        ]);

        $status = 'failed';
        $output = '';

        if ($request->input('action') === 'db-reset') {
            try {
                Artisan::call('db:reset');
                $output = Artisan::output();
                $status = 'success';
            } catch (\Throwable $throwable) {
                $output = $throwable->getMessage();
                report($throwable);
            }
        }

        Session::put('setup_console.last_output', $output);
        Session::put('setup_console.last_status', $status);

        return redirect()->route('setup.index')
            ->with($status === 'success' ? 'success' : 'error', __('Setup command completed with :status', ['status' => $status]));
    }
}
