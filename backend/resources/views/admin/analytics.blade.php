<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard — Carboot@CMart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                        },
                    },
                },
            },
        };
    </script>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .wordcloud-wrap canvas { display: block; margin: 0 auto; max-width: 100%; }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen">
    <header class="bg-white border-b border-slate-200 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
            <p class="text-sm font-semibold uppercase tracking-wider text-brand-600">Carboot@CMart Admin</p>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 mt-1">Booking &amp; Word Cloud Analytics</h1>
            <p class="text-sm text-slate-500 mt-2">Live data proxied securely from the Python analytics service.</p>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div id="global-error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></div>

        <section id="summary-section" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <article class="lg:col-span-1 rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-bold text-slate-900">Booking Summary</h2>
                <p class="text-sm text-slate-500 mt-1">Approval status overview</p>

                <div class="mt-6">
                    <p class="text-sm font-medium text-slate-500">Total Bookings</p>
                    <p id="total-bookings" class="text-4xl font-extrabold text-brand-600 mt-1">—</p>
                </div>

                <dl id="status-breakdown" class="mt-6 space-y-3">
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
                        <dt class="text-sm text-slate-600">Approved</dt>
                        <dd id="status-approved" class="text-lg font-bold text-emerald-600">—</dd>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3">
                        <dt class="text-sm text-slate-600">Pending</dt>
                        <dd id="status-pending" class="text-lg font-bold text-amber-600">—</dd>
                    </div>
                </dl>
            </article>

            <article class="lg:col-span-2 rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">All Status Counts</h2>
                        <p class="text-sm text-slate-500 mt-1">Full breakdown from the analytics API</p>
                    </div>
                </div>
                <div id="status-grid" class="grid grid-cols-2 sm:grid-cols-3 gap-3"></div>
            </article>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <article class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Vendor Products Word Cloud</h2>
                        <p id="products-meta" class="text-xs text-slate-500 mt-1">Loading…</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                        bookings.product_details
                    </span>
                </div>
                <div class="wordcloud-wrap relative min-h-[280px] rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center">
                    <canvas id="products-cloud" aria-label="Vendor products word cloud"></canvas>
                    <p id="products-empty" class="hidden absolute inset-0 flex items-center justify-center text-sm text-slate-500 px-6 text-center">
                        No approved vendor product descriptions yet.
                    </p>
                </div>
            </article>

            <article class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Community Feedback Word Cloud</h2>
                        <p id="feedback-meta" class="text-xs text-slate-500 mt-1">Loading…</p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-brand-100 px-3 py-1 text-xs font-semibold text-brand-700">
                        feedbacks.comments
                    </span>
                </div>
                <div class="wordcloud-wrap relative min-h-[280px] rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center">
                    <canvas id="feedback-cloud" aria-label="Community feedback word cloud"></canvas>
                    <p id="feedback-empty" class="hidden absolute inset-0 flex items-center justify-center text-sm text-slate-500 px-6 text-center">
                        No feedback text yet. Reviews will appear here once submitted.
                    </p>
                </div>
            </article>
        </section>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/wordcloud2.js/1.2.2/wordcloud2.min.js"></script>
    <script>
        (function () {
            'use strict';

            const PROXY = {
                summary: @json(url('/api/proxy/analytics/summary')),
                feedback: @json(url('/api/proxy/analytics/feedback')),
                products: @json(url('/api/proxy/analytics/products')),
            };

            const PRODUCT_COLORS = ['#047857', '#059669', '#10b981', '#34d399', '#065f46', '#0f766e'];
            const FEEDBACK_COLORS = ['#c2410c', '#ea580c', '#f97316', '#fb923c', '#9a3412', '#b45309'];

            const globalErrorEl = document.getElementById('global-error');
            const totalBookingsEl = document.getElementById('total-bookings');
            const statusApprovedEl = document.getElementById('status-approved');
            const statusPendingEl = document.getElementById('status-pending');
            const statusGridEl = document.getElementById('status-grid');
            const productsMetaEl = document.getElementById('products-meta');
            const feedbackMetaEl = document.getElementById('feedback-meta');
            const productsCanvas = document.getElementById('products-cloud');
            const feedbackCanvas = document.getElementById('feedback-cloud');
            const productsEmptyEl = document.getElementById('products-empty');
            const feedbackEmptyEl = document.getElementById('feedback-empty');

            function showGlobalError(message) {
                globalErrorEl.textContent = message;
                globalErrorEl.classList.remove('hidden');
            }

            async function fetchProxy(url) {
                let response;

                try {
                    response = await fetch(url, {
                        method: 'GET',
                        headers: { Accept: 'application/json' },
                    });
                } catch (error) {
                    throw new Error('Unable to reach the analytics proxy. Check that Laravel is running.');
                }

                let payload = null;

                try {
                    payload = await response.json();
                } catch (error) {
                    throw new Error('Analytics proxy returned an invalid response.');
                }

                if (!response.ok) {
                    throw new Error(payload?.message || `Analytics request failed (${response.status}).`);
                }

                return payload;
            }

            function resolveCount(data, keys) {
                for (const key of keys) {
                    if (typeof data?.[key] === 'number') {
                        return data[key];
                    }
                }

                return 0;
            }

            function renderSummary(data) {
                const breakdown = data?.status_breakdown ?? {};
                const total = resolveCount(data, ['total_bookings']);

                totalBookingsEl.textContent = total.toLocaleString();
                statusApprovedEl.textContent = (breakdown.Approved ?? 0).toLocaleString();

                const pendingTotal = Object.entries(breakdown).reduce((sum, [status, count]) => {
                    return status.toLowerCase().includes('pending') ? sum + Number(count || 0) : sum;
                }, breakdown.Pending ?? 0);

                statusPendingEl.textContent = pendingTotal.toLocaleString();

                statusGridEl.innerHTML = '';

                const entries = Object.entries(breakdown);

                if (!entries.length) {
                    statusGridEl.innerHTML = '<p class="col-span-full text-sm text-slate-500">No booking status data available.</p>';
                    return;
                }

                entries.forEach(([status, count]) => {
                    const card = document.createElement('div');
                    card.className = 'rounded-lg border border-slate-200 bg-slate-50 px-4 py-3';
                    card.innerHTML = `
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">${status}</p>
                        <p class="text-2xl font-bold text-slate-900 mt-1">${Number(count || 0).toLocaleString()}</p>
                    `;
                    statusGridEl.appendChild(card);
                });
            }

            function termsToWordList(terms) {
                if (!Array.isArray(terms)) {
                    return [];
                }

                return terms
                    .filter((term) => term && typeof term.text === 'string' && term.text.trim() !== '')
                    .map((term) => [term.text.trim(), Number(term.weight) || 1]);
            }

            function sizeCanvas(canvas) {
                const container = canvas.parentElement;
                const width = Math.max(container.clientWidth - 24, 280);
                const height = 280;

                canvas.width = width;
                canvas.height = height;
                canvas.style.width = `${width}px`;
                canvas.style.height = `${height}px`;

                return { width, height };
            }

            function renderWordCloud(canvas, terms, palette, emptyEl) {
                const list = termsToWordList(terms);
                const { width, height } = sizeCanvas(canvas);

                if (!list.length || typeof WordCloud === 'undefined') {
                    canvas.classList.add('hidden');
                    emptyEl.classList.remove('hidden');
                    return;
                }

                canvas.classList.remove('hidden');
                emptyEl.classList.add('hidden');

                WordCloud(canvas, {
                    list,
                    gridSize: Math.round(Math.max(8, 16 * (width / 1024))),
                    weightFactor: (size) => Math.pow(size, 0.65) * (width / 1024) * 18,
                    fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif',
                    color: () => palette[Math.floor(Math.random() * palette.length)],
                    rotateRatio: 0.2,
                    rotationSteps: 2,
                    backgroundColor: '#f8fafc',
                    shrinkToFit: true,
                    minSize: 10,
                });
            }

            function renderCloudMeta(metaEl, data) {
                const totalRecords = resolveCount(data, ['total_records', 'total_documents']);
                const uniqueWords = resolveCount(data, ['unique_words', 'unique_terms']);

                metaEl.textContent = `${totalRecords.toLocaleString()} records · ${uniqueWords.toLocaleString()} unique words`;
            }

            async function loadDashboard() {
                try {
                    const [summary, products, feedback] = await Promise.all([
                        fetchProxy(PROXY.summary),
                        fetchProxy(PROXY.products),
                        fetchProxy(PROXY.feedback),
                    ]);

                    renderSummary(summary);
                    renderCloudMeta(productsMetaEl, products);
                    renderCloudMeta(feedbackMetaEl, feedback);
                    renderWordCloud(productsCanvas, products?.terms, PRODUCT_COLORS, productsEmptyEl);
                    renderWordCloud(feedbackCanvas, feedback?.terms, FEEDBACK_COLORS, feedbackEmptyEl);
                } catch (error) {
                    showGlobalError(error.message || 'Failed to load analytics dashboard.');
                }
            }

            window.addEventListener('resize', () => {
                if (typeof WordCloud === 'undefined') {
                    return;
                }

                Promise.all([
                    fetchProxy(PROXY.products).catch(() => null),
                    fetchProxy(PROXY.feedback).catch(() => null),
                ]).then(([products, feedback]) => {
                    if (products) {
                        renderWordCloud(productsCanvas, products?.terms, PRODUCT_COLORS, productsEmptyEl);
                    }

                    if (feedback) {
                        renderWordCloud(feedbackCanvas, feedback?.terms, FEEDBACK_COLORS, feedbackEmptyEl);
                    }
                });
            });

            document.addEventListener('DOMContentLoaded', loadDashboard);
        })();
    </script>
</body>
</html>
