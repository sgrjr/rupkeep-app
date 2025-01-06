<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attachment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AttachmentsController extends Controller
{

    use AuthorizesRequests;

    public function download(Request $request, Attachment $attachment){

        if($attachment && $this->authorize('download', $attachment)){
           
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'txt' => 'text/plain',
                'html' => 'text/html',
                'exe' => 'application/octet-stream',
                'zip' => 'application/zip',
                'doc' => 'application/msword',
                'xls' => 'application/vnd.ms-excel',
                'ppt' => 'application/vnd.ms-powerpoint',
                'gif' => 'image/gif',
                'png' => 'image/png',
                'jpeg' => 'image/jpg',
                'jpg' => 'image/jpg',
                'php' => 'text/plain'
            ];
            
            $fileSize = filesize($attachment->location);
            $fileName = rawurldecode($attachment->file_name);
            $fileExt = '';
            
            // Determine MIME Type
            $fileExt = strtolower(substr(strrchr($fileName, '.'), 1));
            
            if(array_key_exists($fileExt, $mimeTypes)) {
                $mimeType = $mimeTypes[$fileExt];
            }
            else {
                $mimeType = 'application/force-download';
            }

           return response()->download($attachment->location, headers:[
            'Content-Description' => 'File Transfer',
            'Content-Type' => $mimeType
           ]);
        }

        return null;
    }

    public function delete(Request $request, Attachment $attachment){
        if($attachment && auth()->user()->can('delete', $attachment)){
           $attachment->deleteFile()->delete();
        }

        return back();
    }
}
