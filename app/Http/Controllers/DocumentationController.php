<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DocumentationController extends Controller
{
    public function index()
    {
        return view('documentation.index');
    }

    public function show($document = 'onboarding')
    {
        $availableDocuments = [
            'onboarding' => 'onboarding',
        ];

        $documentName = $availableDocuments[$document] ?? 'onboarding';

        return view("documentation.{$documentName}", [
            'document' => $documentName,
        ]);
    }
}
