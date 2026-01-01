<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-white">{{ __('Getting Started Guide') }}</h2>
                <p class="text-sm text-white/85">{{ __('Complete onboarding guide for Rupkeep Pilot Car Management System') }}</p>
            </div>
            <a href="{{ route('documentation.index') }}" 
               class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white/85 transition hover:bg-white/25">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H5m7-7l-7 7 7 7"/>
                </svg>
                {{ __('Back to Documentation') }}
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <!-- Introduction -->
        <section class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow-sm">
            <h1 class="text-4xl font-bold text-slate-900">{{ __('Welcome to Rupkeep') }}</h1>
            <p class="mt-4 text-lg text-slate-600">
                {{ __('This guide will help you get started with the Rupkeep Pilot Car Management System. We\'ve designed this system to replace your Google Sheets workflow with a comprehensive, user-friendly platform for managing jobs, logs, invoices, and customer relationships.') }}
            </p>
            <div class="mt-6 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                <p class="text-slate-700">
                    <strong>{{ __('Public Pricing Page:') }}</strong> {{ __('We\'ve created a public pricing page that displays your service rates, charges, cancellation policies, and payment terms. This marketing page is accessible to both guests and authenticated users, making it easy for potential customers to review your pricing.') }}
                    <a href="{{ route('pricing') }}" target="_blank" class="text-orange-600 hover:text-orange-700 underline font-semibold ml-1">{{ __('View Pricing Page →') }}</a>
                </p>
            </div>
        </section>

        <!-- Customer Concerns & Feature Requests -->
        <section class="rounded-3xl border border-orange-200 bg-gradient-to-br from-orange-50 to-orange-100/50 p-8 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('Addressing Your Concerns & Feature Requests') }}</h2>
            <p class="mt-2 text-slate-600">{{ __('We\'ve built this system based on your specific needs and requirements. Here\'s how we\'ve addressed each of your concerns:') }}</p>

            <div class="mt-6 space-y-6">
                <div class="rounded-2xl border border-orange-200 bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('Customer Portal Authentication') }}
                    </h3>
                    <p class="mt-2 text-slate-600">
                        {{ __('We\'ve implemented a unified authentication system that uses login codes instead of traditional email/password. Customers receive one-time login codes that are valid for 24 hours (configurable). This provides secure access without requiring customers to manage passwords.') }}
                    </p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Login codes are generated on-demand and sent to customers') }}</li>
                        <li>{{ __('Codes expire after 24 hours (configurable)') }}</li>
                        <li>{{ __('Customers can view all their invoices, comment on them, and flag for attention') }}</li>
                        <li>{{ __('All customer access is tracked and auditable') }}</li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-orange-200 bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('Invoice Generation & Printing') }}
                    </h3>
                    <p class="mt-2 text-slate-600">
                        {{ __('Invoices are generated as print-optimized HTML that works perfectly with your browser\'s print function. This approach ensures:') }}
                    </p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Professional, clean invoice layout') }}</li>
                        <li>{{ __('No additional software required (works with any browser)') }}</li>
                        <li>{{ __('Print to PDF directly from browser') }}</li>
                        <li>{{ __('Consistent formatting across all invoices') }}</li>
                        <li>{{ __('Future-ready: PDF library integration can be added later if needed') }}</li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-orange-200 bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('Driver Notifications') }}
                    </h3>
                    <p class="mt-2 text-slate-600">
                        {{ __('We\'ve implemented email notifications using Laravel\'s robust Events and Notifications pattern. This ensures:') }}
                    </p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Drivers receive email notifications when jobs are assigned') }}</li>
                        <li>{{ __('Customers receive emails when invoices are ready') }}</li>
                        <li>{{ __('Flexible architecture allows SMS integration in the future') }}</li>
                        <li>{{ __('All notifications are queued for reliable delivery') }}</li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-orange-200 bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('QuickBooks Integration') }}
                    </h3>
                    <p class="mt-2 text-slate-600">
                        {{ __('We\'ve implemented CSV export functionality that generates files in QuickBooks-compatible format. This allows you to:') }}
                    </p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Export invoice data to CSV format') }}</li>
                        <li>{{ __('Import directly into QuickBooks') }}</li>
                        <li>{{ __('Maintain all financial data in sync') }}</li>
                        <li>{{ __('Export by date range or customer') }}</li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-orange-200 bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('Proof Materials & Document Visibility') }}
                    </h3>
                    <p class="mt-2 text-slate-600">
                        {{ __('We\'ve implemented a flexible proof materials system that gives you complete control over what customers can see:') }}
                    </p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('All logs and attachments are private by default (staff-only)') }}</li>
                        <li>{{ __('Staff can mark specific documents as "public" for customer viewing') }}</li>
                        <li>{{ __('Business policy can evolve over time - you control visibility') }}</li>
                        <li>{{ __('Customers can see invoices and any materials you mark as public') }}</li>
                        <li>{{ __('Complete audit trail of what customers have accessed') }}</li>
                    </ul>
                </div>

                <div class="rounded-2xl border border-orange-200 bg-white p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('Role-Based Permissions') }}
                    </h3>
                    <p class="mt-2 text-slate-600">
                        {{ __('We\'ve implemented a whitelist-based permission system with five distinct roles:') }}
                    </p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li><strong>{{ __('Guest/Unauthenticated User') }}:</strong> {{ __('Limited public access') }}</li>
                        <li><strong>{{ __('Employee: Standard') }}:</strong> {{ __('Can work on assigned jobs, view own logs') }}</li>
                        <li><strong>{{ __('Employee: Manager') }}:</strong> {{ __('Can create jobs, manage customers, generate invoices') }}</li>
                        <li><strong>{{ __('Customer') }}:</strong> {{ __('Can view own invoices, comment, flag for attention') }}</li>
                        <li><strong>{{ __('Admin/Super User') }}:</strong> {{ __('Full system access, organization management') }}</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Data Import/Export -->
        <section class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('Data Import & Export') }}</h2>

            <div class="mt-6 space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Importing Data from Google Sheets') }}</h3>
                    <p class="mt-2 text-slate-600">{{ __('To migrate your existing data from Google Sheets:') }}</p>
                    <ol class="mt-4 space-y-3 text-sm text-slate-600 list-decimal list-inside">
                        <li>{{ __('Export your Google Sheets data as a CSV file') }}</li>
                        <li>{{ __('Navigate to your Organization settings page') }}</li>
                        <li>{{ __('Find the "Upload Data from CSV" section') }}</li>
                        <li>{{ __('Click "Choose File" and select your CSV export') }}</li>
                        <li>{{ __('Click "Upload Data File"') }}</li>
                        <li>{{ __('The system will automatically:') }}
                            <ul class="mt-2 ml-6 space-y-1 list-disc">
                                <li>{{ __('Map column headers to the correct fields') }}</li>
                                <li>{{ __('Create or update customers') }}</li>
                                <li>{{ __('Create or update drivers (users)') }}</li>
                                <li>{{ __('Create or update vehicles') }}</li>
                                <li>{{ __('Create jobs and associated logs') }}</li>
                            </ul>
                        </li>
                    </ol>
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                        <p class="text-sm font-semibold text-amber-900">{{ __('Note:') }}</p>
                        <p class="mt-1 text-sm text-amber-800">{{ __('The CSV import automatically maps common column names. If your headers don\'t match exactly, the system will attempt to match them intelligently. After import, verify your data and make any necessary corrections.') }}</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Exporting Data') }}</h3>
                    <p class="mt-2 text-slate-600">{{ __('Export functionality is available for:') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li><strong>{{ __('QuickBooks CSV Export') }}:</strong> {{ __('Export invoice data in QuickBooks-compatible format from the Invoices section') }}</li>
                        <li><strong>{{ __('Reports') }}:</strong> {{ __('Generate various reports including annual vehicle reports, job summaries, and more') }}</li>
                    </ul>
                    <p class="mt-4 text-sm text-slate-600">
                        {{ __('To export data for QuickBooks:') }}
                    </p>
                    <ol class="mt-2 space-y-2 text-sm text-slate-600 list-decimal list-inside">
                        <li>{{ __('Navigate to the Invoices section') }}</li>
                        <li>{{ __('Use filters to select the invoices you want to export') }}</li>
                        <li>{{ __('Click the "Export to CSV" or "QuickBooks Export" button') }}</li>
                        <li>{{ __('Download the generated CSV file') }}</li>
                        <li>{{ __('Import the CSV into QuickBooks') }}</li>
                    </ol>
                </div>
            </div>
        </section>

        <!-- Application Sections -->
        <section class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('Application Sections & Features') }}</h2>
            <p class="mt-2 text-slate-600">{{ __('The application is organized into several main sections, each serving a specific purpose in your workflow.') }}</p>

            <div class="mt-6 space-y-6">
                <!-- Dashboard -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                        </svg>
                        {{ __('Dashboard') }}
                    </h3>
                    <p class="mt-2 text-slate-600">{{ __('Your central command center. The dashboard provides:') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Quick overview cards showing counts of Jobs, Customers, Users, and Vehicles') }}</li>
                        <li>{{ __('Manager statistics: active jobs, completed jobs, cancelled jobs, and invoice summaries') }}</li>
                        <li>{{ __('Recent jobs and jobs marked for attention') }}</li>
                        <li>{{ __('Driver view: jobs assigned to you with quick access to log entry') }}</li>
                        <li>{{ __('Quick navigation to all major sections') }}</li>
                    </ul>
                </div>

                <!-- Jobs -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h69.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H77.25m0 0l-3 3m3-3l-3-3"/>
                        </svg>
                        {{ __('Jobs') }}
                    </h3>
                    <p class="mt-2 text-slate-600">{{ __('Manage all your pilot car assignments. Features include:') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Create new jobs with customer, schedule, and rate information') }}</li>
                        <li>{{ __('Edit job details (pickup/delivery addresses, times, rates)') }}</li>
                        <li>{{ __('View job details including all associated logs and invoices') }}</li>
                        <li>{{ __('Search and filter jobs by customer, job number, load number, invoice number, addresses, and status') }}</li>
                        <li>{{ __('Assign default drivers and truck driver contacts to jobs') }}</li>
                        <li>{{ __('Track job status: Active, Completed, Cancelled') }}</li>
                        <li>{{ __('Public memo field for notes that appear on invoices') }}</li>
                        <li>{{ __('Internal memo field for staff-only notes') }}</li>
                    </ul>
                </div>

                <!-- Logs -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h69.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H77.25m0 0l-3 3m3-3l-3-3"/>
                        </svg>
                        {{ __('Logs (Driver Work Records)') }}
                    </h3>
                    <p class="mt-2 text-slate-600">{{ __('Drivers submit logs to record their work on each job. Logs capture:') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Driver and vehicle assignment') }}</li>
                        <li>{{ __('Mileage tracking (start, end, job start, job end)') }}</li>
                        <li>{{ __('Truck and trailer information') }}</li>
                        <li>{{ __('Expenses: tolls, gas, hotel, extra charges') }}</li>
                        <li>{{ __('Wait time hours and reason') }}</li>
                        <li>{{ __('Extra load stops count') }}</li>
                        <li>{{ __('Deadhead leg tracking') }}</li>
                        <li>{{ __('Pre-trip check confirmation') }}</li>
                        <li>{{ __('Load cancellation status') }}</li>
                        <li>{{ __('Internal memo (staff-only, not on invoices)') }}</li>
                        <li>{{ __('Maintenance memo for vehicle concerns') }}</li>
                        <li>{{ __('File attachments (receipts, photos, documents)') }}</li>
                    </ul>
                    <p class="mt-4 text-sm font-semibold text-slate-700">{{ __('Mobile-Optimized:') }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ __('The log entry form is designed mobile-first, making it easy for drivers to complete logs on their phones while on the road.') }}</p>
                </div>

                <!-- Invoices -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        </svg>
                        {{ __('Invoices') }}
                    </h3>
                    <p class="mt-2 text-slate-600">{{ __('Generate, manage, and track invoices. Key features:') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Generate invoices from completed jobs') }}</li>
                        <li>{{ __('Automatic calculation of:') }}
                            <ul class="mt-1 ml-6 space-y-1 list-disc">
                                <li>{{ __('Billable miles and rates') }}</li>
                                <li>{{ __('Wait time charges') }}</li>
                                <li>{{ __('Extra load stops') }}</li>
                                <li>{{ __('Deadhead charges') }}</li>
                                <li>{{ __('Tolls and expenses') }}</li>
                                <li>{{ __('Mini charges (for jobs ≤125 miles)') }}</li>
                                <li>{{ __('Late fees (if applicable)') }}</li>
                            </ul>
                        </li>
                        <li>{{ __('Edit invoice details and override calculated values') }}</li>
                        <li>{{ __('Print-optimized invoice view') }}</li>
                        <li>{{ __('Payment tracking: record partial payments, full payments, and overpayments') }}</li>
                        <li>{{ __('Account credit management: apply customer credits and track overpayments') }}</li>
                        <li>{{ __('Late fee application for past-due invoices') }}</li>
                        <li>{{ __('Summary invoices: combine multiple jobs into a single invoice') }}</li>
                        <li>{{ __('Invoice comments and flagging for attention') }}</li>
                        <li>{{ __('Email invoices directly to customers') }}</li>
                    </ul>
                </div>

                <!-- Customers -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        {{ __('Customers') }}
                    </h3>
                    <p class="mt-2 text-slate-600">{{ __('Manage customer relationships and information:') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Create and edit customer records') }}</li>
                        <li>{{ __('Store customer addresses and contact information') }}</li>
                        <li>{{ __('Manage customer contacts (truck drivers, dispatchers, etc.)') }}</li>
                        <li>{{ __('Mark main contacts and billing contacts') }}</li>
                        <li>{{ __('View customer job history and invoices') }}</li>
                        <li>{{ __('Track customer account credit (from overpayments)') }}</li>
                        <li>{{ __('View all invoices for a specific customer') }}</li>
                    </ul>
                </div>

                <!-- Users -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                        </svg>
                        {{ __('Users (Drivers & Staff)') }}
                    </h3>
                    <p class="mt-2 text-slate-600">{{ __('Manage your team members and their access:') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Create user accounts for drivers and staff') }}</li>
                        <li>{{ __('Assign roles: Standard Employee, Manager, Admin') }}</li>
                        <li>{{ __('Set organization-specific permissions') }}</li>
                        <li>{{ __('View user activity and job assignments') }}</li>
                        <li>{{ __('Manage user profiles and preferences') }}</li>
                    </ul>
                </div>

                <!-- Vehicles -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H21.375m-19.5 0h.008v.008H1.875V11.25h9.75v.008H12.75v-.008h.008V11.25h3.375v.008H16.5v-.008h.375m-1.125 0v-.75m0 0h.375M21 9.75V8.625c0-.621-.504-1.125-1.125-1.125H18.75c-.621 0-1.125.504-1.125 1.125v1.125m0 0v.75m0 0H21m-3.75 0v-.75m0 .75h.375"/>
                        </svg>
                        {{ __('Vehicles') }}
                    </h3>
                    <p class="mt-2 text-slate-600">{{ __('Track your fleet:') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Register vehicles in your fleet') }}</li>
                        <li>{{ __('Track vehicle assignments to jobs') }}</li>
                        <li>{{ __('Monitor odometer readings') }}</li>
                        <li>{{ __('Vehicle maintenance tracking (future feature)') }}</li>
                    </ul>
                </div>

                <!-- Pricing -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <svg class="h-5 w-5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ __('Pricing Settings') }}
                    </h3>
                    <p class="mt-2 text-slate-600">{{ __('Configure your organization\'s pricing structure:') }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Set per-mile rates') }}</li>
                        <li>{{ __('Configure flat rates (with or without expenses)') }}</li>
                        <li>{{ __('Set wait time rates') }}</li>
                        <li>{{ __('Configure extra stop charges') }}</li>
                        <li>{{ __('Set deadhead charges') }}</li>
                        <li>{{ __('Configure mini charge thresholds and amounts') }}</li>
                        <li>{{ __('Set payment terms and late fee percentages') }}</li>
                        <li>{{ __('Organization-specific overrides (different rates per organization)') }}</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Job Lifecycle -->
        <section class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('Complete Job Lifecycle: From Creation to Completion') }}</h2>
            <p class="mt-2 text-slate-600">{{ __('Understanding the complete workflow will help you use the system effectively. Here\'s how a job flows from start to finish:') }}</p>

            <div class="mt-6 space-y-6">
                <!-- Step 1 -->
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white font-bold">1</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Job Creation') }}</h3>
                            <p class="mt-2 text-slate-600">{{ __('A manager or admin creates a new job by:') }}</p>
                            <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                                <li>{{ __('Selecting or creating a customer') }}</li>
                                <li>{{ __('Entering job number and load number') }}</li>
                                <li>{{ __('Setting scheduled pickup and delivery dates/times') }}</li>
                                <li>{{ __('Entering pickup and delivery addresses') }}</li>
                                <li>{{ __('Selecting a rate code (per-mile, flat rate, etc.)') }}</li>
                                <li>{{ __('Optionally setting a rate value override') }}</li>
                                <li>{{ __('Assigning default driver and truck driver contact') }}</li>
                                <li>{{ __('Adding public memo (will appear on invoice) or internal memo (staff-only)') }}</li>
                            </ul>
                            <p class="mt-4 text-sm font-semibold text-slate-700">{{ __('Result:') }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Job is created with status "ACTIVE" and appears in the jobs list. The assigned driver receives an email notification (if configured).') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="rounded-2xl border border-blue-200 bg-blue-50/60 p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white font-bold">2</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Job Assignment & Preparation') }}</h3>
                            <p class="mt-2 text-slate-600">{{ __('Before the job begins:') }}</p>
                            <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                                <li>{{ __('Manager can edit job details if schedule changes') }}</li>
                                <li>{{ __('Driver can view assigned jobs from their dashboard') }}</li>
                                <li>{{ __('Vehicle can be assigned to the job') }}</li>
                                <li>{{ __('Job details are accessible to all authorized staff') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white font-bold">3</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Job Execution - Log Entry') }}</h3>
                            <p class="mt-2 text-slate-600">{{ __('During and after the job, the driver creates a log entry:') }}</p>
                            <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                                <li>{{ __('Driver navigates to the job from their dashboard or jobs list') }}</li>
                                <li>{{ __('Clicks "Create Log" or "Add Log Entry"') }}</li>
                                <li>{{ __('Fills out the mobile-optimized log form with:') }}
                                    <ul class="mt-2 ml-6 space-y-1 list-disc">
                                        <li>{{ __('Start and end mileage (personal and job-specific)') }}</li>
                                        <li>{{ __('Vehicle and driver information') }}</li>
                                        <li>{{ __('Truck driver and vehicle details') }}</li>
                                        <li>{{ __('Expenses: tolls, gas, hotel, extra charges') }}</li>
                                        <li>{{ __('Wait time hours and reason') }}</li>
                                        <li>{{ __('Extra load stops') }}</li>
                                        <li>{{ __('Deadhead legs') }}</li>
                                        <li>{{ __('Pre-trip check confirmation') }}</li>
                                        <li>{{ __('Load cancellation status (if applicable)') }}</li>
                                        <li>{{ __('Internal memo (staff-only notes)') }}</li>
                                        <li>{{ __('Maintenance memo (vehicle concerns)') }}</li>
                                        <li>{{ __('File attachments (receipts, photos)') }}</li>
                                    </ul>
                                </li>
                                <li>{{ __('Saves the log entry') }}</li>
                            </ul>
                            <p class="mt-4 text-sm font-semibold text-slate-700">{{ __('Result:') }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Log is saved and associated with the job. The job now has work records that can be used for invoicing.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="rounded-2xl border border-purple-200 bg-purple-50/60 p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-500 text-white font-bold">4</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Invoice Generation') }}</h3>
                            <p class="mt-2 text-slate-600">{{ __('Once the job is complete and logs are entered, generate an invoice:') }}</p>
                            <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                                <li>{{ __('Navigate to the job detail page') }}</li>
                                <li>{{ __('Click "Generate Invoice" button') }}</li>
                                <li>{{ __('System automatically:') }}
                                    <ul class="mt-2 ml-6 space-y-1 list-disc">
                                        <li>{{ __('Calculates total billable miles from all logs') }}</li>
                                        <li>{{ __('Applies the job\'s rate code and rate value') }}</li>
                                        <li>{{ __('Calculates wait time charges') }}</li>
                                        <li>{{ __('Calculates extra stop charges') }}</li>
                                        <li>{{ __('Calculates deadhead charges') }}</li>
                                        <li>{{ __('Sums all expenses (tolls, gas, hotel, extra charges)') }}</li>
                                        <li>{{ __('Applies mini charge if billable miles ≤125') }}</li>
                                        <li>{{ __('Calculates total due') }}</li>
                                        <li>{{ __('Uses job\'s public memo for invoice notes') }}</li>
                                    </ul>
                                </li>
                                <li>{{ __('Invoice is created and linked to the job') }}</li>
                            </ul>
                            <p class="mt-4 text-sm font-semibold text-slate-700">{{ __('Result:') }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Invoice is generated with all calculated values. Manager can now review and edit the invoice before sending to customer.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Step 5 -->
                <div class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-500 text-white font-bold">5</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Invoice Review & Editing') }}</h3>
                            <p class="mt-2 text-slate-600">{{ __('Manager reviews and can adjust the invoice:') }}</p>
                            <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                                <li>{{ __('Navigate to invoice edit page') }}</li>
                                <li>{{ __('Review all calculated values') }}</li>
                                <li>{{ __('Override any values if needed (billable miles, rates, charges)') }}</li>
                                <li>{{ __('Override invoice notes (public memo) if different from job memo') }}</li>
                                <li>{{ __('View internal log memos (staff-only, not printed)') }}</li>
                                <li>{{ __('Customize invoice appearance (logo, footer message)') }}</li>
                                <li>{{ __('Add invoice comments or flags') }}</li>
                            </ul>
                            <p class="mt-4 text-sm font-semibold text-slate-700">{{ __('Result:') }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Invoice is ready to send to customer with accurate billing information.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Step 6 -->
                <div class="rounded-2xl border border-pink-200 bg-pink-50/60 p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-pink-500 text-white font-bold">6</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Invoice Delivery') }}</h3>
                            <p class="mt-2 text-slate-600">{{ __('Send invoice to customer:') }}</p>
                            <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                                <li>{{ __('Click "Email Invoice" button on invoice page') }}</li>
                                <li>{{ __('System sends email with invoice link to customer') }}</li>
                                <li>{{ __('Customer receives login code to access invoice portal') }}</li>
                                <li>{{ __('Customer can view invoice, download/print, comment, and flag for attention') }}</li>
                            </ul>
                            <p class="mt-4 text-sm font-semibold text-slate-700">{{ __('Result:') }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Customer has access to view and interact with their invoice.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Step 7 -->
                <div class="rounded-2xl border border-rose-200 bg-rose-50/60 p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-500 text-white font-bold">7</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Payment Tracking') }}</h3>
                            <p class="mt-2 text-slate-600">{{ __('Record payments as they come in:') }}</p>
                            <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                                <li>{{ __('Navigate to invoice edit page') }}</li>
                                <li>{{ __('Click "Record Payment" button') }}</li>
                                <li>{{ __('Enter payment amount, date, method, check number (if applicable)') }}</li>
                                <li>{{ __('Optionally apply customer account credit') }}</li>
                                <li>{{ __('System automatically:') }}
                                    <ul class="mt-2 ml-6 space-y-1 list-disc">
                                        <li>{{ __('Calculates remaining balance') }}</li>
                                        <li>{{ __('Updates invoice status if paid in full') }}</li>
                                        <li>{{ __('Handles overpayments (adds to customer credit)') }}</li>
                                        <li>{{ __('Maintains payment history') }}</li>
                                    </ul>
                                </li>
                            </ul>
                            <p class="mt-4 text-sm font-semibold text-slate-700">{{ __('Result:') }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Payment is recorded, balance is updated, and invoice status reflects current payment state.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Step 8 -->
                <div class="rounded-2xl border border-teal-200 bg-teal-50/60 p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-teal-500 text-white font-bold">8</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Late Fees (If Applicable)') }}</h3>
                            <p class="mt-2 text-slate-600">{{ __('If payment is not received within the grace period:') }}</p>
                            <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                                <li>{{ __('System calculates late fees based on payment terms') }}</li>
                                <li>{{ __('Manager can review calculated late fees on invoice page') }}</li>
                                <li>{{ __('Click "Apply Late Fees" to add fees to invoice total') }}</li>
                                <li>{{ __('Invoice total is updated with late fees included') }}</li>
                                <li>{{ __('Invoice can be resent to customer with updated total') }}</li>
                            </ul>
                            <p class="mt-4 text-sm font-semibold text-slate-700">{{ __('Result:') }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Late fees are applied and invoice reflects the new total due.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Step 9 -->
                <div class="rounded-2xl border border-cyan-200 bg-cyan-50/60 p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-cyan-500 text-white font-bold">9</div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-slate-900">{{ __('Job Completion') }}</h3>
                            <p class="mt-2 text-slate-600">{{ __('Once invoice is paid and job is fully complete:') }}</p>
                            <ul class="mt-3 space-y-2 text-sm text-slate-600 list-disc list-inside">
                                <li>{{ __('Job status can be marked as "COMPLETED"') }}</li>
                                <li>{{ __('All logs are finalized') }}</li>
                                <li>{{ __('All invoices are paid') }}</li>
                                <li>{{ __('Job appears in completed jobs list') }}</li>
                                <li>{{ __('Job data is preserved for historical reference') }}</li>
                            </ul>
                            <p class="mt-4 text-sm font-semibold text-slate-700">{{ __('Result:') }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ __('Job lifecycle is complete. All data is preserved and can be referenced for reporting, customer service, or accounting purposes.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Additional Features -->
        <section class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('Additional Features & Capabilities') }}</h2>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Summary Invoices') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ __('Combine multiple jobs into a single summary invoice for customers with multiple jobs in a billing period.') }}</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Invoice Comments') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ __('Staff and customers can add comments to invoices for communication and clarification.') }}</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Flag for Attention') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ __('Customers and staff can flag invoices that need attention, clarification, or changes.') }}</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Soft Delete & Restore') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ __('Jobs, invoices, and other records can be archived (soft deleted) and restored if needed.') }}</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('File Attachments') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ __('Attach receipts, photos, documents, and other files to logs and invoices. Control visibility (staff-only or public for customers).') }}</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-6">
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Search & Filtering') }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ __('Powerful search and filter capabilities across jobs, customers, and invoices to quickly find what you need.') }}</p>
                </div>
            </div>
        </section>

        <!-- Tips & Best Practices -->
        <section class="rounded-3xl border border-slate-200 bg-white/90 p-8 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('Tips & Best Practices') }}</h2>

            <div class="mt-6 space-y-4">
                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                    <h3 class="font-semibold text-slate-900">{{ __('For Managers') }}</h3>
                    <ul class="mt-2 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Set default drivers and truck driver contacts on jobs to speed up log entry') }}</li>
                        <li>{{ __('Use public memos on jobs for notes that should appear on invoices') }}</li>
                        <li>{{ __('Use internal memos for staff-only notes that should never appear on invoices') }}</li>
                        <li>{{ __('Review invoices before sending - you can always override calculated values') }}</li>
                        <li>{{ __('Use summary invoices for customers with multiple jobs in a period') }}</li>
                        <li>{{ __('Regularly check the dashboard for jobs marked for attention') }}</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                    <h3 class="font-semibold text-slate-900">{{ __('For Drivers') }}</h3>
                    <ul class="mt-2 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Complete logs as soon as possible after finishing a job') }}</li>
                        <li>{{ __('Attach receipts for expenses (tolls, gas, hotel) when available') }}</li>
                        <li>{{ __('Use the internal memo field for any notes about the job (these won\'t appear on invoices)') }}</li>
                        <li>{{ __('Double-check mileage entries - these directly affect billing') }}</li>
                        <li>{{ __('Mark pre-trip checks to ensure vehicle safety compliance') }}</li>
                    </ul>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                    <h3 class="font-semibold text-slate-900">{{ __('Data Management') }}</h3>
                    <ul class="mt-2 space-y-2 text-sm text-slate-600 list-disc list-inside">
                        <li>{{ __('Export data regularly for backup purposes') }}</li>
                        <li>{{ __('Use QuickBooks export monthly or as needed for accounting') }}</li>
                        <li>{{ __('Keep customer information up-to-date, especially billing contacts') }}</li>
                        <li>{{ __('Archive completed jobs rather than deleting them (preserves history)') }}</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Support -->
        <section class="rounded-3xl border border-orange-200 bg-gradient-to-br from-orange-50 to-orange-100/50 p-8 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('Need Help?') }}</h2>
            <p class="mt-2 text-slate-600">{{ __('If you have questions or need assistance:') }}</p>
            <ul class="mt-4 space-y-2 text-slate-600 list-disc list-inside">
                <li>{{ __('Use the feedback form in the application to report issues or request features') }}</li>
                <li>{{ __('Contact Stephen Reynolds Jr. for access or permission questions') }}</li>
                <li>{{ __('Refer back to this documentation anytime by visiting the Documentation section') }}</li>
            </ul>
        </section>
    </div>
</x-app-layout>
