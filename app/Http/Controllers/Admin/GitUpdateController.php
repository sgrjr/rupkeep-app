<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class GitUpdateController extends Controller
{
    /**
     * Pull the latest code from GitHub.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user && $user->is_super, 403);

        $process = Process::fromShellCommandline('git pull', base_path());
        $process->run();

        $status = $process->isSuccessful() ? 'success' : 'failed';
        $output = trim($process->getOutput() ?: $process->getErrorOutput());

        $redirect = back()
            ->with('git-update-status', $status)
            ->with('git-update-output', $output);

        return $status === 'success'
            ? $redirect->with('success', __('Server updated from GitHub.'))
            : $redirect->with('error', __('Failed to update server from GitHub. Check the output for details.'));
    }
}
