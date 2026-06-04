import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { create, show } from '@/routes/domains';
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Domains', href: '/domains' },
];

function VerifyBadge({ status }: { status: DomainSummary['verifyStatus'] }) {
    const map = {
        verified: { label: 'Verified', variant: 'default' as const },
        pending: { label: 'Pending', variant: 'secondary' as const },
        failed: { label: 'Failed', variant: 'destructive' as const },
    };
    const { label, variant } = map[status];
    return <Badge variant={variant}>{label}</Badge>;
}

export default function DomainsIndex({
    domains,
    canCreate,
}: {
    domains: DomainSummary[];
    canCreate: boolean;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Domains" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Domains</h1>
                    {canCreate && (
                        <Button asChild>
                            <Link href={create().url}>Add domain</Link>
                        </Button>
                    )}
                </div>

                {domains.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <p className="text-muted-foreground">
                                No domains yet. Add one to start managing consent.
                            </p>
                            <Button asChild>
                                <Link href={create().url}>Add your first domain</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {domains.map((domain) => (
                            <Link key={domain.id} href={show(domain.id).url}>
                                <Card className="transition-colors hover:border-primary">
                                    <CardHeader className="flex-row items-center justify-between space-y-0">
                                        <CardTitle className="text-base">
                                            {domain.hostname}
                                        </CardTitle>
                                        <VerifyBadge status={domain.verifyStatus} />
                                    </CardHeader>
                                    <CardContent className="flex gap-2 text-sm text-muted-foreground">
                                        <Badge variant={domain.bannerLive ? 'default' : 'outline'}>
                                            {domain.bannerLive ? 'Banner live' : 'Banner off'}
                                        </Badge>
                                        <Badge variant="outline">
                                            {domain.lastScannedAt
                                                ? `Scanned ${new Date(domain.lastScannedAt).toLocaleDateString()}`
                                                : 'Never scanned'}
                                        </Badge>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}

                {!canCreate && domains.length > 0 && (
                    <p className="text-sm text-muted-foreground">
                        Free tier allows 1 domain. Remove it to add another.
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
