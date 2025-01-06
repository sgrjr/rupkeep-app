<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Dashboard;
use App\Livewire\OrganizationsIndex;
use App\Livewire\OrganizationCreate;
use App\Livewire\OrganizationShow;
use App\Livewire\OrganizationEdit;
use App\Livewire\UserProfile;
use App\Livewire\MyUserProfile;
use App\Livewire\CreatePilotCarJob;
use App\Livewire\ShowPilotCarJob;
use App\Livewire\EditPilotCarJob;
use App\Livewire\EditUserLog;
use App\Http\Controllers\OrganizationsController;
use App\Http\Controllers\CustomersController;
use App\Http\Controllers\CustomerContactsController;
use App\Http\Controllers\MyUsersController;
use App\Http\Controllers\MyCustomersController;
use App\Http\Controllers\MyVehiclesController;
use App\Http\Controllers\MyJobsController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\UserLogsController;
use App\Http\Controllers\AttachmentsController;
use App\Http\Controllers\MyInvoicesController;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    Route::get('/dashboard/{component?}', Dashboard::class)->name('dashboard');

    Route::get('/organizations', OrganizationsIndex::class)->name('organizations.index');
    Route::get('/organizations/create', OrganizationCreate::class)->name('organizations.create');
    Route::get('/organizations/{organization}', OrganizationShow::class)->name('organizations.show');
    Route::delete('/organizations/{organization}',[OrganizationsController::class, 'delete'])->name('organizations.delete');
    Route::post('/organizations/{organization}/user',[OrganizationsController::class, 'createUser'])->name('organization.user.create');
    Route::get('/organizations/{organization}/edit', OrganizationEdit::class)->name('organizations.edit');
    Route::post('/organization', [OrganizationsController::class, 'store'])->name('organizations.store');
    Route::patch('/organization/{organization}', [OrganizationsController::class, 'update'])->name('organizations.update');
    Route::get('/users/{user}/profile', UserProfile::class)->name('user.profile');
    Route::post('/users/{user}/restore', [UsersController::class, 'restore'])->name('user.restore');
    Route::delete('/users/{user}', [UsersController::class, 'delete'])->name('user.delete');

    Route::resource('/customers', CustomersController::class)->names('customers');
    Route::resource('/customers/{customer}/contacts', CustomerContactsController::class)->names('customer.contacts');

    Route::resource('/my/customers', MyCustomersController::class)->names('my.customers');
    Route::resource('/my/users', MyUsersController::class)->names('my.users');
    Route::resource('/my/vehicles', MyVehiclesController::class)->names('my.vehicles');
    Route::resource('/my/jobs', MyJobsController::class)->names('my.jobs');
    Route::get('/my/jobs/create', CreatePilotCarJob::class)->name('my.jobs.create');
    Route::get('/my/jobs/{job}', ShowPilotCarJob::class)->name('my.jobs.show');
    Route::get('/my/jobs/{job}/edit', EditPilotCarJob::class)->name('my.jobs.edit');
    Route::get('/my/profile', MyUserProfile::class)->name('my.profile');
    Route::get('jobs/{job}', ShowPilotCarJob::class)->name('jobs.show');
    Route::get('jobs', [JobsController::class, 'index'])->name('jobs.index');
    Route::get('logs/{log}', EditUserLog::class)->name('logs.edit');
    Route::delete('logs/{log}',[UserLogsController::class, 'delete'])->name('logs.destroy');

    Route::get('attachments/{attachment}', [AttachmentsController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentsController::class, 'delete'])->name('attachments.destroy');

    Route::post('my/invoices/create', [MyInvoicesController::class, 'store'])->name('my.invoices.store');
});

Route::middleware([
    'web'
])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });    

});

Route::get('/impersonate/{user}', [MyUsersController::class, 'impersonate'])->name('impersonate');


