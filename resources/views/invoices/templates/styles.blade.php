<style>
    @page {
        margin: 0;
    }

    :root {
        color-scheme: light;
        font-family: "Segoe UI", Arial, sans-serif;
        --invoice-accent: #f9b104;
        --invoice-border: #d4d4d4;
        --invoice-text: #172232;
        --invoice-muted: #6b7280;
        --invoice-card-bg: #ffffff;
        --invoice-background: #f8fafc;
    }

    .invoice-doc {
        margin: 0;
        padding: 0;
        min-height: 100vh;
        background: var(--invoice-background);
        color: var(--invoice-text);
    }

    .invoice-doc--portal {
        padding: 3rem 1.5rem;
        display: flex;
        justify-content: center;
        background: linear-gradient(180deg, rgba(249, 177, 4, 0.12) 0%, rgba(248, 250, 252, 1) 35%);
    }

    .invoice-doc--portal .page {
        margin-top: 0;
    }

    .invoice-doc--print {
        padding: 0;
    }

    .invoice-doc .page {
        width: min(920px, 100%);
        margin: 2rem auto;
        background: var(--invoice-card-bg);
        border: 1px solid var(--invoice-border);
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.15);
    }

    .invoice-doc header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 2rem;
        padding: 1.25rem 1.5rem;
        background: #ffffff;
        border-bottom: 2px solid var(--invoice-border);
    }

    .invoice-doc header .header-left {
        display: flex;
        align-items: flex-start;
        gap: 1.5rem;
        flex: 1;
    }

    .invoice-doc header .invoice-logo {
        flex-shrink: 0;
    }

    .invoice-doc header .invoice-logo img {
        max-height: 80px;
        max-width: 200px;
        width: auto;
        height: auto;
        object-fit: contain;
    }

    .invoice-doc header .invoice-title {
        flex: 1;
    }

    .invoice-doc header h1 {
        margin: 0 0 0.5rem;
        font-size: 2.2rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--invoice-text);
    }

    .invoice-doc header .company-name-large {
        margin: 0 0 0.25rem;
        font-size: 1.8rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--invoice-text);
        line-height: 1.2;
    }

    .invoice-doc header .company-tagline {
        margin: 0 0 0.75rem;
        font-size: 0.85rem;
        color: var(--invoice-accent);
        font-weight: 500;
    }

    .invoice-doc header .company-info {
        font-size: 0.9rem;
        color: var(--invoice-text);
        line-height: 1.5;
        margin-top: 0.5rem;
    }

    .invoice-doc header .company-info div {
        margin-bottom: 0.15rem;
    }

    .invoice-doc header .invoice-summary-title {
        margin: 0 0 0.5rem;
        font-size: 1.8rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--invoice-text);
        text-align: right;
    }

    .invoice-doc header .summary-id {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--invoice-text);
        margin-bottom: 1rem;
        text-align: right;
    }

    .invoice-doc header .bill-to-section {
        text-align: right;
        margin-top: 1rem;
    }

    .invoice-doc header .bill-to-label {
        margin: 0 0 0.5rem;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-weight: 700;
        color: var(--invoice-muted);
        text-align: right;
    }

    .invoice-doc header .bill-to-info {
        font-size: 0.9rem;
        color: var(--invoice-text);
        line-height: 1.5;
    }

    .invoice-doc header .bill-to-company {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .invoice-doc header .bill-to-contact {
        font-size: 0.9rem;
        color: var(--invoice-text);
        line-height: 1.5;
        margin-bottom: 0.15rem;
    }

    .invoice-doc header .bill-to-address {
        font-size: 0.9rem;
        color: var(--invoice-text);
        line-height: 1.5;
        margin-bottom: 0.15rem;
    }

    .invoice-doc header .organization-info {
        font-size: 0.9rem;
        color: var(--invoice-text);
        line-height: 1.5;
    }

    .invoice-doc header .organization-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--invoice-text);
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }

    .invoice-doc header .organization-contact {
        font-size: 0.9rem;
        color: var(--invoice-text);
        line-height: 1.5;
        margin-bottom: 0.15rem;
    }

    .invoice-doc header .organization-address {
        font-size: 0.9rem;
        color: var(--invoice-text);
        line-height: 1.5;
        margin-bottom: 0.15rem;
    }

    .invoice-doc header .invoice-title-right {
        margin: 0 0 0.5rem;
        font-size: 1.8rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--invoice-text);
        text-align: right;
    }

    .invoice-doc header .invoice-date {
        font-size: 0.9rem;
        color: var(--invoice-text);
        margin-top: 0.25rem;
        text-align: right;
    }

    .invoice-doc tbody td.text-right {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .invoice-doc header .invoice-number {
        font-size: 1rem;
        font-weight: 600;
        color: var(--invoice-text);
        margin-bottom: 0.25rem;
    }

    .invoice-doc header .header-right {
        text-align: right;
        flex-shrink: 0;
    }

    .invoice-doc header .company-name {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--invoice-text);
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }

    .invoice-doc header .company-address {
        font-size: 0.9rem;
        color: var(--invoice-text);
        line-height: 1.5;
        margin-bottom: 0.15rem;
    }

    .invoice-doc header .company-contact {
        font-size: 0.85rem;
        color: var(--invoice-muted);
        line-height: 1.5;
        margin-top: 0.5rem;
    }

    .invoice-doc .muted {
        color: var(--invoice-muted);
        font-size: 0.9rem;
    }

    .invoice-doc .meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        padding: 1.25rem 1.5rem;
        background: #f8fafc;
        border-bottom: 1px solid var(--invoice-border);
    }

    .invoice-doc .meta section {
        padding: 1.25rem 1.5rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #ffffff;
    }

    .invoice-doc .meta h2 {
        margin: 0 0 0.75rem;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-weight: 700;
        color: var(--invoice-muted);
    }

    .invoice-doc .meta p {
        margin: 0.35rem 0;
        line-height: 1.5;
        font-size: 0.9rem;
        color: var(--invoice-text);
    }

    .invoice-doc .meta p strong {
        font-weight: 600;
        color: var(--invoice-text);
    }

    .invoice-doc .details {
        padding: 1.25rem 1.5rem;
    }

    .invoice-doc table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 0;
        background: #ffffff;
    }

    .invoice-doc thead th {
        text-align: left;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-weight: 700;
        color: var(--invoice-muted);
        padding: 0.6rem 0.75rem;
        border-bottom: 2px solid #e2e8f0;
        background: #f8fafc;
    }

    .invoice-doc thead th:last-child {
        text-align: right;
    }

    .invoice-doc tbody tr {
        border-bottom: 1px solid #e2e8f0;
    }

    .invoice-doc tbody tr:last-child {
        border-bottom: none;
    }

    .invoice-doc tbody td {
        padding: 0.75rem;
        font-size: 0.9rem;
        color: var(--invoice-text);
    }

    .invoice-doc tbody td:last-child,
    .invoice-doc tbody td.amount-due {
        text-align: right;
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }

    .invoice-doc tbody tr.total-due-row {
        border-top: 2px solid #e2e8f0;
        background: #f8fafc;
    }

    .invoice-doc tbody tr.total-due-row td {
        padding-top: 0.9rem;
        padding-bottom: 0.9rem;
    }

    .invoice-doc tbody .total-due-label {
        text-align: right;
        font-weight: 700;
        color: var(--invoice-text);
        padding-right: 0.75rem;
    }

    .invoice-doc tbody .total-due-amount {
        text-align: right;
        font-variant-numeric: tabular-nums;
        font-weight: 700;
        font-size: 1rem;
        color: var(--invoice-text);
    }

    .invoice-doc .summary {
        margin-top: 1.5rem;
        display: flex;
        justify-content: flex-end;
    }

    .invoice-doc .summary section {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1.5rem;
        background: #f8fafc;
        min-width: 280px;
    }

    .invoice-doc .summary h3 {
        margin: 0 0 1rem;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-weight: 700;
        color: var(--invoice-muted);
    }

    .invoice-doc .summary p {
        display: flex;
        justify-content: space-between;
        margin: 0.5rem 0;
        font-size: 0.95rem;
        color: var(--invoice-text);
    }

    .invoice-doc .summary p:last-of-type {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 2px solid #e2e8f0;
        font-size: 1.1rem;
        font-weight: 700;
    }

    .invoice-doc .summary p span:last-child {
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .invoice-doc footer {
        margin-top: 1.5rem;
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--invoice-border);
        font-size: 0.9rem;
        color: var(--invoice-text);
        background: rgba(249, 177, 4, 0.08);
    }

    .invoice-attachments {
        max-width: 900px;
        margin: 1.5rem auto 0;
        padding: 2rem 2.5rem;
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid rgba(226, 232, 240, 0.8);
        box-shadow: 0 18px 40px -20px rgba(15, 23, 42, 0.25);
    }

    .invoice-attachments__header h2 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--invoice-text);
    }

    .invoice-attachments__header p {
        margin-top: 0.25rem;
        font-size: 0.9rem;
        color: var(--invoice-muted);
    }

    .invoice-attachments__grid {
        margin-top: 1.5rem;
        display: grid;
        gap: 1rem;
    }

    .invoice-attachments__item {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: center;
        padding: 1rem 1.25rem;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 16px;
        background: linear-gradient(180deg, rgba(248, 250, 252, 0.9) 0%, #ffffff 100%);
    }

    .invoice-attachments__label {
        display: flex;
        gap: 0.85rem;
        align-items: center;
        flex: 1;
        min-width: 0;
    }

    .invoice-attachments__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 2.5rem;
        width: 2.5rem;
        border-radius: 9999px;
        background: rgba(249, 177, 4, 0.15);
        color: var(--invoice-accent);
    }

    .invoice-attachments__name {
        margin: 0;
        font-weight: 600;
        color: var(--invoice-text);
        word-break: break-word;
    }

    .invoice-attachments__meta {
        margin: 0.2rem 0 0;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--invoice-muted);
    }

    .invoice-attachments__action {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.55rem 1.25rem;
        border-radius: 9999px;
        border: 1px solid rgba(249, 177, 4, 0.4);
        color: var(--invoice-accent);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background: #ffffff;
        transition: all 0.2s ease-in-out;
    }

    .invoice-attachments__action:hover {
        border-color: rgba(249, 177, 4, 0.6);
        color: #d48806;
    }

    .invoice-support-message {
        max-width: 900px;
        margin: 1.5rem auto 0;
        padding: 1rem 1.5rem;
        border-radius: 16px;
        border: 1px dashed rgba(148, 163, 184, 0.6);
        background: rgba(248, 250, 252, 0.65);
        color: var(--invoice-muted);
        font-size: 0.9rem;
    }

    .invoice-portal-footer {
        max-width: 900px;
        margin: 1.5rem auto 0;
        padding: 0 0 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
        color: rgba(100, 116, 139, 0.9);
        gap: 1rem;
    }

    .invoice-portal-footer a {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--invoice-accent);
        font-weight: 600;
        text-decoration: none;
    }

    .invoice-portal-footer a:hover {
        text-decoration: underline;
    }

    .invoice-portal-actions {
        max-width: 900px;
        margin: 1.5rem auto 0;
        display: flex;
        justify-content: flex-end;
    }

    .invoice-print-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.65rem 1.4rem;
        border-radius: 9999px;
        background: var(--invoice-accent);
        color: #fff;
        font-weight: 600;
        font-size: 0.85rem;
        text-decoration: none;
        border: none;
        cursor: pointer;
        box-shadow: 0 10px 25px -12px rgba(249, 177, 4, 0.8);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .invoice-print-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 16px 35px -18px rgba(249, 177, 4, 0.9);
    }

    .invoice-print-button svg {
        height: 1rem;
        width: 1rem;
    }

    .no-print {
        display: block;
    }

    @media print {
        .no-print {
            display: none !important;
        }
        .invoice-doc {
            background: none !important;
            padding: 0 !important;
            display: block;
        }
        .invoice-doc--portal {
            display: block;
            padding: 0 !important;
        }
        .invoice-doc .page {
            margin: 0;
            width: 100% !important;
            max-width: 100% !important;
            border-radius: 0;
            border: none;
            box-shadow: none;
        }
        .invoice-doc header {
            border-bottom: none;
            padding: 1rem 1.25rem;
            gap: 1.5rem;
        }
        .invoice-doc footer {
            border-top: none;
            padding: 0.9rem 1.25rem;
            margin-top: 1rem;
        }
        .invoice-doc .meta {
            padding: 1rem 1.25rem;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        }
        .invoice-doc .meta section {
            padding: 0.75rem 0.9rem;
        }
        .invoice-doc header {
            padding: 1rem 1.25rem;
            gap: 1.5rem;
        }
        .invoice-doc header .invoice-logo img {
            max-height: 60px;
            max-width: 150px;
        }
        .invoice-doc header h1 {
            font-size: 1.8rem;
        }
        .invoice-doc .meta {
            padding: 1.5rem 1.5rem;
            gap: 1rem;
        }
        .invoice-doc .details {
            padding: 1rem 1.25rem;
        }
        .invoice-doc table {
            margin-top: 0;
        }
        .invoice-doc thead th {
            padding: 0.6rem 0.75rem;
            font-size: 0.7rem;
        }
        .invoice-doc tbody td {
            padding: 0.75rem;
            font-size: 0.85rem;
        }
        .invoice-doc .summary {
            margin-top: 1.5rem;
        }
        .invoice-doc .summary section {
            padding: 1.25rem;
            min-width: 240px;
        }
        .invoice-attachments,
        .invoice-support-message,
        .invoice-portal-actions,
        .invoice-portal-footer {
            display: none !important;
        }
    }

@media (max-width: 640px) {
    .invoice-portal-actions {
        justify-content: center;
        margin: 1rem auto 0;
    }

    .invoice-print-button {
        width: 100%;
        justify-content: center;
        font-size: 0.8rem;
        padding: 0.65rem 1.1rem;
    }

    .invoice-attachments__item {
        flex-direction: column;
        align-items: flex-start;
    }

    .invoice-attachments__action {
        width: 100%;
        justify-content: center;
        text-align: center;
    }

    .invoice-portal-footer {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
}
</style>
