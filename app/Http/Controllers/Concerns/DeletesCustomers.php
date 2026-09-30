<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Customer;
use App\Services\CustomerArchive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Two controllers serve customer deletion (the resource controller behind
 * the create/edit forms and MyCustomersController behind the list), so the
 * one implementation lives here (TASK-461).
 */
trait DeletesCustomers
{
    protected function destroyCustomer(Request $request, $customerId, CustomerArchive $archive): RedirectResponse
    {
        $customer = Customer::find($customerId);

        if (! $customer) {
            return redirect()->route('customers.index')
                ->with('error', __('That customer no longer exists.'));
        }

        $this->authorize('delete', $customer);

        // The two-step confirmation is in the delete form; the server insists
        // on seeing it, so a stray or replayed request deletes nothing.
        if (! $request->boolean('confirmed')) {
            return redirect()->route('customers.index')
                ->with('error', __('Deleting :name needs confirmation. Nothing was deleted.', ['name' => $customer->name]));
        }

        $name = $customer->name;

        try {
            $path = $archive->archiveAndDelete($customer, $request->user());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('customers.index')
                ->with('error', __('The archive for :name could not be written, so nothing was deleted.', ['name' => $name]));
        }

        return redirect()->route('customers.index')
            ->with('success', __(':name deleted. The archive file is ready to download.', ['name' => $name]))
            ->with('customer_archive', ['name' => $name, 'file' => basename($path)]);
    }

    /**
     * Serve one archive file. The customer is gone, so the file itself says
     * which organization it belonged to.
     */
    public function downloadArchive(Request $request, string $file, CustomerArchive $archive)
    {
        abort_unless((bool) preg_match(CustomerArchive::FILE_PATTERN, $file), 404);

        $user = $request->user();
        abort_unless($user->isSuper() || $user->isAdmin(), 403);

        $path = CustomerArchive::DIRECTORY . '/' . $file;
        $disk = Storage::disk(CustomerArchive::DISK);

        abort_unless($disk->exists($path), 404, __('That archive is no longer on the server.'));

        $organizationId = $archive->organizationIdOf($path);
        abort_unless($user->isSuper() || ($organizationId !== null && $organizationId === (int) $user->organization_id), 403);

        return $disk->download($path, 'customer-archive-' . $file, ['Content-Type' => 'application/json']);
    }
}
