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
use App\Http\Controllers\EmailController;

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
    Route::get('my/invoices/{invoice}/edit', [MyInvoicesController::class, 'edit'])->name('my.invoices.edit');
    Route::put('my/invoices/{invoice}', [MyInvoicesController::class, 'update'])->name('my.invoices.update');
});

Route::middleware([
    'web'
])->group(function () {
    Route::get('/', function () {
        return view('cbpc');
    })->name('home');    

});

Route::get('/impersonate/{user}', [MyUsersController::class, 'impersonate'])->name('impersonate');

Route::get('/send-email', [EmailController::class, 'redirectToAuthUrl']);
Route::get('/email-callback', [EmailController::class, 'handleCallback']);
Route::get('/send-html-email', [EmailController::class, 'sendHtmlEmail']);

Route::get('brevo', function(){
    $api_key = config('mail.mailers.brevo.key');

    // Configure API key authorization: api-key
    $config = \Brevo\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', $api_key);
    // Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
    // $config = Brevo\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('api-key', 'Bearer');
    // Configure API key authorization: partner-key
    //$config = \Brevo\Client\Configuration::getDefaultConfiguration()->setApiKey('partner-key', 'YOUR_API_KEY');
    // Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
    // $config = Brevo\Client\Configuration::getDefaultConfiguration()->setApiKeyPrefix('partner-key', 'Bearer');

    $apiInstance = new \Brevo\Client\Api\AccountApi(
        // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
        // This is optional, `GuzzleHttp\Client` will be used as default.
        new \GuzzleHttp\Client(),
        $config
    );

    try {
        $result = $apiInstance->getAccount();
        print_r($result);
    } catch (\Exception $e) {
        echo 'Exception when calling AccountApi->getAccount: ', $e->getMessage(), PHP_EOL;
    }
});