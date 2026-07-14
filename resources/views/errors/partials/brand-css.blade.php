{{-- Shared, self-contained styles for branded error pages (TASK-065). --}}
<style>
    :root {
        --brand: #f9b104;
        --brand-dark: #d99500;
        --ink: #1f2937;
        --muted: #6b7280;
        --bg: #f6f7f9;
        --card: #ffffff;
        --border: #e5e7eb;
        --ring: rgba(249, 177, 4, 0.35);
    }
    @media (prefers-color-scheme: dark) {
        :root {
            --ink: #f3f4f6;
            --muted: #9ca3af;
            --bg: #0b0f16;
            --card: #131a24;
            --border: #263241;
            --ring: rgba(249, 177, 4, 0.45);
        }
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        background:
            radial-gradient(circle at 15% -10%, var(--ring), transparent 45%),
            radial-gradient(circle at 100% 110%, var(--ring), transparent 40%),
            var(--bg);
        color: var(--ink);
        font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
        -webkit-font-smoothing: antialiased;
    }
    .card {
        width: 100%;
        max-width: 30rem;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 1.25rem;
        box-shadow: 0 20px 45px -25px rgba(0, 0, 0, 0.45);
        padding: 2.5rem 2rem;
        text-align: center;
    }
    .logo { height: 3.25rem; width: auto; margin: 0 auto 1.5rem; display: block; }
    .code {
        font-size: clamp(3.5rem, 18vw, 5.5rem);
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.03em;
        color: var(--brand);
        margin: 0;
    }
    .heading {
        font-size: 1.375rem;
        font-weight: 700;
        margin: 0.75rem 0 0.5rem;
        color: var(--ink);
    }
    .message {
        font-size: 1rem;
        line-height: 1.6;
        color: var(--muted);
        margin: 0 auto 1.75rem;
        max-width: 24rem;
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        justify-content: center;
    }
    .btn {
        appearance: none;
        border: 1px solid transparent;
        border-radius: 0.75rem;
        padding: 0.7rem 1.35rem;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: transform 0.06s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .btn:active { transform: translateY(1px); }
    .btn-primary {
        background: var(--brand);
        color: #1f2937;
        box-shadow: 0 8px 20px -10px var(--brand-dark);
    }
    .btn-primary:hover { background: var(--brand-dark); }
    .btn-secondary {
        background: transparent;
        color: var(--ink);
        border-color: var(--border);
    }
    .btn-secondary:hover { border-color: var(--brand); color: var(--brand); }
    .footer {
        margin-top: 2rem;
        font-size: 0.8rem;
        color: var(--muted);
    }
    .footer a { color: var(--brand-dark); }
    @media (prefers-color-scheme: dark) { .footer a { color: var(--brand); } }
</style>
