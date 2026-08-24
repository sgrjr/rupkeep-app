<style>
    /*
     * Invoice type scale (TASK-369). The document was set noticeably larger
     * than an invoice needs to be, which pushed content down the page and cost
     * a second sheet on longer jobs. Every size here is one step down from the
     * original; the smallest uppercase labels are floored so they stay legible
     * in print rather than scaling into illegibility.
     *
     * Scoped entirely to .invoice-doc — the customer portal renders this
     * document inside the app's guest layout, so the root font size must not be
     * touched.
     */

    @page {
        margin: 0;
    }

    /*
     * Border-box everywhere in the invoice document (TASK-369). Without this,
     * any element combining an explicit width with padding renders wider than
     * its container and gets sliced off by the .page overflow:hidden — which is
     * exactly how the bill-to block ended up cut mid-word ("South Portland,
     * Maine 4…") on the print view.
     */
    .invoice-doc,
    .invoice-doc *,
    .invoice-doc *::before,
    .invoice-doc *::after {
        box-sizing: border-box;
    }

    :root {
        color-scheme: light;
        font-family: "Segoe UI", Arial, sans-serif;
        --invoice-accent: #f9b104;
        /*
         * Print-legible, not just screen-legible (TASK-406). At #d4d4d4 the
         * rules between line items were about 1.5:1 on white -- barely there
         * on a backlit screen and gone entirely on paper, where many printers
         * drop very light fills. This is the document of record in a billing
         * dispute, so its structure has to survive being printed.
         */
        --invoice-border: #9ca3af;
        --invoice-text: #172232;
        --invoice-muted: #6b7280;
        --invoice-card-bg: #ffffff;
        /*
         * The page ground behind the invoice card. At #f8fafc this sat about
         * 1.02:1 against the white card -- the same colour for practical
         * purposes -- so the whole document read as one flat sheet with
         * floating text and no card edge at all.
         */
        --invoice-background: #e8ecf1;
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
        font-size: 1.87rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--invoice-text);
    }

    .invoice-doc header .company-name-large {
        margin: 0 0 0.25rem;
        font-size: 1.53rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--invoice-text);
        line-height: 1.2;
    }

    .invoice-doc header .company-tagline {
        margin: 0 0 0.75rem;
        font-size: 0.72rem;
        color: var(--invoice-accent);
        font-weight: 500;
    }

    .invoice-doc header .company-info {
        font-size: 0.77rem;
        color: var(--invoice-text);
        line-height: 1.5;
        margin-top: 0.5rem;
    }

    .invoice-doc header .company-info div {
        margin-bottom: 0.15rem;
    }

    .invoice-doc header .invoice-summary-title {
        margin: 0 0 0.5rem;
        font-size: 1.53rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--invoice-text);
        text-align: right;
    }

    .invoice-doc header .summary-id {
        font-size: 0.77rem;
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
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-weight: 700;
        color: var(--invoice-muted);
        text-align: right;
    }

    .invoice-doc header .bill-to-info {
        font-size: 0.77rem;
        color: var(--invoice-text);
        line-height: 1.5;
    }

    .invoice-doc header .bill-to-company {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .invoice-doc header .bill-to-contact {
        font-size: 0.77rem;
        color: var(--invoice-text);
        line-height: 1.5;
        margin-bottom: 0.15rem;
    }

    .invoice-doc header .bill-to-address {
        font-size: 0.77rem;
        color: var(--invoice-text);
        line-height: 1.5;
        margin-bottom: 0.15rem;
    }

    .invoice-doc header .organization-info {
        font-size: 0.77rem;
        color: var(--invoice-text);
        line-height: 1.5;
    }

    .invoice-doc header .organization-name {
        font-size: 0.94rem;
        font-weight: 700;
        color: var(--invoice-text);
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }

    .invoice-doc header .organization-contact {
        font-size: 0.77rem;
        color: var(--invoice-text);
        line-height: 1.5;
        margin-bottom: 0.15rem;
    }

    .invoice-doc header .organization-address {
        font-size: 0.77rem;
        color: var(--invoice-text);
        line-height: 1.5;
        margin-bottom: 0.15rem;
    }

    .invoice-doc header .invoice-title-right {
        margin: 0 0 0.5rem;
        font-size: 1.53rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--invoice-text);
        text-align: right;
    }

    .invoice-doc header .invoice-date {
        font-size: 0.77rem;
        color: var(--invoice-text);
        margin-top: 0.25rem;
        text-align: right;
    }

    .invoice-doc tbody td.text-right {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .invoice-doc header .invoice-number {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--invoice-text);
        margin-bottom: 0.25rem;
    }

    .invoice-doc header .header-right {
        text-align: right;
        flex-shrink: 0;
    }

    .invoice-doc header .company-name {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--invoice-text);
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }

    .invoice-doc header .company-address {
        font-size: 0.77rem;
        color: var(--invoice-text);
        line-height: 1.5;
        margin-bottom: 0.15rem;
    }

    .invoice-doc header .company-contact {
        font-size: 0.72rem;
        color: var(--invoice-muted);
        line-height: 1.5;
        margin-top: 0.5rem;
    }

    .invoice-doc .muted {
        color: var(--invoice-muted);
        font-size: 0.77rem;
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
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-weight: 700;
        color: var(--invoice-muted);
    }

    .invoice-doc .meta p {
        margin: 0.35rem 0;
        line-height: 1.5;
        font-size: 0.77rem;
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
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-weight: 700;
        color: var(--invoice-muted);
        padding: 0.6rem 0.75rem;
        border-bottom: 2px solid #e2e8f0;
        background: #f8fafc;
    }

    .invoice-doc thead th:last-child,
    .invoice-doc thead th.text-right {
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
        font-size: 0.77rem;
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
        font-size: 0.85rem;
        color: var(--invoice-text);
    }

    /*
     * Per-row detail on a summary invoice (TASK-345): truck driver, truck and
     * trailer ride under the description rather than as three more columns,
     * so a twenty-row monthly summary still fits letter portrait.
     */
    .invoice-doc .summary-item__equipment {
        margin-top: 0.15rem;
        font-size: 0.6rem;
        color: var(--invoice-muted);
    }

    .invoice-doc .summary-item__canceled {
        margin-top: 0.15rem;
        font-size: 0.6rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        color: #dc2626;
    }

    .invoice-doc .summary {
        margin-top: 1rem;
        display: flex;
        justify-content: flex-end;
    }

    /*
     * The summary is a closing recap, not the body of the invoice — it was
     * taking a disproportionate share of the page, so the padding and type
     * scale are deliberately tighter than the line-item table above it.
     */
    .invoice-doc .summary section {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.85rem 1rem;
        background: #f8fafc;
        min-width: 240px;
    }

    .invoice-doc .summary h3 {
        margin: 0 0 0.5rem;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
        color: var(--invoice-muted);
    }

    .invoice-doc .summary p {
        display: flex;
        justify-content: space-between;
        margin: 0.25rem 0;
        font-size: 0.68rem;
        color: var(--invoice-text);
    }

    .invoice-doc .summary p:last-of-type {
        margin-top: 0.5rem;
        padding-top: 0.5rem;
        border-top: 1px solid #e2e8f0;
        font-size: 0.81rem;
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
        font-size: 0.77rem;
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
        font-size: 0.94rem;
        font-weight: 600;
        color: var(--invoice-text);
    }

    .invoice-attachments__header p {
        margin-top: 0.25rem;
        font-size: 0.77rem;
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
        font-size: 0.65rem;
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
        font-size: 0.65rem;
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
        font-size: 0.77rem;
    }

    .invoice-portal-footer {
        max-width: 900px;
        margin: 1.5rem auto 0;
        padding: 0 0 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.68rem;
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
        font-size: 0.72rem;
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

    /* Job-detail block on single invoices (TASK-344): job date, job/load
       numbers, truck driver/number, trailer number, pickup/delivery. Table
       layout so it renders under dompdf (no flex/grid). Explicit resets keep it
       from inheriting the line-item table's borders and right-aligned last cell. */
    .invoice-doc .job-info {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--invoice-border);
    }

    .invoice-doc .job-info table {
        width: 100%;
        border-collapse: collapse;
        background: transparent;
    }

    .invoice-doc .job-info tr {
        border: none;
    }

    .invoice-doc .job-info td {
        width: 33%;
        vertical-align: top;
        text-align: left;
        font-weight: 400;
        padding: 0.3rem 0.75rem 0.3rem 0;
        font-size: 0.77rem;
        color: var(--invoice-text);
    }

    .invoice-doc .job-info td:last-child {
        text-align: left;
        font-weight: 400;
    }

    .invoice-doc .job-info__locations td {
        width: 50%;
    }

    .invoice-doc .job-info__label {
        display: block;
        margin-bottom: 0.1rem;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
        color: var(--invoice-muted);
    }

    .invoice-doc .job-info__canceled {
        margin-top: 0.5rem;
        font-weight: 700;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #dc2626;
    }

    .no-print {
        display: block;
    }

    /*
     * PDF / print-document overrides (TASK-340).
     *
     * The dedicated print view (invoices/print.blade.php → body.invoice-doc--print)
     * is rendered by dompdf, which uses the "screen" media type and therefore
     * IGNORES the @media print block below, and does not support flexbox. Without
     * these unconditional rules the generated PDF keeps the on-screen card chrome
     * (rounded corners, outer margin, drop shadow) and the flex header collapses
     * into two stacked full-width rows, wasting the top of the page. Scope is
     * limited to --print so the browser portal view (--portal) is unaffected.
     */
    .invoice-doc--print {
        background: #ffffff;
        min-height: 0;
    }

    .invoice-doc--print .page {
        width: 100%;
        max-width: 100%;
        margin: 0;
        border: none;
        border-radius: 0;
        box-shadow: none;
    }

    /*
     * The rules above are unconditional because dompdf ignores @media print
     * (TASK-340) — but they were also stretching the on-screen preview across
     * the full browser window, so /print looked nothing like the PDF it is
     * previewing (TASK-369). `--screen` is applied only by the browser route,
     * never by the dompdf one, so the preview gets a letter-width page back
     * while the generated PDF stays full-bleed.
     */
    .invoice-doc--print.invoice-doc--screen {
        background: var(--invoice-background);
        padding: 2rem 1rem;
    }

    .invoice-doc--print.invoice-doc--screen .page {
        width: min(8.5in, 100%);
        margin: 0 auto;
        border: 1px solid var(--invoice-border);
        border-radius: 12px;
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.15);
    }

    @media print {
        .invoice-doc--print.invoice-doc--screen {
            background: #ffffff;
            padding: 0;
        }

        .invoice-doc--print.invoice-doc--screen .page {
            width: 100%;
            border: none;
            border-radius: 0;
            box-shadow: none;
        }
    }

    /* dompdf ignores flexbox; lay the header out as a fixed table so the two
       columns sit side by side instead of stacking. */
    .invoice-doc--print header {
        display: table;
        width: 100%;
        table-layout: fixed;
        padding: 0;
        gap: 0;
    }

    /*
     * The padding lives on the cells, not the table (TASK-369). A `display:
     * table` at width:100% WITH horizontal padding measures 100% + 2.5rem, so
     * the header ran 40px past .page and its overflow:hidden clipped the
     * right-hand bill-to column. Padding the cells keeps the same visual inset
     * without ever exceeding the container, in any renderer.
     */
    .invoice-doc--print header .header-left,
    .invoice-doc--print header .header-right {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        padding: 1rem 1.25rem;
    }

    .invoice-doc--print header .header-left {
        padding-right: 0.625rem;
    }

    .invoice-doc--print header .header-right {
        padding-left: 0.625rem;
    }

    /*
     * Long unbroken values (company names, street addresses, emails) must wrap
     * rather than run off the edge of a fixed-width table cell.
     */
    .invoice-doc--print header .header-left,
    .invoice-doc--print header .header-right,
    .invoice-doc .bill-to-info,
    .invoice-doc .organization-info,
    .invoice-doc header .company-info {
        overflow-wrap: break-word;
        word-wrap: break-word;
    }

    .invoice-doc--print header .header-right {
        text-align: right;
    }

    /*
     * The org logo is a remote asset (and often AVIF). dompdf renders PDFs
     * with remote images disabled and cannot decode AVIF, so the <img> never
     * loaded — it only ever emitted its alt text, a second copy of the company
     * name directly above the heading. Hide it in the PDF and let the text
     * letterhead stand alone. The browser portal view (--portal) keeps the logo.
     */
    .invoice-doc--print header .invoice-logo {
        display: none;
    }

    /* A tighter, single-line company name reads more like a letterhead than the
       oversized heading that wrapped inside the half-width header column. */
    .invoice-doc--print header .company-name-large {
        font-size: 1.15rem;
        line-height: 1.25;
        margin-bottom: 0.15rem;
    }

    .invoice-doc--print header .organization-name {
        font-size: 1.06rem;
    }

    .invoice-doc--print header .invoice-title-right,
    .invoice-doc--print header .invoice-summary-title {
        font-size: 1.36rem;
    }

    /*
     * Summary rows are flex label/value pairs, and dompdf ignores flexbox, so
     * in the PDF they collapsed to inline text — "Subtotal $357.00" run
     * together instead of the label left and the amount right (TASK-369). Same
     * fix as the header above: lay them out as a table for the PDF only, so the
     * browser keeps its flex layout untouched.
     */
    .invoice-doc--print .summary p {
        display: table;
        width: 100%;
        table-layout: fixed;
    }

    .invoice-doc--print .summary p > span:first-child {
        display: table-cell;
        text-align: left;
    }

    .invoice-doc--print .summary p > span:last-child {
        display: table-cell;
        text-align: right;
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
            font-size: 1.53rem;
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
            font-size: 0.65rem;
        }
        .invoice-doc tbody td {
            padding: 0.75rem;
            font-size: 0.72rem;
        }
        .invoice-doc .summary {
            margin-top: 0.9rem;
        }
        .invoice-doc .summary section {
            padding: 0.75rem 0.9rem;
            min-width: 210px;
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
        font-size: 0.68rem;
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
