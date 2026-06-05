import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import BannerController from '@/actions/App/Http/Controllers/BannerController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/domains';
import type { BreadcrumbItem } from '@/types';

type Content = Record<string, Record<string, string>>;

interface BannerConfig {
    version: number;
    status: string;
    layout: { type: string; position: string; theme: string; colors?: { accent?: string }; logo?: string | null };
    content: Content;
    languages: string[];
    defaultLanguage: string;
    policyUrl: string | null;
    consentModeMap: Record<string, string[]> | null;
    publishedAt: string | null;
}

const MULTILINE_KEYS = new Set(['body', 'aboutCookies']);

const CONTENT_FIELDS: { key: string; label: string }[] = [
    { key: 'title', label: 'Title' },
    { key: 'body', label: 'Body' },
    { key: 'acceptAll', label: 'Accept-all button' },
    { key: 'rejectAll', label: 'Reject-all button' },
    { key: 'customize', label: 'Customize button' },
    { key: 'details', label: 'Cookie details link (optional)' },
    { key: 'close', label: 'Close button (optional)' },
    { key: 'aboutCookies', label: 'About cookies (modal tab body)' },
];

export default function BannerBuilder({
    domain,
    config,
    publishedVersion,
}: {
    domain: { id: string; hostname: string };
    config: BannerConfig;
    publishedVersion: number | null;
    requiredContentKeys: string[];
}) {
    const errors = usePage().props.errors as Record<string, string>;
    const [layout, setLayout] = useState(config.layout);
    const [languages, setLanguages] = useState<string[]>(config.languages);
    const [defaultLanguage, setDefaultLanguage] = useState(config.defaultLanguage);
    const [content, setContent] = useState<Content>(config.content);
    const [policyUrl, setPolicyUrl] = useState(config.policyUrl ?? '');
    const [lang, setLang] = useState(config.defaultLanguage);
    const [newLang, setNewLang] = useState('');
    const [busy, setBusy] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Domains', href: index().url },
        { title: domain.hostname, href: show(domain.id).url },
        { title: 'Banner', href: '#' },
    ];

    const payload = () => ({
        layout,
        languages,
        default_language: defaultLanguage,
        content,
        policy_url: policyUrl || null,
        consent_mode_map: config.consentModeMap,
    });

    const save = (onSuccess?: () => void) => {
        setBusy(true);
        router.put(BannerController.update(domain.id).url, payload(), {
            preserveScroll: true,
            onSuccess,
            onFinish: () => setBusy(false),
        });
    };

    const publish = () => {
        // Persist the draft first, then publish it.
        save(() => router.post(BannerController.publish(domain.id).url, {}, { preserveScroll: true }));
    };

    const setField = (key: string, value: string) => {
        setContent((prev) => ({ ...prev, [lang]: { ...prev[lang], [key]: value } }));
    };

    const addLanguage = () => {
        const code = newLang.trim().toLowerCase();
        if (!code || languages.includes(code)) return;
        setLanguages([...languages, code]);
        setContent((prev) => ({ ...prev, [code]: {} }));
        setNewLang('');
    };

    const removeLanguage = (code: string) => {
        if (code === defaultLanguage) return;
        setLanguages(languages.filter((l) => l !== code));
        if (lang === code) setLang(defaultLanguage);
    };

    const c = content[lang] ?? {};

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Banner — ${domain.hostname}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Consent banner" description={`Draft v${config.version}`} />
                    <div className="flex items-center gap-2">
                        {publishedVersion && <Badge variant="outline">Published v{publishedVersion}</Badge>}
                        <Badge variant={config.status === 'published' ? 'default' : 'secondary'}>
                            {config.status}
                        </Badge>
                    </div>
                </div>

                <InputError message={errors.banner} />

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Appearance + content */}
                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Appearance</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Layout</Label>
                                    <Select value={layout.type} onValueChange={(v) => setLayout({ ...layout, type: v })}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="box">Box</SelectItem>
                                            <SelectItem value="bar">Bar</SelectItem>
                                            <SelectItem value="popup">Popup</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors['layout.type']} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Theme</Label>
                                    <Select value={layout.theme} onValueChange={(v) => setLayout({ ...layout, theme: v })}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="light">Light</SelectItem>
                                            <SelectItem value="dark">Dark</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Position</Label>
                                    <Select value={layout.position} onValueChange={(v) => setLayout({ ...layout, position: v })}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="bottom-left">Bottom left</SelectItem>
                                            <SelectItem value="bottom-right">Bottom right</SelectItem>
                                            <SelectItem value="center">Center</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="accent">Accent color</Label>
                                    <Input
                                        id="accent"
                                        type="color"
                                        value={layout.colors?.accent ?? '#2563eb'}
                                        onChange={(e) =>
                                            setLayout({ ...layout, colors: { ...layout.colors, accent: e.target.value } })
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex-row items-center justify-between space-y-0">
                                <CardTitle className="text-base">Content</CardTitle>
                                <Select value={lang} onValueChange={setLang}>
                                    <SelectTrigger className="w-28"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {languages.map((l) => (
                                            <SelectItem key={l} value={l}>{l}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {CONTENT_FIELDS.map((field) => (
                                    <div key={field.key} className="grid gap-2">
                                        <Label htmlFor={field.key}>{field.label}</Label>
                                        {MULTILINE_KEYS.has(field.key) ? (
                                            <textarea
                                                id={field.key}
                                                className={`${field.key === 'aboutCookies' ? 'min-h-48' : 'min-h-20'} rounded-md border bg-transparent p-2 text-sm`}
                                                value={c[field.key] ?? ''}
                                                onChange={(e) => setField(field.key, e.target.value)}
                                            />
                                        ) : (
                                            <Input
                                                id={field.key}
                                                value={c[field.key] ?? ''}
                                                onChange={(e) => setField(field.key, e.target.value)}
                                            />
                                        )}
                                    </div>
                                ))}

                                <div className="grid gap-2">
                                    <Label>Languages</Label>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {languages.map((l) => (
                                            <Badge key={l} variant="outline" className="gap-1">
                                                {l}
                                                {l !== defaultLanguage && (
                                                    <button
                                                        type="button"
                                                        onClick={() => removeLanguage(l)}
                                                        className="ml-1 text-muted-foreground hover:text-foreground"
                                                    >
                                                        ×
                                                    </button>
                                                )}
                                            </Badge>
                                        ))}
                                        <Input
                                            value={newLang}
                                            onChange={(e) => setNewLang(e.target.value)}
                                            placeholder="add (e.g. pt)"
                                            className="h-8 w-28"
                                        />
                                        <Button type="button" size="sm" variant="outline" onClick={addLanguage}>
                                            Add
                                        </Button>
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label>Default language</Label>
                                    <Select value={defaultLanguage} onValueChange={setDefaultLanguage}>
                                        <SelectTrigger className="w-28"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            {languages.map((l) => (
                                                <SelectItem key={l} value={l}>{l}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.default_language} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="policy">Privacy/cookie policy URL</Label>
                                    <Input
                                        id="policy"
                                        value={policyUrl}
                                        onChange={(e) => setPolicyUrl(e.target.value)}
                                        placeholder="https://example.com/cookies"
                                    />
                                    <InputError message={errors.policy_url} />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Preview + actions */}
                    <div className="flex flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Preview ({lang})</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div
                                    className={`rounded-lg border p-4 ${layout.theme === 'dark' ? 'bg-neutral-900 text-neutral-100' : 'bg-white text-neutral-900'}`}
                                >
                                    <p className="font-semibold">{c.title || 'Title'}</p>
                                    <p className="mt-1 text-sm opacity-80">{c.body || 'Body text'}</p>
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        <span
                                            className="rounded-md px-3 py-1.5 text-sm font-medium text-white"
                                            style={{ backgroundColor: layout.colors?.accent ?? '#2563eb' }}
                                        >
                                            {c.acceptAll || 'Accept all'}
                                        </span>
                                        {/* Equal prominence: reject styled same as accept */}
                                        <span
                                            className="rounded-md px-3 py-1.5 text-sm font-medium text-white"
                                            style={{ backgroundColor: layout.colors?.accent ?? '#2563eb' }}
                                        >
                                            {c.rejectAll || 'Reject all'}
                                        </span>
                                        <span className="rounded-md border px-3 py-1.5 text-sm">
                                            {c.customize || 'Customize'}
                                        </span>
                                    </div>
                                </div>
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Reject and Accept render with equal prominence (GDPR — freely given consent).
                                </p>
                            </CardContent>
                        </Card>

                        <div className="flex gap-3">
                            <Button variant="outline" onClick={() => save()} disabled={busy}>
                                Save draft
                            </Button>
                            <Button onClick={publish} disabled={busy}>
                                Publish
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
