<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class AttachmentsController extends Controller
{
    use AuthorizesRequests;

    /**
     * Serve the file from the private disk under the name the user gave it.
     * `location` used to be read as a raw filesystem path, which was right
     * for rows the job page wrote and wrong for rows the log page wrote
     * (TASK-454).
     */
    public function download(Request $request, Attachment $attachment)
    {
        $this->authorize('download', $attachment);

        abort_unless($attachment->fileExists(), 404, __('This file is no longer in storage.'));

        return $attachment->download();
    }

    /**
     * Removing an attachment is final: nothing in the UI restores one, so the
     * row is force-deleted and the model event takes the file with it.
     */
    public function delete(Request $request, Attachment $attachment)
    {
        $this->authorize('delete', $attachment);

        $name = $attachment->file_name;

        $attachment->forceDelete();

        return back()->with('success', __(':name deleted.', ['name' => $name]));
    }
}
