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
        align-items: center;
        gap: 2rem;
        padding: 1.75rem 2rem;
        background: linear-gradient(135deg, #fff7e6 0%, #ffeccc 100%);
        border-bottom: 1px solid var(--invoice-border);
    }

    .invoice-doc header h1 {
        margin: 0;
        font-size: 1.8rem;
        letter-spacing: 0.04em;
    }

    .invoice-doc .muted {
        color: var(--invoice-muted);
        font-size: 0.95rem;
    }

    .invoice-doc .meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.25rem;
        padding: 1.75rem 2.2rem;
    }

    .invoice-doc .meta section {
        padding: 0.9rem 1.15rem;
        border: 1px solid var(--invoice-border);
        border-radius: 12px;
        background: #fff;
        box-shadow: inset 0 0 0 1px rgba(249, 177, 4, 0.05);
    }

    .invoice-doc .meta h2 {
        margin: 0 0 0.5rem;
        font-size: 0.82rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--invoice-muted);
    }

    .invoice-doc .meta p {
        margin: 0.15rem 0;
        line-height: 1.35;
        font-size: 0.88rem;
    }

    .invoice-doc .details {
        padding: 0 2rem 1.75rem;
    }

    .invoice-doc table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1.5rem;
    }

    .invoice-doc thead th {
        text-align: left;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--invoice-muted);
        padding-bottom: 0.6rem;
        border-bottom: 1px solid var(--invoice-border);
    }

    .invoice-doc tbody tr {
        border-bottom: 1px solid var(--invoice-border);
    }

    .invoice-doc tbody td {
        padding: 0.8rem 0;
        font-size: 0.92rem;
    }

    .invoice-doc tbody td:last-child {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .invoice-doc .summary {
        margin-top: 1.75rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1.5rem;
    }

    .invoice-doc .summary section {
        border: 1px solid var(--invoice-border);
        border-radius: 12px;
        padding: 1.1rem;
        background: #fff;
        box-shadow: inset 0 0 0 1px rgba(249, 177, 4, 0.04);
    }

    .invoice-doc .summary h3 {
        margin: 0 0 1rem;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--invoice-muted);
    }

    .invoice-doc .summary p {
        display: flex;
        justify-content: space-between;
        margin: 0.4rem 0;
        font-size: 0.95rem;
    }

    .invoice-doc .summary p span:last-child {
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .invoice-doc footer {
        margin-top: 2.5rem;
        padding: 1.25rem 2rem;
        border-top: 1px solid var(--invoice-border);
        font-size: 0.9rem;
        color: var(--invoice-muted);
        background: #fffef9;
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

    @media print {
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
        }
        .invoice-doc footer {
            border-top: none;
            padding: 0.9rem 1.25rem;
        }
        .invoice-doc .meta {
            padding: 1.2rem 1.2rem;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        }
        .invoice-doc .meta section {
            padding: 0.75rem 0.9rem;
        }
        .invoice-doc .details {
            padding: 0 1.25rem 1.25rem;
        }
        .invoice-doc table {
            margin-top: 0.85rem;
        }
        .invoice-doc tbody td {
            padding: 0.5rem 0;
        }
        .invoice-doc .summary {
            margin-top: 1.1rem;
            gap: 0.85rem;
        }
        .invoice-doc .summary section {
            padding: 0.9rem;
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
