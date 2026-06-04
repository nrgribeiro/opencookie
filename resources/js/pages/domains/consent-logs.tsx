import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index as domainsIndex } from '@/routes/domains';
import type { BreadcrumbItem } from '@/types';

interface ConsentRow {
    consentId: string;
    createdAt: string | null;
    method: 'accept_all' | 'reject_all' | 'custom';
    categories: Record<string, boolean>;
    bannerVersion: number;
    policyVersion: number;
    language: string | null;
}

export default function ConsentLogs({
    domain,
    records,
    totalCount,
}: {
    domain: { id: string; hostname: string };
    records: ConsentRow[];
    totalCount: number;
}) {
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');

    const exportUrl = (() => {
        const params = new URLSearchParams();
        if (from) params.set('from', from);
        if (to) params.set('to', to);
        const q = params.toString();
        return `/domains/${domain.id}/consent-logs/export${q ? `?${q}` : ''}`;
    })();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Domains', href: domainsIndex().url },
        { title: domain.hostname, href: `/domains/${domain.id}` },
        { title: 'Consent logs', href: `/domains/${domain.id}/consent-logs` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${domain.hostname} — consent logs`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Consent logs"
                    description={`${totalCount} records stored. Export for DPA audits.`}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Export CSV</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="from">From</Label>
                                <Input
                                    id="from"
                                    type="date"
                                    value={from}
                                    onChange={(e) => setFrom(e.target.value)}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="to">To</Label>
                                <Input
                                    id="to"
                                    type="date"
                                    value={to}
                                    onChange={(e) => setTo(e.target.value)}
                                />
                            </div>
                        </div>
                        <Button asChild>
                            <a href={exportUrl}>Download CSV</a>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Recent records ({records.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {records.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No consent events yet.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2 pr-4">When</th>
                                            <th className="py-2 pr-4">Method</th>
                                            <th className="py-2 pr-4">Categories</th>
                                            <th className="py-2 pr-4">Banner v.</th>
                                            <th className="py-2 pr-4">Policy v.</th>
                                            <th className="py-2 pr-4">Lang</th>
                                            <th className="py-2">Consent ID</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {records.map((r) => {
                                            const granted = Object.entries(r.categories)
                                                .filter(([, v]) => v)
                                                .map(([k]) => k);
                                            return (
                                                <tr
                                                    key={r.consentId}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="py-2 pr-4 text-xs">
                                                        {r.createdAt
                                                            ? new Date(r.createdAt).toLocaleString()
                                                            : '—'}
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        <Badge variant="outline">{r.method}</Badge>
                                                    </td>
                                                    <td className="py-2 pr-4 text-xs">
                                                        {granted.join(', ') || '—'}
                                                    </td>
                                                    <td className="py-2 pr-4">{r.bannerVersion}</td>
                                                    <td className="py-2 pr-4">{r.policyVersion}</td>
                                                    <td className="py-2 pr-4">{r.language ?? '—'}</td>
                                                    <td className="py-2 font-mono text-xs">
                                                        {r.consentId}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <p className="text-xs text-muted-foreground">
                    Records are retained for 24 months and then automatically purged.{' '}
                    <Link
                        href={`/domains/${domain.id}`}
                        className="underline"
                    >
                        Back to domain
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
