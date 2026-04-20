<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }} — Patient Reported Outcomes</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <style>
            :root {
                color-scheme: light dark;
                --bg: #f4f7f9;
                --surface: #ffffff;
                --text: #0f172a;
                --muted: #475569;
                --accent: #0d9488;
                --accent-soft: rgba(13, 148, 136, 0.12);
                --border: #e2e8f0;
                --code-bg: #f1f5f9;
            }
            @media (prefers-color-scheme: dark) {
                :root {
                    --bg: #0f1419;
                    --surface: #1a222c;
                    --text: #f1f5f9;
                    --muted: #94a3b8;
                    --accent: #2dd4bf;
                    --accent-soft: rgba(45, 212, 191, 0.15);
                    --border: #334155;
                    --code-bg: #0f172a;
                }
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                font-family: Figtree, ui-sans-serif, system-ui, sans-serif;
                background: var(--bg);
                color: var(--text);
                line-height: 1.6;
            }
            .wrap {
                max-width: 52rem;
                margin: 0 auto;
                padding: 2.5rem 1.25rem 4rem;
            }
            .hero {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 1rem;
                padding: 2rem 1.75rem;
                margin-bottom: 2rem;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
            }
            .hero h1 {
                margin: 0 0 0.75rem;
                font-size: 1.75rem;
                font-weight: 600;
                letter-spacing: -0.02em;
            }
            .hero p {
                margin: 0;
                color: var(--muted);
                font-size: 1.05rem;
            }
            .badge {
                display: inline-block;
                font-size: 0.75rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: var(--accent);
                background: var(--accent-soft);
                padding: 0.35rem 0.65rem;
                border-radius: 999px;
                margin-bottom: 1rem;
            }
            .panel {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 1rem;
                padding: 1.75rem 1.5rem 2rem;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
            }
            .panel h2 {
                margin: 0 0 1rem;
                font-size: 1.15rem;
                font-weight: 600;
            }
            .markdown-body { font-size: 0.95rem; }
            .markdown-body h1 {
                font-size: 1.35rem;
                margin: 0 0 1rem;
                padding-bottom: 0.5rem;
                border-bottom: 1px solid var(--border);
            }
            .markdown-body h2 {
                font-size: 1.1rem;
                margin: 1.75rem 0 0.75rem;
                color: var(--text);
            }
            .markdown-body h2:first-child { margin-top: 0; }
            .markdown-body h3 { font-size: 1rem; margin: 1.25rem 0 0.5rem; }
            .markdown-body p { margin: 0.65rem 0; color: var(--muted); }
            .markdown-body ul, .markdown-body ol { margin: 0.5rem 0 0.75rem 1.25rem; color: var(--muted); }
            .markdown-body li { margin: 0.25rem 0; }
            .markdown-body table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.875rem;
                margin: 0.75rem 0 1.25rem;
            }
            .markdown-body th, .markdown-body td {
                border: 1px solid var(--border);
                padding: 0.5rem 0.65rem;
                text-align: left;
            }
            .markdown-body th {
                background: var(--code-bg);
                font-weight: 600;
                color: var(--text);
            }
            .markdown-body td { color: var(--muted); }
            .markdown-body code {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 0.85em;
                background: var(--code-bg);
                padding: 0.15em 0.4em;
                border-radius: 0.25rem;
            }
            .markdown-body pre {
                background: var(--code-bg);
                border: 1px solid var(--border);
                border-radius: 0.5rem;
                padding: 1rem;
                overflow-x: auto;
                font-size: 0.8rem;
                margin: 0.75rem 0;
            }
            .markdown-body pre code { background: none; padding: 0; }
            footer {
                margin-top: 2.5rem;
                text-align: center;
                font-size: 0.8rem;
                color: var(--muted);
            }
        </style>
    </head>
    <body>
        <div class="wrap">
            <header class="hero">
                <span class="badge">Wave Health</span>
                <h1>Welcome to Patient Reported Outcomes</h1>
                <p>
                    This service is part of our chronic-care platform: patients share structured health feedback over time,
                    and clinicians review it through programmatic access. <strong>This application is API-first</strong>—there is no
                    patient or clinician UI here; integrations consume the REST API below.
                </p>
            </header>

            <section class="panel" aria-labelledby="api-docs-heading">
                <h2 id="api-docs-heading">API schema and endpoints</h2>
                <div class="markdown-body">
                    {!! $apiDocsHtml !!}
                </div>
            </section>

            <footer>
                {{ config('app.name') }} — Laravel {{ Illuminate\Foundation\Application::VERSION }} (PHP {{ PHP_VERSION }})
            </footer>
        </div>
    </body>
</html>
