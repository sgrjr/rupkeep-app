<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserLog;
use App\Models\CustomerContact;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserLogsController extends Controller
{

    use AuthorizesRequests;

    public function delete(Request $request, $log){
        return $this->destroy($request, $log);
    }
    public function destroy(Request $request, $log){
        // Use withTrashed to find even soft-deleted logs (though we shouldn't be deleting already deleted logs)
        $log = UserLog::withTrashed()->find($log);

        if($log && !$log->trashed() && $this->authorize('delete', $log)){
           // This will now soft delete since UserLog uses SoftDeletes trait
           $log->delete();
        }

        return back();
    }

    public function restore(Request $request, $log){
        $log = UserLog::withTrashed()->find($log);

        if($log && $this->authorize('restore', $log)){
           $log->restore();
        }

        return back();
    }

    public function forceDelete(Request $request, $log){

        $log = UserLog::withTrashed()->find($log);

        if($log && $this->authorize('delete', $log)){
           $log->forceDelete();
        }

        return back();
    }
}
