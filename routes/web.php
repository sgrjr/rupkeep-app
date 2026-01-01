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
use App\Http\Controllers\VehicleMaintenanceController;
use App\Http\Controllers\MyJobsController;
use App\Http\Controllers\JobsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\UserLogsController;
use App\Http\Controllers\AttachmentsController;
use App\Http\Controllers\MyInvoicesController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\QuickBooksExportController;
use App\Http\Controllers\Admin\GitUpdateController;
use App\Http\Controllers\AdminToolsController;
use App\Http\Controllers\UserEventController;
use App\Http\Controllers\MyReportsController;
use App\Livewire\ManagePricing;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    Route::get('/dashboard/{component?}', Dashboard::class)->name('dashboard');
    Route::post('/admin/git-update', GitUpdateController::class)->name('admin.git-update');
    Route::post('/admin/tools/update-from-git', [AdminToolsController::class, 'updateFromGit'])->name('admin.tools.update_from_git');
    
    // Server Management (super admin only)
    Route::get('/admin/server-management', \App\Livewire\ServerManagement::class)
        ->name('admin.server-management')
        ->middleware('super');
    
    Route::post('/admin/tools/execute-command', [AdminToolsController::class, 'executeCommand'])
        ->name('admin.tools.execute-command')
        ->middleware('super');
    
    Route::post('/admin/tools/execute-workflow', [AdminToolsController::class, 'executeWorkflow'])
        ->name('admin.tools.execute-workflow')
        ->middleware('super');
    
    // Pricing Management (organization-scoped)
    Route::get('/my/pricing', ManagePricing::class)->name('my.pricing.index');

    // Experience Tracker (super users only)
    Route::resource('user-events', UserEventController::class)->only(['index', 'show']);

    Route::get('/organizations', OrganizationsIndex::class)->name('organizations.index');
    Route::get('/organizations/create', OrganizationCreate::class)->name('organizations.create');
    Route::get('/organizations/onboard', \App\Livewire\OnboardingWizard::class)->name('organizations.onboard');
    Route::get('/organizations/{organization}', OrganizationShow::class)->name('organizations.show');
    Route::delete('/organizations/{organization}',[OrganizationsController::class, 'delete'])->name('organizations.delete');
    Route::post('/organizations/{organization}/user',[OrganizationsController::class, 'createUser'])->name('organization.user.create');
    Route::get('/organizations/{organization}/edit', OrganizationEdit::class)->name('organizations.edit');
    Route::post('/organization', [OrganizationsController::class, 'store'])->name('organizations.store');
    Route::patch('/organization/{organization}', [OrganizationsController::class, 'update'])->name('organizations.update');
    Route::get('/users/{user}/profile', UserProfile::class)->name('user.profile');
    Route::post('/users/{user}/restore', [UsersController::class, 'restore'])->name('user.restore');
    Route::delete('/users/{user}', [UsersController::class, 'delete'])->name('user.delete');

    Route::resource('/customers', CustomersController::class);
    Route::resource('/customers/{customer}/contacts', CustomerContactsController::class);

    // Customers routes - explicitly defined to ensure all routes are registered
    Route::get('/my/customers', [MyCustomersController::class, 'index'])->name('my.customers.index');
    Route::get('/my/customers/create', [MyCustomersController::class, 'create'])->name('my.customers.create');
    Route::post('/my/customers', [MyCustomersController::class, 'store'])->name('my.customers.store');
    Route::get('/my/customers/{customer}', [MyCustomersController::class, 'show'])->name('my.customers.show');
    Route::get('/my/customers/{customer}/edit', [MyCustomersController::class, 'edit'])->name('my.customers.edit');
    Route::put('/my/customers/{customer}', [MyCustomersController::class, 'update'])->name('my.customers.update');
    Route::delete('/my/customers/{customer}', [MyCustomersController::class, 'destroy'])->name('my.customers.destroy');
    
    // Users routes - explicitly defined to ensure all routes are registered
    Route::get('/my/users', [MyUsersController::class, 'index'])->name('my.users.index');
    Route::get('/my/users/create', [MyUsersController::class, 'create'])->name('my.users.create');
    Route::post('/my/users', [MyUsersController::class, 'store'])->name('my.users.store');
    Route::get('/my/users/{user}', [MyUsersController::class, 'show'])->name('my.users.show');
    Route::get('/my/users/{user}/edit', [MyUsersController::class, 'edit'])->name('my.users.edit');
    Route::put('/my/users/{user}', [MyUsersController::class, 'update'])->name('my.users.update');
    Route::delete('/my/users/{user}', [MyUsersController::class, 'destroy'])->name('my.users.destroy');
    
    // Vehicles routes - explicitly defined to ensure all routes are registered
    Route::get('/my/vehicles', [MyVehiclesController::class, 'index'])->name('my.vehicles.index');
    Route::get('/my/vehicles/create', [MyVehiclesController::class, 'create'])->name('my.vehicles.create');
    Route::post('/my/vehicles', [MyVehiclesController::class, 'store'])->name('my.vehicles.store');
    Route::get('/my/vehicles/{vehicle}', [MyVehiclesController::class, 'show'])->name('my.vehicles.show');
    Route::get('/my/vehicles/{vehicle}/edit', [MyVehiclesController::class, 'edit'])->name('my.vehicles.edit');
    Route::put('/my/vehicles/{vehicle}', [MyVehiclesController::class, 'update'])->name('my.vehicles.update');
    Route::delete('/my/vehicles/{vehicle}', [MyVehiclesController::class, 'destroy'])->name('my.vehicles.destroy');
    Route::put('/my/vehicles/{vehicle}/restore', [MyVehiclesController::class, 'restore'])->name('my.vehicles.restore');
    Route::delete('/my/vehicles/{vehicle}/force', [MyVehiclesController::class, 'forceDestroy'])->name('my.vehicles.force-destroy');
    Route::post('/my/vehicles/{vehicle}/maintenance', [VehicleMaintenanceController::class, 'store'])->name('my.vehicles.maintenance.store');
    
    // Jobs routes - using resource for index, store, update, destroy, but Livewire components for create, show, edit
    Route::get('/my/jobs', [MyJobsController::class, 'index'])->name('my.jobs.index');
    Route::post('/my/jobs', [MyJobsController::class, 'store'])->name('my.jobs.store');
    Route::put('/my/jobs/{job}', [MyJobsController::class, 'update'])->name('my.jobs.update');
    Route::delete('/my/jobs/{job}', [MyJobsController::class, 'destroy'])->name('my.jobs.destroy');
    Route::put('/my/jobs/{job}/restore', [MyJobsController::class, 'restore'])->name('my.jobs.restore');
    Route::get('/my/jobs/create', CreatePilotCarJob::class)->name('my.jobs.create');
    Route::get('/my/jobs/{job}', ShowPilotCarJob::class)->name('my.jobs.show');
    Route::get('/my/jobs/{job}/edit', EditPilotCarJob::class)->name('my.jobs.edit');
    Route::get('/my/profile', MyUserProfile::class)->name('my.profile');
    Route::get('/feedback', function () {
        return view('feedback.index');
    })->name('feedback.index');
    
    Route::get('/documentation', [\App\Http\Controllers\DocumentationController::class, 'index'])->name('documentation.index');
    Route::get('/documentation/{document}', [\App\Http\Controllers\DocumentationController::class, 'show'])->name('documentation.show');
    Route::get('jobs/{job}', ShowPilotCarJob::class)->name('jobs.show');
    Route::get('jobs', [JobsController::class, 'index'])->name('jobs.index');
    Route::get('logs/{log}', EditUserLog::class)->name('logs.edit');
    Route::delete('logs/{log}',[UserLogsController::class, 'delete'])->name('logs.destroy');

    Route::get('attachments/{attachment}', [AttachmentsController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentsController::class, 'delete'])->name('attachments.destroy');

    Route::post('my/invoices/create', [MyInvoicesController::class, 'store'])->name('my.invoices.store');
    Route::get('my/invoices/{invoice}/edit', [MyInvoicesController::class, 'edit'])->name('my.invoices.edit');
    Route::put('my/invoices/{invoice}', [MyInvoicesController::class, 'update'])->name('my.invoices.update');
    Route::post('my/invoices/{invoice}/apply-late-fees', [MyInvoicesController::class, 'applyLateFees'])->name('my.invoices.apply-late-fees');
    Route::post('my/invoices/{invoice}/toggle-marked-for-attention', [MyInvoicesController::class, 'toggleMarkedForAttention'])->name('my.invoices.toggle-marked-for-attention');
    Route::get('my/invoices/export/quickbooks', QuickBooksExportController::class)->name('my.invoices.export.quickbooks');
    Route::get('my/invoices/{invoice}/print', [MyInvoicesController::class, 'print'])->name('my.invoices.print');
    Route::get('my/invoices/{invoice}/pdf', [MyInvoicesController::class, 'pdf'])->name('my.invoices.pdf');

    // Reports
    Route::get('my/reports', [MyReportsController::class, 'index'])->name('my.reports.index');
    Route::get('my/reports/annual-vehicle-report', [MyReportsController::class, 'annualVehicleReport'])->name('my.reports.annual-vehicle-report');
});

Route::middleware([
    'web'
])->group(function () {
    Route::get('/', function () {
        return view('cbpc');
    })->name('home');    

    Route::middleware('guest')->group(function () {
        Route::get('/login-code', [\App\Http\Controllers\Auth\LoginCodeController::class, 'create'])->name('login-code.create');
        Route::post('/login-code', [\App\Http\Controllers\Auth\LoginCodeController::class, 'store'])->name('login-code.store');
        Route::get('/login-code/verify', [\App\Http\Controllers\Auth\LoginCodeController::class, 'verifyForm'])->name('login-code.verify-form');
        Route::post('/login-code/verify', [\App\Http\Controllers\Auth\LoginCodeController::class, 'verify'])->name('login-code.verify');
    });

    Route::prefix('portal')->group(function () {
        Route::get('invoices', [\App\Http\Controllers\CustomerPortal\InvoiceController::class, 'index'])->name('customer.invoices.index');
        Route::get('invoices/{invoice}', [\App\Http\Controllers\CustomerPortal\InvoiceController::class, 'show'])->name('customer.invoices.show');
    });

    Route::prefix('setup')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\SetupController::class, 'index'])->name('setup.index');
        Route::post('/login', [\App\Http\Controllers\Admin\SetupController::class, 'login'])->name('setup.login');
        Route::post('/run', [\App\Http\Controllers\Admin\SetupController::class, 'run'])->name('setup.run');
        Route::post('/logout', [\App\Http\Controllers\Admin\SetupController::class, 'logout'])->name('setup.logout');
    });
});

Route::get('/impersonate/{user}', [MyUsersController::class, 'impersonate'])->name('impersonate');

Route::get('/send-email', [EmailController::class, 'redirectToAuthUrl'])->name('send-email');
Route::get('/email-callback', [EmailController::class, 'handleCallback'])->name('email-callback');
Route::get('/send-html-email', [EmailController::class, 'sendHtmlEmail'])->name('send-html-email');

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