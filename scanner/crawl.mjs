// Headless cookie/tracker crawler (US-SCAN-1).
//
// Usage:  node scanner/crawl.mjs '<json-config>'
//   config = { "url": "https://example.com", "maxPages": 100, "pageTimeoutMs": 15000 }
//
// Emits a single JSON object to stdout:
//   { "pagesCrawled": N, "items": [ { name, type, sourceDomain, isFirstParty, expiry, provider } ] }
//
// type ∈ http | script | local_storage | session_storage | pixel
//
// Requires Playwright + Chromium in the environment:
//   npm i -D playwright && npx playwright install chromium
//
// Failures are reported as JSON on stderr ({ "error": "..." }) with a non-zero
// exit code so the PHP driver can surface a reason (US-SCAN-1 AC5).

import { chromium } from 'playwright';

function readConfig() {
    const raw = process.argv[2];
    if (!raw) throw new Error('missing config argument');
    const cfg = JSON.parse(raw);
    if (!cfg.url) throw new Error('config.url is required');
    return {
        url: cfg.url,
        maxPages: Math.max(1, Math.min(Number(cfg.maxPages) || 100, 100)),
        pageTimeoutMs: Number(cfg.pageTimeoutMs) || 15000,
    };
}

function baseHost(hostname) {
    // Registrable-ish base: last two labels. Good enough for first-party check.
    const parts = hostname.split('.').filter(Boolean);
    return parts.slice(-2).join('.');
}

function isFirstParty(candidate, rootBase) {
    if (!candidate) return true;
    const c = candidate.replace(/^\./, '').toLowerCase();
    return c === rootBase || c.endsWith('.' + rootBase);
}

function expiryString(expires) {
    // Playwright cookie.expires: -1 = session, else unix seconds.
    if (expires === undefined || expires === null || expires === -1) return 'session';
    try {
        return new Date(expires * 1000).toISOString();
    } catch {
        return 'session';
    }
}

async function main() {
    const cfg = readConfig();
    const root = new URL(cfg.url);
    const rootBase = baseHost(root.hostname);

    const browser = await chromium.launch({ args: ['--no-sandbox'] });
    const context = await browser.newContext();

    const toVisit = [root.href];
    const queued = new Set([normalize(root.href)]);
    const visited = new Set();

    // Third-party network hosts seen across the whole crawl.
    const thirdPartyHosts = new Map(); // host -> 'script' | 'pixel'
    context.on('request', (req) => {
        try {
            const u = new URL(req.url());
            if (isFirstParty(u.hostname, rootBase)) return;
            const type = req.resourceType();
            if (type === 'image') {
                if (!thirdPartyHosts.has(u.hostname)) thirdPartyHosts.set(u.hostname, 'pixel');
            } else if (type === 'script' || type === 'xhr' || type === 'fetch') {
                thirdPartyHosts.set(u.hostname, 'script');
            }
        } catch {
            /* ignore malformed urls */
        }
    });

    const storage = []; // { name, type } for local/session storage keys
    const storageSeen = new Set();

    let pagesCrawled = 0;

    while (toVisit.length > 0 && pagesCrawled < cfg.maxPages) {
        const next = toVisit.shift();
        const norm = normalize(next);
        if (visited.has(norm)) continue;
        visited.add(norm);

        const page = await context.newPage();
        try {
            await page.goto(next, { waitUntil: 'networkidle', timeout: cfg.pageTimeoutMs });
            pagesCrawled++;

            // Storage keys for this page's origin.
            const keys = await page.evaluate(() => {
                const out = { local: [], session: [] };
                try {
                    for (let i = 0; i < localStorage.length; i++) out.local.push(localStorage.key(i));
                } catch {}
                try {
                    for (let i = 0; i < sessionStorage.length; i++) out.session.push(sessionStorage.key(i));
                } catch {}
                return out;
            });
            for (const k of keys.local) {
                const id = 'local:' + k;
                if (k && !storageSeen.has(id)) { storageSeen.add(id); storage.push({ name: k, type: 'local_storage' }); }
            }
            for (const k of keys.session) {
                const id = 'session:' + k;
                if (k && !storageSeen.has(id)) { storageSeen.add(id); storage.push({ name: k, type: 'session_storage' }); }
            }

            // Discover more same-host links (BFS) until we hit the cap.
            if (queued.size < cfg.maxPages) {
                const hrefs = await page.evaluate(() =>
                    Array.from(document.querySelectorAll('a[href]')).map((a) => a.href),
                );
                for (const href of hrefs) {
                    if (queued.size >= cfg.maxPages) break;
                    try {
                        const u = new URL(href);
                        if (u.protocol !== 'http:' && u.protocol !== 'https:') continue;
                        if (!isFirstParty(u.hostname, rootBase)) continue;
                        const n = normalize(u.href);
                        if (!queued.has(n)) { queued.add(n); toVisit.push(u.href); }
                    } catch {
                        /* ignore */
                    }
                }
            }
        } catch {
            // Page failed to load — skip it, keep crawling the rest.
        } finally {
            await page.close();
        }
    }

    // Cookies accumulated in the context across all pages.
    const cookies = await context.cookies();

    await browser.close();

    const items = [];

    for (const c of cookies) {
        items.push({
            name: c.name,
            type: 'http',
            sourceDomain: (c.domain || '').replace(/^\./, '') || root.hostname,
            isFirstParty: isFirstParty(c.domain || root.hostname, rootBase),
            expiry: expiryString(c.expires),
            provider: null,
        });
    }

    for (const s of storage) {
        items.push({
            name: s.name,
            type: s.type,
            sourceDomain: root.hostname,
            isFirstParty: true,
            expiry: s.type === 'local_storage' ? 'persistent' : 'session',
            provider: null,
        });
    }

    for (const [host, type] of thirdPartyHosts) {
        items.push({
            name: host,
            type,
            sourceDomain: host,
            isFirstParty: false,
            expiry: null,
            provider: null,
        });
    }

    process.stdout.write(JSON.stringify({ pagesCrawled, items }));
}

function normalize(href) {
    try {
        const u = new URL(href);
        u.hash = '';
        return u.href;
    } catch {
        return href;
    }
}

main().catch((err) => {
    process.stderr.write(JSON.stringify({ error: String(err && err.message ? err.message : err) }));
    process.exit(1);
});
