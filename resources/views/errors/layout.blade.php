<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — Aura Luxe</title>
    <style>
        :root {
            --bg-color: #FAF8F5;
            --card-bg: #FFFFFF;
            --card-border: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #64748B;
            --btn-primary-bg: #0F172A;
            --btn-primary-text: #FFFFFF;
            --btn-primary-hover: #1E293B;
            --btn-secondary-border: #CBD5E1;
            --btn-secondary-text: #334155;
            --btn-secondary-hover: #F1F5F9;
            --badge-bg: #F8FAFC;
            --badge-border: #E2E8F0;
            --badge-text: #475569;
            --accent: #B45309;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-color: #0B0F19;
                --card-bg: #0F172A;
                --card-border: #1E293B;
                --text-primary: #F8FAFC;
                --text-secondary: #94A3B8;
                --btn-primary-bg: #D97706;
                --btn-primary-text: #FFFFFF;
                --btn-primary-hover: #B45309;
                --btn-secondary-border: #334155;
                --btn-secondary-text: #E2E8F0;
                --btn-secondary-hover: #1E293B;
                --badge-bg: rgba(30, 41, 59, 0.7);
                --badge-border: #334155;
                --badge-text: #94A3B8;
                --accent: #F59E0B;
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: 2rem 1rem;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .brand-title {
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.6rem;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            font-weight: 600;
            color: var(--text-primary);
            text-decoration: none;
        }

        .brand-subtitle {
            display: block;
            font-size: 0.65rem;
            letter-spacing: 0.35em;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-top: 0.2rem;
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 1.5rem;
            padding: 2.5rem 2rem;
            max-width: 32rem;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }

        .badge {
            display: inline-block;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            background-color: var(--badge-bg);
            border: 1px solid var(--badge-border);
            color: var(--badge-text);
            margin-bottom: 1.25rem;
        }

        .icon-wrap {
            width: 3.5rem;
            height: 3.5rem;
            margin: 0 auto 1.25rem auto;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--badge-bg);
            border: 1px solid var(--badge-border);
            color: var(--accent);
        }

        .icon-wrap svg {
            width: 1.75rem;
            height: 1.75rem;
        }

        .headline {
            font-family: Georgia, "Times New Roman", serif;
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            max-width: 26rem;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8125rem;
            font-weight: 500;
            padding: 0.65rem 1.25rem;
            border-radius: 0.75rem;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-primary {
            background-color: var(--btn-primary-bg);
            color: var(--btn-primary-text);
            border: 1px solid transparent;
        }

        .btn-primary:hover {
            background-color: var(--btn-primary-hover);
        }

        .btn-secondary {
            background-color: transparent;
            color: var(--btn-secondary-text);
            border: 1px solid var(--btn-secondary-border);
        }

        .btn-secondary:hover {
            background-color: var(--btn-secondary-hover);
        }

        .footer {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 1.5rem;
        }

        @media (max-width: 480px) {
            .card {
                padding: 2rem 1.25rem;
            }
            .headline {
                font-size: 1.4rem;
            }
            .actions {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="/" class="brand-title">Aura Luxe</a>
        <span class="brand-subtitle">Botanical Cosmetics</span>
    </header>

    <main class="card">
        <div class="badge">@yield('code') &mdash; @yield('title')</div>

        <div class="icon-wrap">
            @yield('icon')
        </div>

        <h1 class="headline">@yield('heading')</h1>

        <p class="description">@yield('message')</p>

        <div class="actions">
            @yield('actions')
        </div>
    </main>

    <footer class="footer">
        &copy; {{ date('Y') }} Aura Luxe. Botanical Formulations & Luxury Care.
    </footer>
</body>
</html>

