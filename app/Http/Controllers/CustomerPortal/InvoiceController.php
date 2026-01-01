<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return $this->promptForAuthentication($request, [
                'title' => __('Sign in to view your invoices'),
                'message' => __('Access your billing history using your employee login credentials or request a one-time code sent to your email.'),
            ]);
        }

        if (! $user->isCustomer() && ! $user->isAdmin() && ! $user->is_super) {
            return $this->accessDenied($request, [
                'title' => __('Customer portal access required'),
                'message' => __('This portal is designed for customer accounts using one-time login codes. Sign out and sign back in with a customer link, or head back to your dashboard.'),
                'primaryAction' => [
                    'href' => Route::has('dashboard') ? route('dashboard') : url('/'),
                    'label' => __('Return to dashboard'),
                ],
                'secondaryActions' => [
                    [
                        'href' => route('login-code.create', ['redirect' => $request->fullUrl()]),
                        'label' => __('Request a customer login code'),
                    ],
                ],
                'showLogout' => true,
            ]);
        }

        $query = Invoice::query()
            ->with(['customer', 'job'])
            ->where('customer_id', $user->customer_id ?? null);

        // Filter by payment status
        if ($request->has('status')) {
            if ($request->status === 'paid') {
                $query->where('paid_in_full', true);
            } elseif ($request->status === 'unpaid') {
                $query->where('paid_in_full', false);
            }
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $invoices = $query->latest()->paginate(15)->withQueryString();

        return view('customer.invoices.index', [
            'invoices' => $invoices,
        ]);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $user = $request->user();

        if (! $user) {
            return $this->promptForAuthentication($request, [
                'title' => __('Sign in to open this invoice'),
                'message' => __('Choose the best way to sign in—either enter your password or request a one-time login code. We\'ll bring you right back here.'),
            ]);
        }

        if (! $user->isCustomer() && ! $user->isAdmin() && ! $user->is_super) {
            return $this->accessDenied($request, [
                'title' => __('Customer portal access required'),
                'message' => __('You are currently signed in as a staff member. Sign out and sign back in with a customer link to view invoices in the portal.'),
                'primaryAction' => [
                    'href' => Route::has('dashboard') ? route('dashboard') : url('/'),
                    'label' => __('Return to dashboard'),
                ],
                'secondaryActions' => [
                    [
                        'href' => route('login-code.create', ['redirect' => $request->fullUrl()]),
                        'label' => __('Request a customer login code'),
                    ],
                ],
                'showLogout' => true,
            ]);
        }

        if (! $user->can('view', $invoice)) {
            return $this->accessDenied($request, [
                'title' => __('We couldn\'t open that invoice'),
                'message' => __('That invoice belongs to a different account or is no longer available. Choose another invoice from your list.'),
                'primaryAction' => [
                    'href' => route('customer.invoices.index'),
                    'label' => __('Back to my invoices'),
                ],
                'showLogout' => false,
            ]);
        }

        $invoice->loadMissing(['customer', 'job', 'organization', 'comments.user']);

        $values = is_array($invoice->values) ? $invoice->values : [];

        $view = method_exists($invoice, 'isSummary') && $invoice->isSummary()
            ? 'customer.invoices.summary'
            : 'customer.invoices.single';

        return view($view, [
            'invoice' => $invoice,
            'values' => $values,
            'attachments' => $invoice->publicProofAttachments(),
        ]);
    }

    protected function accessDenied(Request $request, array $data, int $status = 403)
    {
        $defaults = [
            'title' => __('Access restricted'),
            'message' => __('We could not open this section right now.'),
            'primaryAction' => null,
            'secondaryActions' => [],
            'showLogout' => false,
        ];

        $payload = array_merge($defaults, $data);

        if ($payload['showLogout'] ?? false) {
            $payload['logoutRedirect'] = route('logout');
        }

        return response()->view('customer.invoices.access-denied', $payload, $status);
    }

    protected function promptForAuthentication(Request $request, array $data)
    {
        $defaults = [
            'title' => __('Sign in to continue'),
            'message' => __('Sign in with your email and password or request a one-time code.'),
        ];

        $payload = array_merge($defaults, $data);

        $redirectUrl = $request->fullUrl();
        $request->session()->put('url.intended', $redirectUrl);
        $request->session()->put('customer_portal.redirect', $redirectUrl);

        $payload['loginUrl'] = Route::has('login') ? route('login') : url('/login');
        $payload['loginCodeUrl'] = route('login-code.create', ['redirect' => $redirectUrl]);

        return response()->view('customer.invoices.auth-gate', $payload, 200);
    }
}

