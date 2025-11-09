@extends('layouts.guest')

@section('content')
    @include('invoices.templates.styles')

    <div class="invoice-doc invoice-doc--portal">
        <div>
            <div class="invoice-portal-actions">
                <button type="button" class="invoice-print-button" onclick="window.print()">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4.5A1.5 1.5 0 0 1 7.5 3h9A1.5 1.5 0 0 1 18 4.5V9M6 11.25H5.25A1.5 1.5 0 0 0 3.75 12.75v3A1.5 1.5 0 0 0 5.25 17.25H6V15A1.5 1.5 0 0 1 7.5 13.5h9A1.5 1.5 0 0 1 18 15v2.25h.75a1.5 1.5 0 0 0 1.5-1.5v-3a1.5 1.5 0 0 0-1.5-1.5H18M6 20.25h12M9 9h6" />
                    </svg>
                    {{ __('Print invoice') }}
                </button>
            </div>

            @include('invoices.templates.render', [
                'invoice' => $invoice,
                'values' => $values,
                'isSummary' => true,
            ])

            @if($attachments->isNotEmpty())
                <div class="invoice-attachments">
                    <div class="invoice-attachments__header">
                        <h2>{{ __('Proof Materials') }}</h2>
                        <p>{{ __('Files shared by the pilot car team for your records.') }}</p>
                    </div>
                    <div class="invoice-attachments__grid">
                        @foreach($attachments as $attachment)
                            <div class="invoice-attachments__item">
                                <div class="invoice-attachments__label">
                                    <span class="invoice-attachments__icon">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9m0 7.5-3-3m3 3 3-3m3 7.5H6a2.25 2.25 0 0 1-2.25-2.25V6.75A2.25 2.25 0 0 1 6 4.5h3.375a2.25 2.25 0 0 1 1.591.659l1.5 1.5A2.25 2.25 0 0 0 13.058 7.5H18a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25Z" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p class="invoice-attachments__name">{{ $attachment->file_name }}</p>
                                        <p class="invoice-attachments__meta">{{ __('Uploaded :date', ['date' => optional($attachment->created_at)->format('M j, Y')]) }}</p>
                                    </div>
                                </div>
                                <a class="invoice-attachments__action" download href="{{ route('attachments.download', ['attachment' => $attachment->id]) }}">
                                    {{ __('Download') }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="invoice-support-message">
                <p>{{ __('Need clarification or an updated invoice? Reply to the email that delivered this invoice or contact your Casco Bay Pilot Car coordinator.') }}</p>
            </div>

            <div class="invoice-portal-footer">
                <a href="{{ route('customer.invoices.index') }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    <span>{{ __('Back to invoices') }}</span>
                </a>
                <span>{{ __('Casco Bay Pilot Car • Customer Portal') }}</span>
            </div>
        </div>
    </div>
@endsection
