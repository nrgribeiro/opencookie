/**
 * CMP Consent SDK (public, standalone — no framework).
 * Loaded first on customer sites. Blocks non-necessary scripts until consent,
 * renders the banner, captures consent, and emits Google Consent Mode v2 signals.
 * See technical-spec.md §4.3 / §7 and user-stories/05-consent-script-enforcement.md.
 */

type Categories = Record<string, boolean>;

interface CookieDetail {
    name: string;
    provider: string;
    providerUrl: string | null;
    purpose: Record<string, string>;
    expiry: string;
    sourceDomain: string | null;
    isFirstParty: boolean;
}

interface BannerConfig {
    domainId: string;
    bannerVersion: number;
    policyVersion: number;
    consentExpiryDays: number;
    defaultLanguage: string;
    languages: string[];
    policyUrl: string | null;
    layout: { type: string; position: string; theme: string; colors?: { accent?: string } };
    content: Record<string, Record<string, string>>;
    categories: { id: string; required: boolean; name: Record<string, string>; description: Record<string, string> }[];
    cookieDetails: Record<string, CookieDetail[]>;
    consentModeMap: Record<string, string[]> | null;
}

interface StoredConsent {
    consentId: string;
    categories: Categories;
    bannerVersion: number;
    policyVersion: number;
    ts: number;
    exp: number;
}

type ConsentListener = (categories: Categories) => void;

declare global {
    interface Window {
        CMP?: CmpApi;
        dataLayer?: unknown[];
    }
}

interface CmpApi {
    getConsent(): Categories | null;
    onConsentChange(cb: ConsentListener): void;
    showSettings(): void;
    showDetails(): void;
}

const STORAGE_KEY = 'cmp_consent';
const NON_NECESSARY = ['preferences', 'statistics', 'marketing'];
const CONSENT_SIGNALS = ['ad_storage', 'analytics_storage', 'ad_user_data', 'ad_personalization'];

const script = document.currentScript as HTMLScriptElement | null;
const domainId = script?.getAttribute('data-domain') ?? '';
const apiBase = (script?.getAttribute('data-api') ?? originFromScript(script)) + '/v1/c';

const listeners: ConsentListener[] = [];
let config: BannerConfig | null = null;

function originFromScript(el: HTMLScriptElement | null): string {
    try {
        return el ? new URL(el.src).origin : window.location.origin;
    } catch {
        return window.location.origin;
    }
}

// --- Google Consent Mode v2 -------------------------------------------------

function gtag(...args: unknown[]): void {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(args);
}

function setConsentDefaults(): void {
    const denied: Record<string, string> = { wait_for_update: '500' } as Record<string, string>;
    CONSENT_SIGNALS.forEach((s) => (denied[s] = 'denied'));
    gtag('consent', 'default', denied);
}

function updateConsentMode(categories: Categories): void {
    const map = config?.consentModeMap ?? defaultConsentMap();
    const update: Record<string, string> = {};
    CONSENT_SIGNALS.forEach((signal) => {
        const cats = map[signal] ?? [];
        const granted = cats.some((c) => categories[c]);
        update[signal] = granted ? 'granted' : 'denied';
    });
    gtag('consent', 'update', update);
}

function defaultConsentMap(): Record<string, string[]> {
    return {
        analytics_storage: ['statistics'],
        ad_storage: ['marketing'],
        ad_user_data: ['marketing'],
        ad_personalization: ['marketing'],
    };
}

// --- Storage ----------------------------------------------------------------

function loadStored(): StoredConsent | null {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? (JSON.parse(raw) as StoredConsent) : null;
    } catch {
        return null;
    }
}

function persist(consent: StoredConsent): void {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(consent));
    } catch {
        /* ignore */
    }
    document.cookie = `${STORAGE_KEY}=${consent.consentId}; path=/; max-age=${Math.floor((consent.exp - Date.now()) / 1000)}; SameSite=Lax`;
}

function isValid(stored: StoredConsent | null, cfg: BannerConfig | null): boolean {
    if (!stored) return false;
    if (stored.exp < Date.now()) return false;
    if (cfg && (stored.bannerVersion !== cfg.bannerVersion || stored.policyVersion !== cfg.policyVersion)) {
        return false;
    }
    return true;
}

function uuid(): string {
    if (crypto && 'randomUUID' in crypto) return crypto.randomUUID();
    return 'c-' + Math.random().toString(36).slice(2) + Date.now().toString(36);
}

// --- Script gating (US-SDK-1) ----------------------------------------------

function activateScripts(categories: Categories): void {
    const blocked = document.querySelectorAll<HTMLScriptElement>('script[type="text/plain"][data-cmp-category]');
    blocked.forEach((node) => {
        const category = node.getAttribute('data-cmp-category') ?? '';
        if (category === 'necessary' || categories[category]) {
            const real = document.createElement('script');
            for (const attr of Array.from(node.attributes)) {
                if (attr.name === 'type' || attr.name === 'data-cmp-category') continue;
                real.setAttribute(attr.name, attr.value);
            }
            real.type = 'text/javascript';
            if (node.textContent) real.textContent = node.textContent;
            node.parentNode?.replaceChild(real, node);
        }
    });
}

// --- Apply + emit -----------------------------------------------------------

function applyConsent(categories: Categories): void {
    updateConsentMode(categories);
    activateScripts(categories);
    listeners.forEach((cb) => cb(categories));
}

function sendConsent(method: string, categories: Categories, consentId: string): void {
    const body = JSON.stringify({
        consentId,
        method,
        bannerVersion: config?.bannerVersion ?? 0,
        policyVersion: config?.policyVersion ?? 0,
        categories,
        language: config?.defaultLanguage ?? 'en',
        ts: new Date().toISOString(),
    });
    fetch(`${apiBase}/${domainId}/consent`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body,
        keepalive: true,
    }).catch(() => {
        /* fail-safe: tracking already gated; retry best-effort omitted */
    });
}

function sendImpression(): void {
    fetch(`${apiBase}/${domainId}/impression`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ bannerVersion: config?.bannerVersion ?? 0, language: config?.defaultLanguage ?? 'en' }),
        keepalive: true,
    }).catch(() => {});
}

function choose(method: string, categories: Categories): void {
    const cfg = config;
    const exp = Date.now() + (cfg?.consentExpiryDays ?? 365) * 86400000;
    const consentId = loadStored()?.consentId ?? uuid();
    const full: Categories = { necessary: true, ...categories };

    const stored: StoredConsent = {
        consentId,
        categories: full,
        bannerVersion: cfg?.bannerVersion ?? 0,
        policyVersion: cfg?.policyVersion ?? 0,
        ts: Date.now(),
        exp,
    };
    persist(stored);
    applyConsent(full);
    sendConsent(method, full, consentId);
    removeBanner();
}

// --- UI ---------------------------------------------------------------------

let bannerEl: HTMLElement | null = null;

const FALLBACK_LABELS: Record<string, string> = {
    details: 'Cookie details',
    close: 'Close',
    providerLabel: 'Provider',
    expiryLabel: 'Expiry',
    learnMore: 'More info',
    noCookies: 'No cookies in this category.',
    sessionExpiry: 'Session',
};

function currentLang(): string {
    return config?.defaultLanguage ?? 'en';
}

function t(key: string): string {
    const lang = currentLang();
    return config?.content?.[lang]?.[key] ?? FALLBACK_LABELS[key] ?? key;
}

function categoryLabel(id: string): string {
    const lang = currentLang();
    const cat = config?.categories?.find((c) => c.id === id);
    return cat?.name?.[lang] ?? id;
}

function categoryDescription(id: string): string {
    const lang = currentLang();
    const cat = config?.categories?.find((c) => c.id === id);
    return cat?.description?.[lang] ?? '';
}

function cookiePurpose(cookie: CookieDetail): string {
    const lang = currentLang();
    return cookie.purpose?.[lang] ?? cookie.purpose?.[config?.defaultLanguage ?? 'en'] ?? '';
}

function removeBanner(): void {
    bannerEl?.remove();
    bannerEl = null;
}

function renderBanner(): void {
    if (bannerEl) return;
    const accent = config?.layout?.colors?.accent ?? '#2563eb';
    const dark = config?.layout?.theme === 'dark';

    const root = document.createElement('div');
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-label', 'Cookie consent');
    root.style.cssText = [
        'position:fixed',
        'bottom:16px',
        config?.layout?.position === 'bottom-right' ? 'right:16px' : 'left:16px',
        'max-width:420px',
        'z-index:2147483647',
        'padding:16px',
        'border-radius:12px',
        'box-shadow:0 8px 30px rgba(0,0,0,.2)',
        'font:14px/1.5 system-ui,sans-serif',
        dark ? 'background:#171717;color:#fafafa' : 'background:#fff;color:#171717',
    ].join(';');

    const title = document.createElement('p');
    title.textContent = t('title');
    title.style.cssText = 'font-weight:600;margin:0 0 6px';

    const body = document.createElement('p');
    body.textContent = t('body');
    body.style.cssText = 'margin:0 0 12px;opacity:.85';

    const actions = document.createElement('div');
    actions.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap';

    // Equal prominence: Accept and Reject share identical styling (US-SDK / BAN-2).
    const primary = `appearance:none;border:0;border-radius:8px;padding:8px 14px;font-weight:600;cursor:pointer;color:#fff;background:${accent}`;
    const accept = button(t('acceptAll'), primary, () => choose('accept_all', allCategories(true)));
    const reject = button(t('rejectAll'), primary, () => choose('reject_all', allCategories(false)));
    const customize = button(
        t('customize'),
        `appearance:none;border:1px solid ${dark ? '#444' : '#ccc'};border-radius:8px;padding:8px 14px;cursor:pointer;background:transparent;color:inherit`,
        () => renderCustomize(root),
    );

    actions.append(accept, reject, customize);
    root.append(title, body, actions);

    const footer = document.createElement('div');
    footer.style.cssText = 'display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:10px;font-size:12px;opacity:.75';

    const detailsLink = document.createElement('a');
    detailsLink.href = '#';
    detailsLink.textContent = t('details');
    detailsLink.style.cssText = 'color:inherit;text-decoration:underline;cursor:pointer';
    detailsLink.addEventListener('click', (e) => {
        e.preventDefault();
        renderDetailsModal();
    });
    footer.append(detailsLink);

    if (config?.policyUrl) {
        const link = document.createElement('a');
        link.href = config.policyUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = 'Privacy & cookie policy';
        link.style.cssText = 'color:inherit;text-decoration:underline';
        footer.append(link);
    }

    root.append(footer);
    document.body.appendChild(root);
    bannerEl = root;
    sendImpression();
}

function renderCustomize(root: HTMLElement): void {
    root.querySelectorAll('[data-cmp-panel]').forEach((n) => n.remove());
    const panel = document.createElement('div');
    panel.setAttribute('data-cmp-panel', '');
    panel.style.cssText = 'margin-top:12px;display:flex;flex-direction:column;gap:8px';

    const state: Categories = {};
    NON_NECESSARY.forEach((cat) => {
        state[cat] = false;
        const row = document.createElement('label');
        row.style.cssText = 'display:flex;align-items:center;gap:8px;text-transform:capitalize';
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.addEventListener('change', () => (state[cat] = cb.checked));
        const span = document.createElement('span');
        span.textContent = cat;
        row.append(cb, span);
        panel.append(row);
    });

    const accent = config?.layout?.colors?.accent ?? '#2563eb';
    const save = button(
        'Save choices',
        `appearance:none;border:0;border-radius:8px;padding:8px 14px;font-weight:600;cursor:pointer;color:#fff;background:${accent}`,
        () => choose('custom', { ...state }),
    );

    const detailsBtn = button(
        t('details'),
        'appearance:none;border:0;background:transparent;color:inherit;text-decoration:underline;cursor:pointer;padding:0;font:inherit',
        () => renderDetailsModal(),
    );

    const row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;gap:12px;flex-wrap:wrap';
    row.append(save, detailsBtn);
    panel.append(row);
    root.append(panel);
}

// --- Details modal (cookies per category) ----------------------------------

let detailsModalEl: HTMLElement | null = null;

function closeDetailsModal(): void {
    detailsModalEl?.remove();
    detailsModalEl = null;
    document.removeEventListener('keydown', detailsKeyHandler);
}

function detailsKeyHandler(e: KeyboardEvent): void {
    if (e.key === 'Escape') closeDetailsModal();
}

function escapeHtml(s: string): string {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

function categoryOrder(): string[] {
    const fromConfig = (config?.categories ?? []).map((c) => c.id);
    const fromCookies = Object.keys(config?.cookieDetails ?? {});
    const seen = new Set<string>();
    const ordered: string[] = [];
    for (const id of [...fromConfig, ...fromCookies]) {
        if (!seen.has(id)) {
            seen.add(id);
            ordered.push(id);
        }
    }
    return ordered;
}

function renderDetailsModal(): void {
    if (detailsModalEl) return;
    const dark = config?.layout?.theme === 'dark';
    const accent = config?.layout?.colors?.accent ?? '#2563eb';

    const overlay = document.createElement('div');
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', t('details'));
    overlay.style.cssText = [
        'position:fixed',
        'inset:0',
        'z-index:2147483647',
        'background:rgba(0,0,0,.55)',
        'display:flex',
        'align-items:center',
        'justify-content:center',
        'padding:20px',
        'font:14px/1.5 system-ui,sans-serif',
    ].join(';');
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeDetailsModal();
    });

    const panel = document.createElement('div');
    panel.style.cssText = [
        'width:100%',
        'max-width:860px',
        'max-height:90vh',
        'overflow:auto',
        'border-radius:14px',
        'box-shadow:0 16px 60px rgba(0,0,0,.4)',
        dark ? 'background:#171717;color:#fafafa' : 'background:#fff;color:#171717',
    ].join(';');

    const header = document.createElement('div');
    header.style.cssText = [
        'display:flex',
        'align-items:center',
        'justify-content:space-between',
        'padding:18px 22px',
        'border-bottom:1px solid ' + (dark ? '#2a2a2a' : '#eee'),
        'position:sticky',
        'top:0',
        dark ? 'background:#171717' : 'background:#fff',
    ].join(';');

    const heading = document.createElement('h2');
    heading.textContent = t('details');
    heading.style.cssText = 'margin:0;font-size:18px;font-weight:600';

    const close = button(
        '×',
        'appearance:none;border:0;background:transparent;color:inherit;font-size:24px;line-height:1;cursor:pointer;padding:4px 8px',
        closeDetailsModal,
    );
    close.setAttribute('aria-label', t('close'));
    header.append(heading, close);

    const body = document.createElement('div');
    body.style.cssText = 'padding:18px 22px;display:flex;flex-direction:column;gap:18px';

    const details = config?.cookieDetails ?? {};

    categoryOrder().forEach((catId) => {
        const cookies = details[catId] ?? [];

        const section = document.createElement('section');
        section.style.cssText = 'display:flex;flex-direction:column;gap:8px';

        const heading = document.createElement('h3');
        heading.textContent = categoryLabel(catId) + ' (' + cookies.length + ')';
        heading.style.cssText = `margin:0;font-size:15px;font-weight:600;color:${accent}`;
        section.append(heading);

        const desc = categoryDescription(catId);
        if (desc) {
            const p = document.createElement('p');
            p.textContent = desc;
            p.style.cssText = 'margin:0;font-size:13px;opacity:.8';
            section.append(p);
        }

        if (cookies.length === 0) {
            const empty = document.createElement('p');
            empty.textContent = t('noCookies');
            empty.style.cssText = 'margin:6px 0 0;font-size:13px;opacity:.6;font-style:italic';
            section.append(empty);
        } else {
            const tableWrap = document.createElement('div');
            tableWrap.style.cssText = 'overflow-x:auto;border:1px solid ' + (dark ? '#2a2a2a' : '#eee') + ';border-radius:8px';

            const table = document.createElement('table');
            table.style.cssText = 'width:100%;border-collapse:collapse;font-size:13px';

            const thead = document.createElement('thead');
            thead.innerHTML =
                '<tr style="text-align:left;background:' + (dark ? '#202020' : '#f7f7f7') + '">' +
                '<th style="padding:8px 10px;font-weight:600">Cookie</th>' +
                '<th style="padding:8px 10px;font-weight:600">' + escapeHtml(t('providerLabel')) + '</th>' +
                '<th style="padding:8px 10px;font-weight:600">Purpose</th>' +
                '<th style="padding:8px 10px;font-weight:600">' + escapeHtml(t('expiryLabel')) + '</th>' +
                '</tr>';

            const tbody = document.createElement('tbody');
            cookies.forEach((cookie) => {
                const tr = document.createElement('tr');
                tr.style.cssText = 'border-top:1px solid ' + (dark ? '#2a2a2a' : '#eee');

                const nameCell = document.createElement('td');
                nameCell.style.cssText = 'padding:8px 10px;font-family:ui-monospace,monospace;font-size:12px;vertical-align:top;word-break:break-all';
                nameCell.textContent = cookie.name;

                const providerCell = document.createElement('td');
                providerCell.style.cssText = 'padding:8px 10px;vertical-align:top';
                const providerText = cookie.provider || (cookie.sourceDomain ?? (cookie.isFirstParty ? '1st party' : ''));
                if (cookie.providerUrl) {
                    const a = document.createElement('a');
                    a.href = cookie.providerUrl;
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.textContent = providerText || t('learnMore');
                    a.style.cssText = `color:${accent};text-decoration:underline`;
                    providerCell.append(a);
                } else {
                    providerCell.textContent = providerText;
                }

                const purposeCell = document.createElement('td');
                purposeCell.style.cssText = 'padding:8px 10px;vertical-align:top';
                purposeCell.textContent = cookiePurpose(cookie);

                const expiryCell = document.createElement('td');
                expiryCell.style.cssText = 'padding:8px 10px;vertical-align:top;white-space:nowrap';
                expiryCell.textContent = cookie.expiry || t('sessionExpiry');

                tr.append(nameCell, providerCell, purposeCell, expiryCell);
                tbody.append(tr);
            });

            table.append(thead, tbody);
            tableWrap.append(table);
            section.append(tableWrap);
        }

        body.append(section);
    });

    panel.append(header, body);
    overlay.append(panel);
    document.body.appendChild(overlay);
    detailsModalEl = overlay;
    document.addEventListener('keydown', detailsKeyHandler);
}

function button(label: string, css: string, onClick: () => void): HTMLButtonElement {
    const b = document.createElement('button');
    b.type = 'button';
    b.textContent = label;
    b.style.cssText = css;
    b.addEventListener('click', onClick);
    return b;
}

function allCategories(value: boolean): Categories {
    const out: Categories = { necessary: true };
    NON_NECESSARY.forEach((c) => (out[c] = value));
    return out;
}

// --- Public API + boot ------------------------------------------------------

const api: CmpApi = {
    getConsent: () => loadStored()?.categories ?? null,
    onConsentChange: (cb) => listeners.push(cb),
    showSettings: () => {
        if (!bannerEl) renderBanner();
        if (bannerEl) renderCustomize(bannerEl);
    },
    showDetails: () => renderDetailsModal(),
};
window.CMP = api;

async function boot(): Promise<void> {
    setConsentDefaults();

    if (!domainId) return;

    // Apply a still-valid prior choice without a network round-trip first.
    const stored = loadStored();
    if (stored && stored.exp > Date.now()) {
        applyConsent(stored.categories);
    }

    try {
        const res = await fetch(`${apiBase}/${domainId}/config`, { credentials: 'omit' });
        if (res.ok) config = (await res.json()) as BannerConfig;
    } catch {
        /* fail-safe: no config → still gate + show fallback banner below */
    }

    if (isValid(stored, config)) {
        applyConsent((stored as StoredConsent).categories);
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderBanner, { once: true });
    } else {
        renderBanner();
    }
}

void boot();
