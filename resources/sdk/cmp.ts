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
    retention: string | null;
    dataController: string | null;
    gdprPortalUrl: string | null;
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

// --- Auto-block known third-party tags (US-SDK-1 AC2, "where feasible") -----
// Intercepts scripts inserted via the DOM before they connect — and thus before
// they execute — and neutralizes known tracking vendors until consent. Static
// <script src> already in the served HTML can't be caught this way; those should
// still be tagged manually with type="text/plain" data-cmp-category.

const VENDOR_CATEGORY: { re: RegExp; category: string }[] = [
    { re: /googletagmanager\.com|google-analytics\.com|\/gtag\/js/i, category: 'statistics' },
    { re: /static\.hotjar\.com|script\.hotjar\.com/i, category: 'statistics' },
    { re: /clarity\.ms/i, category: 'statistics' },
    { re: /cdn\.segment\.com/i, category: 'statistics' },
    { re: /connect\.facebook\.net|facebook\.com\/tr/i, category: 'marketing' },
    { re: /doubleclick\.net|googlesyndication\.com|googleadservices\.com/i, category: 'marketing' },
];

function vendorCategory(src: string): string | null {
    for (const v of VENDOR_CATEGORY) if (v.re.test(src)) return v.category;
    return null;
}

function consentGrants(category: string): boolean {
    if (category === 'necessary') return true;
    return !!loadStored()?.categories?.[category];
}

function maybeBlockScript(node: Node): void {
    if (!(node instanceof HTMLScriptElement)) return;
    if (node.type === 'text/plain') return; // already blocked or manually tagged
    const src = node.getAttribute('src') ?? '';
    if (!src) return;
    const category = vendorCategory(src);
    if (!category || consentGrants(category)) return;
    node.type = 'text/plain';
    node.setAttribute('data-cmp-category', category);
}

let autoBlockInstalled = false;

function installAutoBlock(): void {
    if (autoBlockInstalled) return;
    autoBlockInstalled = true;

    const proto = Node.prototype as Node & {
        appendChild: <T extends Node>(n: T) => T;
        insertBefore: <T extends Node>(n: T, ref: Node | null) => T;
    };
    const origAppend = proto.appendChild;
    const origInsert = proto.insertBefore;

    proto.appendChild = function <T extends Node>(this: Node, n: T): T {
        try {
            maybeBlockScript(n);
        } catch {
            /* ignore */
        }
        return origAppend.call(this, n) as T;
    };
    proto.insertBefore = function <T extends Node>(this: Node, n: T, ref: Node | null): T {
        try {
            maybeBlockScript(n);
        } catch {
            /* ignore */
        }
        return origInsert.call(this, n, ref) as T;
    };
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
        consentTextHash: consentTextHash(),
        language: currentLang(),
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
        body: JSON.stringify({ bannerVersion: config?.bannerVersion ?? 0, language: currentLang() }),
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
    whenBody(renderReopenWidget);
}

// --- UI ---------------------------------------------------------------------

let bannerEl: HTMLElement | null = null;

const FALLBACK_LABELS: Record<string, string> = {
    details: 'Cookie details',
    close: 'Close',
    providerLabel: 'Provider',
    expiryLabel: 'Expiry',
    retentionLabel: 'Retention',
    controllerLabel: 'Data controller',
    gdprPortalLabel: 'Privacy & GDPR rights',
    learnMore: 'More info',
    noCookies: 'No cookies in this category.',
    sessionExpiry: 'Session',
    tabCookies: 'Cookies',
    tabAbout: 'About cookies',
    manage: 'Cookie settings',
};

const DEFAULT_ABOUT_COOKIES =
    'Cookies are small text files that can be used by websites to make a user’s experience more efficient.\n\n' +
    'Under the law, we can store cookies on your device if they are strictly necessary for the operation of this site. ' +
    'For all other types of cookies we need your permission.\n' +
    'This site uses different types of cookies. Some cookies are placed by third-party services that appear on our pages.\n' +
    'You can at any time change or withdraw your consent from the cookie declaration on our website.\n\n' +
    'Learn more about who we are, how you can contact us and how we process personal data in our Privacy Policy.\n\n' +
    'Please state your consent ID and date when you contact us regarding your consent.';

// Auto-detect visitor locale (US-BAN-4): match navigator languages against the
// configured set (exact, then base-language), falling back to the default.
function currentLang(): string {
    const langs = config?.languages ?? [];
    const def = config?.defaultLanguage ?? 'en';
    if (!langs.length) return def;

    const nav = (navigator.languages && navigator.languages.length
        ? navigator.languages
        : [navigator.language]
    ).filter(Boolean);

    for (const raw of nav) {
        const lc = raw.toLowerCase();
        const base = lc.split('-')[0];
        const hit =
            langs.find((l) => l.toLowerCase() === lc) ??
            langs.find((l) => l.toLowerCase().split('-')[0] === base);
        if (hit) return hit;
    }
    return def;
}

// FNV-1a 32-bit → hex. Deterministic, synchronous. Used to record a fingerprint
// of the consent text the visitor actually saw (US-LOG-1 proof) — not security.
function hashStr(s: string): string {
    let h = 0x811c9dc5;
    for (let i = 0; i < s.length; i++) {
        h ^= s.charCodeAt(i);
        h = Math.imul(h, 0x01000193);
    }
    return (h >>> 0).toString(16).padStart(8, '0');
}

// Hash of the exact text shown in the visitor's language, for consent proof.
function consentTextHash(): string {
    const parts = [
        currentLang(),
        t('title'),
        t('body'),
        t('acceptAll'),
        t('rejectAll'),
        t('customize'),
        config?.policyUrl ?? '',
        aboutCookiesText(),
    ];
    return hashStr(parts.join('␟'));
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

function aboutCookiesText(): string {
    const lang = currentLang();
    const def = config?.defaultLanguage ?? 'en';
    return (
        config?.content?.[lang]?.aboutCookies ||
        config?.content?.[def]?.aboutCookies ||
        DEFAULT_ABOUT_COOKIES
    );
}

function removeBanner(): void {
    bannerEl?.remove();
    bannerEl = null;
}

function whenBody(fn: () => void): void {
    if (document.body) fn();
    else document.addEventListener('DOMContentLoaded', fn, { once: true });
}

// Persistent re-open widget (US-SDK-5 / Art. 7(3)): always available once a
// choice exists so withdrawal is as easy as giving consent.
let widgetEl: HTMLElement | null = null;

function renderReopenWidget(): void {
    if (widgetEl) return;
    const dark = config?.layout?.theme === 'dark';
    const accent = config?.layout?.colors?.accent ?? '#2563eb';
    const right = config?.layout?.position === 'bottom-right';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('aria-label', t('manage'));
    btn.title = t('manage');
    // Simple monochrome cookie glyph; inherits button color via currentColor.
    btn.innerHTML =
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"' +
        ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
        '<path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5Z"/>' +
        '<path d="M8.5 8.5h.01"/><path d="M16 11h.01"/><path d="M11 15h.01"/></svg>';
    btn.style.cssText = [
        'position:fixed',
        'bottom:16px',
        right ? 'right:16px' : 'left:16px',
        'width:44px',
        'height:44px',
        'border-radius:50%',
        'border:0',
        'cursor:pointer',
        'display:flex',
        'align-items:center',
        'justify-content:center',
        'padding:0',
        'z-index:2147483646',
        'box-shadow:0 4px 16px rgba(0,0,0,.25)',
        dark ? 'background:#262626;color:#fafafa' : 'background:#fff;color:#171717',
        `border:1px solid ${accent}`,
    ].join(';');
    btn.addEventListener('click', () => api.showSettings());
    document.body.appendChild(btn);
    widgetEl = btn;
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
        'display:flex',
        'flex-direction:column',
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

    const tabs = document.createElement('div');
    tabs.setAttribute('role', 'tablist');
    tabs.style.cssText = [
        'display:flex',
        'gap:0',
        'padding:0 22px',
        'border-bottom:1px solid ' + (dark ? '#2a2a2a' : '#eee'),
    ].join(';');

    const cookiesPanel = buildCookiesPanel(dark, accent);
    const aboutPanel = buildAboutPanel(dark);

    const tabCookies = makeTab(t('tabCookies'), true, dark, accent);
    const tabAbout = makeTab(t('tabAbout'), false, dark, accent);
    tabs.append(tabCookies, tabAbout);

    const body = document.createElement('div');
    body.style.cssText = 'flex:1;overflow:auto;padding:18px 22px';
    body.append(cookiesPanel);

    const showCookies = (): void => {
        markActiveTab(tabCookies, true, dark, accent);
        markActiveTab(tabAbout, false, dark, accent);
        body.replaceChildren(cookiesPanel);
    };
    const showAbout = (): void => {
        markActiveTab(tabCookies, false, dark, accent);
        markActiveTab(tabAbout, true, dark, accent);
        body.replaceChildren(aboutPanel);
    };
    tabCookies.addEventListener('click', showCookies);
    tabAbout.addEventListener('click', showAbout);

    panel.append(header, tabs, body);
    overlay.append(panel);
    document.body.appendChild(overlay);
    detailsModalEl = overlay;
    document.addEventListener('keydown', detailsKeyHandler);
}

function makeTab(label: string, active: boolean, dark: boolean, accent: string): HTMLButtonElement {
    const b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('role', 'tab');
    b.textContent = label;
    markActiveTab(b, active, dark, accent);
    return b;
}

function markActiveTab(btn: HTMLButtonElement, active: boolean, dark: boolean, accent: string): void {
    btn.setAttribute('aria-selected', active ? 'true' : 'false');
    btn.style.cssText = [
        'appearance:none',
        'background:transparent',
        'border:0',
        'padding:12px 16px',
        'margin-bottom:-1px',
        'cursor:pointer',
        'font:inherit',
        'font-weight:' + (active ? '600' : '500'),
        'color:' + (active ? accent : dark ? '#cfcfcf' : '#444'),
        'border-bottom:2px solid ' + (active ? accent : 'transparent'),
    ].join(';');
}

function buildAboutPanel(dark: boolean): HTMLElement {
    const wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-direction:column;gap:12px;max-width:680px';

    const text = aboutCookiesText();
    text.split(/\n\n+/).forEach((para) => {
        const p = document.createElement('p');
        p.style.cssText = 'margin:0;white-space:pre-wrap;color:' + (dark ? '#e5e5e5' : '#222');
        p.textContent = para.trim();
        wrap.append(p);
    });

    return wrap;
}

function buildCookiesPanel(dark: boolean, accent: string): HTMLElement {
    const wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-direction:column;gap:18px';

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
                '<th style="padding:8px 10px;font-weight:600">' + escapeHtml(t('retentionLabel')) + '</th>' +
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
                    providerCell.append(document.createTextNode(providerText));
                }
                if (cookie.dataController) {
                    const ctrl = document.createElement('div');
                    ctrl.style.cssText = 'font-size:11px;opacity:.6;margin-top:2px';
                    ctrl.textContent = t('controllerLabel') + ': ' + cookie.dataController;
                    providerCell.append(ctrl);
                }
                if (cookie.gdprPortalUrl) {
                    const portal = document.createElement('a');
                    portal.href = cookie.gdprPortalUrl;
                    portal.target = '_blank';
                    portal.rel = 'noopener noreferrer';
                    portal.textContent = t('gdprPortalLabel');
                    portal.style.cssText = `display:block;font-size:11px;margin-top:2px;color:${accent};text-decoration:underline`;
                    providerCell.append(portal);
                }

                const purposeCell = document.createElement('td');
                purposeCell.style.cssText = 'padding:8px 10px;vertical-align:top';
                purposeCell.textContent = cookiePurpose(cookie);

                const expiryCell = document.createElement('td');
                expiryCell.style.cssText = 'padding:8px 10px;vertical-align:top;white-space:nowrap';
                expiryCell.textContent = cookie.expiry || t('sessionExpiry');

                const retentionCell = document.createElement('td');
                retentionCell.style.cssText = 'padding:8px 10px;vertical-align:top;white-space:nowrap';
                retentionCell.textContent = cookie.retention || '—';

                tr.append(nameCell, providerCell, purposeCell, expiryCell, retentionCell);
                tbody.append(tr);
            });

            table.append(thead, tbody);
            tableWrap.append(table);
            section.append(tableWrap);
        }

        wrap.append(section);
    });

    return wrap;
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
        // Reopen (e.g. floating widget) shows the banner with the category
        // options hidden; the Customize button reveals them. Auto-opening the
        // panel here made Customize a no-op (panel already visible).
        if (!bannerEl) renderBanner();
    },
    showDetails: () => renderDetailsModal(),
};
window.CMP = api;

async function boot(): Promise<void> {
    setConsentDefaults();
    installAutoBlock();

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
        whenBody(renderReopenWidget);
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderBanner, { once: true });
    } else {
        renderBanner();
    }
}

void boot();
