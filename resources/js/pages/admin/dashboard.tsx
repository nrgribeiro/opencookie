import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as tiersIndex } from '@/routes/admin/tiers';
import { edit as userEdit, index as usersIndex } from '@/routes/admin/users';
import type { BreadcrumbItem } from '@/types';

interface Owner {
    id: number;
    name: string;
    email: string;
}

interface NonCompliantDomain {
    id: string;
    hostname: string;
    owner: Owner | null;
    failing: { key: string; label: string }[];
}

interface Props {
    stats: {
        users: number;
        domains: number;
        verifiedDomains: number;
        bannersLive: number;
        scans: number;
        consentRecords: number;
        compliantDomains: number;
        nonCompliantDomains: number;
    };
    tiers: { id: number; name: string; slug: string; users: number }[];
    nonCompliant: NonCompliantDomain[];
    recentUsers: { id: number; name: string; email: string; createdAt: string | null }[];
    recentScans: { id: number; hostname: string | null; status: string; finishedAt: string | null }[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin', href: adminDashboard().url }];

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardContent className="py-5">
                <div className="text-2xl font-semibold">{value.toLocaleString()}</div>
                <div className="text-sm text-muted-foreground">{label}</div>
            </CardContent>
        </Card>
    );
}

function fmt(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

export default function AdminDashboard({ stats, tiers, nonCompliant, recentUsers, recentScans }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading title="Platform overview" description="Usage, tiers, and compliance across all accounts." />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Stat label="Users" value={stats.users} />
                    <Stat label="Domains" value={stats.domains} />
                    <Stat label="Verified domains" value={stats.verifiedDomains} />
                    <Stat label="Banners live" value={stats.bannersLive} />
                    <Stat label="Compliant domains" value={stats.compliantDomains} />
                    <Stat label="Non-compliant domains" value={stats.nonCompliantDomains} />
                    <Stat label="Scans run" value={stats.scans} />
                    <Stat label="Consent records" value={stats.consentRecords} />
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Non-compliant domains</CardTitle>
                        <Link href={usersIndex().url} className="text-sm text-muted-foreground hover:underline">
                            Manage users
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {nonCompliant.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Every domain is fully compliant. 🎉</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2 pr-4 font-medium">Domain</th>
                                            <th className="py-2 pr-4 font-medium">Owner</th>
                                            <th className="py-2 font-medium">Failing checks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {nonCompliant.map((d) => (
                                            <tr key={d.id} className="border-b last:border-0 align-top">
                                                <td className="py-2 pr-4 font-medium">{d.hostname}</td>
                                                <td className="py-2 pr-4">
                                                    {d.owner ? (
                                                        <Link
                                                            href={userEdit(d.owner.id).url}
                                                            className="text-muted-foreground hover:underline"
                                                        >
                                                            {d.owner.email}
                                                        </Link>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td className="py-2">
                                                    <div className="flex flex-wrap gap-1">
                                                        {d.failing.map((f) => (
                                                            <Badge key={f.key} variant="destructive">
                                                                {f.label}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Users by tier</CardTitle>
                            <Link href={tiersIndex().url} className="text-sm text-muted-foreground hover:underline">
                                Manage tiers
                            </Link>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {tiers.map((t) => (
                                <div key={t.id} className="flex justify-between">
                                    <span>{t.name}</span>
                                    <span className="text-muted-foreground">{t.users}</span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recent signups</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {recentUsers.map((u) => (
                                <div key={u.id} className="flex justify-between gap-2">
                                    <span className="truncate">{u.email}</span>
                                    <span className="shrink-0 text-muted-foreground">{fmt(u.createdAt)}</span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recent scans</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {recentScans.map((s) => (
                                <div key={s.id} className="flex justify-between gap-2">
                                    <span className="truncate">{s.hostname ?? '—'}</span>
                                    <span className="shrink-0 text-muted-foreground">{s.status}</span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
