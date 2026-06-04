import { Head, Link, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import Heading from '@/components/heading';
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
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface DomainSummary {
    id: string;
    hostname: string;
    verifyStatus: string;
    bannerLive: boolean;
    lastScannedAt: string | null;
}

interface Metrics {
    total: number;
    impressions: number;
    methods: { acceptAll: number; rejectAll: number; custom: number };
    categories: Record<string, { granted: number; percent: number }>;
}

interface ScanSummary {
    lastScannedAt: string | null;
    byCategory: Record<string, number>;
    total: number;
    unclassified: number;
}

interface HealthItem {
    key: string;
    label: string;
    ok: boolean;
    hint: string | null;
}

interface RecentRecord {
    consentId: string;
    createdAt: string | null;
    method: 'accept_all' | 'reject_all' | 'custom';
    language: string | null;
}

type Props =
    | { hasDomain: false }
    | {
          hasDomain: true;
          domain: DomainSummary;
          rangeDays: number;
          rangeOptions: number[];
          metrics: Metrics;
          scanSummary: ScanSummary;
          health: HealthItem[];
          recent: RecentRecord[];
      };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

function EmptyState() {
    return (
        <Card>
            <CardContent className="space-y-3 py-10 text-center">
                <h2 className="text-lg font-medium">Add your first domain</h2>
                <p className="text-sm text-muted-foreground">
                    Create a domain to start collecting consent.
                </p>
                <Button asChild>
                    <Link href="/domains/create">Add domain</Link>
                </Button>
            </CardContent>
        </Card>
    );
}

function percent(part: number, total: number): number {
    return total > 0 ? Math.round((part / total) * 1000) / 10 : 0;
}

export default function Dashboard(props: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {props.hasDomain ? <Loaded {...props} /> : <EmptyState />}
            </div>
        </AppLayout>
    );
}

function Loaded({
    domain,
    rangeDays,
    rangeOptions,
    metrics,
    scanSummary,
    health,
    recent,
}: Extract<Props, { hasDomain: true }>) {
    const acceptPct = percent(metrics.methods.acceptAll, metrics.total);
    const rejectPct = percent(metrics.methods.rejectAll, metrics.total);
    const customPct = percent(metrics.methods.custom, metrics.total);

    return (
        <>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Heading
                    title={domain.hostname}
                    description="Consent activity and compliance health."
                />
                <div className="flex items-center gap-2">
                    <Select
                        value={String(rangeDays)}
                        onValueChange={(v) =>
                            router.get(
                                dashboard().url,
                                { days: Number(v) },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <SelectTrigger className="w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {rangeOptions.map((d) => (
                                <SelectItem key={d} value={String(d)}>
                                    Last {d} days
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button asChild variant="outline" size="sm">
                        <Link href={`/domains/${domain.id}`}>Open domain</Link>
                    </Button>
                </div>
            </div>

            {/* US-DASH-1 — consent metrics */}
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Stat label="Consent events" value={metrics.total.toLocaleString()} />
                <Stat label="Banner impressions" value={metrics.impressions.toLocaleString()} />
                <Stat label="Accept all" value={`${acceptPct}%`} sub={`${metrics.methods.acceptAll}`} />
                <Stat label="Reject all" value={`${rejectPct}%`} sub={`${metrics.methods.rejectAll}`} />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Opt-in by category</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    {Object.entries(metrics.categories).map(([key, value]) => (
                        <div key={key} className="space-y-1">
                            <div className="flex justify-between text-sm">
                                <span className="capitalize">{key}</span>
                                <span className="text-muted-foreground">
                                    {value.granted} ({value.percent}%)
                                </span>
                            </div>
                            <div className="h-2 w-full overflow-hidden rounded bg-muted">
                                <div
                                    className="h-full bg-primary"
                                    style={{ width: `${Math.min(value.percent, 100)}%` }}
                                />
                            </div>
                        </div>
                    ))}
                    <p className="pt-2 text-xs text-muted-foreground">
                        Custom choices: {metrics.methods.custom} ({customPct}%)
                    </p>
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-2">
                {/* US-DASH-2 */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Scan summary</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        <div className="flex justify-between">
                            <span>Total cookies</span>
                            <span className="font-medium">{scanSummary.total}</span>
                        </div>
                        {Object.entries(scanSummary.byCategory).map(([cat, n]) => (
                            <div key={cat} className="flex justify-between text-muted-foreground">
                                <span className="capitalize">{cat}</span>
                                <span>{n}</span>
                            </div>
                        ))}
                        {scanSummary.unclassified > 0 && (
                            <p className="pt-2">
                                <Link
                                    href={`/domains/${domain.id}`}
                                    className="text-sm text-red-600 underline"
                                >
                                    Classify {scanSummary.unclassified} cookie(s)
                                </Link>
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* US-DASH-3 */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Compliance health</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2 text-sm">
                            {health.map((item) => (
                                <li key={item.key} className="flex items-start gap-2">
                                    {item.ok ? (
                                        <Check className="mt-0.5 h-4 w-4 text-emerald-600" />
                                    ) : (
                                        <X className="mt-0.5 h-4 w-4 text-red-600" />
                                    )}
                                    <div>
                                        <div>{item.label}</div>
                                        {item.hint && (
                                            <div className="text-xs text-muted-foreground">
                                                {item.hint}
                                            </div>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>

            {/* US-DASH-4 */}
            <Card>
                <CardHeader className="flex-row items-center justify-between space-y-0">
                    <CardTitle className="text-base">Recent consent events</CardTitle>
                    <Button asChild size="sm" variant="outline">
                        <Link href={`/domains/${domain.id}/consent-logs`}>View all / export</Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    {recent.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No consent events yet.</p>
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {recent.map((r) => (
                                <li
                                    key={r.consentId}
                                    className="flex flex-wrap items-center justify-between gap-2 border-b py-1 last:border-0"
                                >
                                    <span className="font-mono text-xs">{r.consentId}</span>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline">{r.method}</Badge>
                                        {r.language && (
                                            <span className="text-xs text-muted-foreground">
                                                {r.language}
                                            </span>
                                        )}
                                        <span className="text-xs text-muted-foreground">
                                            {r.createdAt
                                                ? new Date(r.createdAt).toLocaleString()
                                                : ''}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </>
    );
}

function Stat({ label, value, sub }: { label: string; value: string; sub?: string }) {
    return (
        <Card>
            <CardContent className="space-y-1 py-4">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="text-2xl font-semibold">{value}</p>
                {sub && <p className="text-xs text-muted-foreground">{sub}</p>}
            </CardContent>
        </Card>
    );
}
