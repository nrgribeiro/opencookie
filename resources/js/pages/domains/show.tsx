import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import CookieController from '@/actions/App/Http/Controllers/CookieController';
import DomainController from '@/actions/App/Http/Controllers/DomainController';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import { edit as bannerEdit } from '@/routes/banner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index } from '@/routes/domains';
import type { BreadcrumbItem } from '@/types';

interface DomainSummary {
    id: string;
    hostname: string;
    verifyStatus: 'pending' | 'verified' | 'failed';
    bannerLive: boolean;
    lastScannedAt: string | null;
    scheduledScans: boolean;
    createdAt: string | null;
}

interface CookieRow {
    id: number;
    name: string;
    provider: string | null;
    providerUrl: string | null;
    category: string;
    purpose: string | null;
    purposeTranslations: Record<string, string>;
    expiry: string | null;
    type: string;
    sourceDomain: string | null;
    isFirstParty: boolean;
    status: string;
}

const CATEGORIES = ['necessary', 'preferences', 'statistics', 'marketing'] as const;

function TranslationDialog({
    cookie,
    languages,
    onSave,
}: {
    cookie: CookieRow;
    languages: string[];
    onSave: (translations: Record<string, string>) => void;
}) {
    const [open, setOpen] = useState(false);
    const [values, setValues] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            languages.map((l) => [l, cookie.purposeTranslations?.[l] ?? '']),
        ),
    );

    const missing = languages.filter((l) => !(values[l] ?? '').trim()).length;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Translate
                    {missing > 0 && (
                        <Badge variant="secondary" className="ml-2">
                            {missing}
                        </Badge>
                    )}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Purpose translations — {cookie.name}</DialogTitle>
                <DialogDescription>
                    Per-language text shown in the cookie declaration.
                </DialogDescription>
                <div className="space-y-3 py-2">
                    {languages.map((lang) => (
                        <div key={lang} className="grid gap-1">
                            <span className="text-xs font-medium uppercase text-muted-foreground">
                                {lang}
                            </span>
                            <input
                                type="text"
                                className="rounded border bg-background px-3 py-2 text-sm"
                                value={values[lang] ?? ''}
                                maxLength={1000}
                                onChange={(e) =>
                                    setValues((prev) => ({ ...prev, [lang]: e.target.value }))
                                }
                            />
                        </div>
                    ))}
                </div>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                        onClick={() => {
                            onSave(values);
                            setOpen(false);
                        }}
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ProviderUrlDialog({
    cookie,
    onSave,
}: {
    cookie: CookieRow;
    onSave: (url: string | null) => void;
}) {
    const [open, setOpen] = useState(false);
    const [value, setValue] = useState(cookie.providerUrl ?? '');

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    {cookie.providerUrl ? 'Provider link' : 'Add link'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Provider URL — {cookie.name}</DialogTitle>
                <DialogDescription>
                    Shown in the banner&apos;s details modal. Visitors can follow it to
                    the third-party provider&apos;s policy page.
                </DialogDescription>
                <div className="grid gap-2 py-2">
                    <Label htmlFor={`provurl-${cookie.id}`}>URL</Label>
                    <input
                        id={`provurl-${cookie.id}`}
                        type="url"
                        inputMode="url"
                        placeholder="https://provider.example/privacy"
                        className="rounded border bg-background px-3 py-2 text-sm"
                        value={value}
                        maxLength={2048}
                        onChange={(e) => setValue(e.target.value)}
                    />
                </div>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>
                    {cookie.providerUrl && (
                        <Button
                            variant="outline"
                            onClick={() => {
                                onSave(null);
                                setValue('');
                                setOpen(false);
                            }}
                        >
                            Remove
                        </Button>
                    )}
                    <Button
                        onClick={() => {
                            onSave(value.trim() ? value.trim() : null);
                            setOpen(false);
                        }}
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function CopyButton({ value }: { value: string }) {
    const [copied, setCopied] = useState(false);
    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => {
                navigator.clipboard.writeText(value);
                setCopied(true);
                setTimeout(() => setCopied(false), 1500);
            }}
        >
            {copied ? 'Copied' : 'Copy'}
        </Button>
    );
}

export default function DomainsShow({
    domain,
    snippet,
    declarationSnippet,
    verification,
    latestScan,
    cookies,
    cookieCounts,
    languages,
}: {
    domain: DomainSummary;
    snippet: string;
    declarationSnippet: string;
    verification: {
        method: string;
        token: string;
        verifiedAt: string | null;
        lastCheckedAt: string | null;
        lastError: string | null;
    } | null;
    latestScan: {
        status: string;
        pagesCrawled: number;
        finishedAt: string | null;
        error: string | null;
    } | null;
    cookies: CookieRow[];
    cookieCounts: { total: number; unclassified: number; missingTranslations: number };
    languages: string[];
}) {
    const errors = usePage().props.errors as Record<string, string>;
    const [method, setMethod] = useState('dns_txt');
    const [verifying, setVerifying] = useState(false);
    const [scanning, setScanning] = useState(false);

    const runVerify = () => {
        setVerifying(true);
        router.post(
            DomainController.verify(domain.id).url,
            { method },
            { preserveScroll: true, onFinish: () => setVerifying(false) },
        );
    };

    const runScan = () => {
        setScanning(true);
        router.post(ScanController.store(domain.id).url, {}, {
            preserveScroll: true,
            onFinish: () => setScanning(false),
        });
    };

    const setCookieCategory = (cookie: CookieRow, category: string) => {
        router.patch(
            CookieController.update(cookie.id).url,
            { category },
            { preserveScroll: true },
        );
    };

    const saveTranslations = (
        cookie: CookieRow,
        translations: Record<string, string>,
    ) => {
        router.patch(
            CookieController.update(cookie.id).url,
            {
                category:
                    cookie.category === 'unclassified' ? 'necessary' : cookie.category,
                provider: cookie.provider ?? undefined,
                providerUrl: cookie.providerUrl ?? undefined,
                purpose: cookie.purpose ?? undefined,
                purposeTranslations: translations,
            },
            { preserveScroll: true },
        );
    };

    const saveProviderUrl = (cookie: CookieRow, url: string | null) => {
        router.patch(
            CookieController.update(cookie.id).url,
            {
                category:
                    cookie.category === 'unclassified' ? 'necessary' : cookie.category,
                provider: cookie.provider ?? undefined,
                providerUrl: url,
                purpose: cookie.purpose ?? undefined,
            },
            { preserveScroll: true },
        );
    };

    const multiLang = languages.length > 1;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Domains', href: index().url },
        { title: domain.hostname, href: `/domains/${domain.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={domain.hostname} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title={domain.hostname} description="Domain settings and installation." />
                    <div className="flex items-center gap-2">
                        <Badge variant={domain.verifyStatus === 'verified' ? 'default' : 'secondary'}>
                            {domain.verifyStatus}
                        </Badge>
                        <Badge variant={domain.bannerLive ? 'default' : 'outline'}>
                            {domain.bannerLive ? 'Banner live' : 'Banner off'}
                        </Badge>
                        <Button asChild size="sm" variant="outline">
                            <Link href={bannerEdit(domain.id).url}>Edit banner</Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href={`/domains/${domain.id}/consent-logs`}>Consent logs</Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href={`/domains/${domain.id}/settings`}>Settings</Link>
                        </Button>
                    </div>
                </div>

                {/* US-DOM-3 — embed snippet */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Install snippet</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            Paste this as the first script in your site&apos;s <code>&lt;head&gt;</code>.
                        </p>
                        <div className="flex items-start gap-2">
                            <pre className="flex-1 overflow-x-auto rounded-md bg-muted p-3 text-xs">
                                <code>{snippet}</code>
                            </pre>
                            <CopyButton value={snippet} />
                        </div>
                    </CardContent>
                </Card>

                {/* US-DECL-2 — cookie declaration embed snippet */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Cookie declaration embed</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            Paste into your cookie/privacy policy page. The table renders the
                            live declaration from your latest scan and updates automatically.
                            Append <code>?lang=xx</code> to the script URL to force a language.
                        </p>
                        <div className="flex items-start gap-2">
                            <pre className="flex-1 overflow-x-auto rounded-md bg-muted p-3 text-xs">
                                <code>{declarationSnippet}</code>
                            </pre>
                            <CopyButton value={declarationSnippet} />
                        </div>
                    </CardContent>
                </Card>

                {/* US-DOM-2 — verify ownership */}
                {verification && domain.verifyStatus !== 'verified' && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Verify ownership</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <span className="text-sm font-medium">Verification token</span>
                                <div className="flex items-start gap-2">
                                    <pre className="flex-1 overflow-x-auto rounded-md bg-muted p-3 text-xs">
                                        <code>{verification.token}</code>
                                    </pre>
                                    <CopyButton value={verification.token} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <span className="text-sm font-medium">Method</span>
                                <Select value={method} onValueChange={setMethod}>
                                    <SelectTrigger className="max-w-xs">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="dns_txt">DNS TXT record</SelectItem>
                                        <SelectItem value="meta_tag">Meta tag</SelectItem>
                                        <SelectItem value="file">File upload</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">
                                    {method === 'dns_txt' &&
                                        `Add a TXT record: cmp-site-verification=${verification.token}`}
                                    {method === 'meta_tag' &&
                                        `Add to <head>: <meta name="cmp-site-verification" content="${verification.token}">`}
                                    {method === 'file' &&
                                        `Upload the token to https://${domain.hostname}/.well-known/cmp-verification.txt`}
                                </p>
                                <InputError message={errors.verification} />
                            </div>

                            <Button onClick={runVerify} disabled={verifying}>
                                {verifying ? 'Checking…' : 'Verify now'}
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {verification && domain.verifyStatus === 'verified' && (
                    <Alert>
                        <AlertTitle>Ownership verified</AlertTitle>
                        <AlertDescription>
                            Verified via {verification.method.replace('_', ' ')}
                            {verification.verifiedAt
                                ? ` on ${new Date(verification.verifiedAt).toLocaleDateString()}`
                                : ''}
                            .
                        </AlertDescription>
                    </Alert>
                )}

                {/* US-SCAN-1 — scan trigger + summary */}
                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle className="text-base">Cookie scan</CardTitle>
                        <Button
                            onClick={runScan}
                            disabled={scanning || domain.verifyStatus !== 'verified'}
                            size="sm"
                        >
                            {scanning ? 'Starting…' : 'Run scan'}
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {domain.verifyStatus !== 'verified' && (
                            <p className="text-sm text-muted-foreground">
                                Verify ownership before scanning.
                            </p>
                        )}
                        <InputError message={errors.scan} />
                        {latestScan ? (
                            <div className="flex flex-wrap gap-2 text-sm">
                                <Badge variant={latestScan.status === 'complete' ? 'default' : 'secondary'}>
                                    {latestScan.status}
                                </Badge>
                                <Badge variant="outline">{latestScan.pagesCrawled} pages</Badge>
                                {latestScan.finishedAt && (
                                    <Badge variant="outline">
                                        {new Date(latestScan.finishedAt).toLocaleString()}
                                    </Badge>
                                )}
                                {latestScan.error && (
                                    <span className="text-red-600">{latestScan.error}</span>
                                )}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">No scans yet.</p>
                        )}
                    </CardContent>
                </Card>

                {/* US-SCAN-2/3 — detected cookies + manual override */}
                {cookies.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Cookies ({cookieCounts.total})
                                {cookieCounts.unclassified > 0 && (
                                    <Badge variant="destructive" className="ml-2">
                                        {cookieCounts.unclassified} unclassified
                                    </Badge>
                                )}
                                {cookieCounts.missingTranslations > 0 && (
                                    <Badge variant="secondary" className="ml-2">
                                        {cookieCounts.missingTranslations} missing translation(s)
                                    </Badge>
                                )}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2 pr-4">Name</th>
                                            <th className="py-2 pr-4">Source</th>
                                            <th className="py-2 pr-4">Type</th>
                                            <th className="py-2 pr-4">Expiry</th>
                                            <th className="py-2 pr-4">Category</th>
                                            <th className="py-2 pr-4">Provider URL</th>
                                            {multiLang && <th className="py-2">Translations</th>}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {cookies.map((cookie) => (
                                            <tr key={cookie.id} className="border-b last:border-0">
                                                <td className="py-2 pr-4 font-mono text-xs">{cookie.name}</td>
                                                <td className="py-2 pr-4 text-muted-foreground">
                                                    {cookie.isFirstParty ? '1st party' : cookie.sourceDomain}
                                                </td>
                                                <td className="py-2 pr-4">{cookie.type}</td>
                                                <td className="py-2 pr-4">{cookie.expiry ?? '—'}</td>
                                                <td className="py-2 pr-4">
                                                    <Select
                                                        value={
                                                            cookie.category === 'unclassified'
                                                                ? undefined
                                                                : cookie.category
                                                        }
                                                        onValueChange={(value) =>
                                                            setCookieCategory(cookie, value)
                                                        }
                                                    >
                                                        <SelectTrigger className="h-8 w-40">
                                                            <SelectValue placeholder="Unclassified" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {CATEGORIES.map((c) => (
                                                                <SelectItem key={c} value={c}>
                                                                    {c}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </td>
                                                <td className="py-2 pr-4">
                                                    <ProviderUrlDialog
                                                        cookie={cookie}
                                                        onSave={(url) => saveProviderUrl(cookie, url)}
                                                    />
                                                </td>
                                                {multiLang && (
                                                    <td className="py-2">
                                                        <TranslationDialog
                                                            cookie={cookie}
                                                            languages={languages}
                                                            onSave={(t) =>
                                                                saveTranslations(cookie, t)
                                                            }
                                                        />
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* US-DOM-5 — delete */}
                <Card className="border-red-200 dark:border-red-200/20">
                    <CardHeader>
                        <CardTitle className="text-base text-red-600 dark:text-red-100">
                            Delete domain
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            Removes banner config and scan data. Consent logs are retained per policy.
                        </p>
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button variant="destructive">Delete domain</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>Delete {domain.hostname}?</DialogTitle>
                                <DialogDescription>
                                    This removes the domain, its banner config, scans, and cookie records.
                                    Consent proof logs are kept until the retention period elapses. This cannot be undone.
                                </DialogDescription>
                                <Form {...DomainController.destroy.form(domain.id)}>
                                    {({ processing }) => (
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button variant="secondary">Cancel</Button>
                                            </DialogClose>
                                            <Button variant="destructive" disabled={processing}>
                                                Delete
                                            </Button>
                                        </DialogFooter>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
