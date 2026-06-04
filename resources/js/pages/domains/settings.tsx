import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { index as domainsIndex } from '@/routes/domains';
import type { BreadcrumbItem } from '@/types';

interface DomainSettings {
    id: string;
    hostname: string;
    consentExpiryDays: number;
    scheduledScanEnabled: boolean;
    scanFrequency: 'weekly' | 'monthly' | null;
}

interface PolicyVersionRow {
    version: number;
    effectiveAt: string | null;
    notes: string | null;
}

const GDPR_RECOMMENDED_MAX_DAYS = 365;

export default function DomainSettings({
    domain,
    notifications,
    policyVersions,
    currentPolicyVersion,
}: {
    domain: DomainSettings;
    notifications: { newCookieAlerts: boolean };
    policyVersions: PolicyVersionRow[];
    currentPolicyVersion: number;
}) {
    const [expiry, setExpiry] = useState<number>(domain.consentExpiryDays);
    const [scheduledEnabled, setScheduledEnabled] = useState(domain.scheduledScanEnabled);
    const [scanFreq, setScanFreq] = useState<'weekly' | 'monthly'>(
        domain.scanFrequency ?? 'monthly',
    );
    const [alerts, setAlerts] = useState(notifications.newCookieAlerts);
    const [notes, setNotes] = useState('');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Domains', href: domainsIndex().url },
        { title: domain.hostname, href: `/domains/${domain.id}` },
        { title: 'Settings', href: `/domains/${domain.id}/settings` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${domain.hostname} — settings`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Domain settings"
                    description="Consent expiry, policy version, and notifications."
                />

                <Form
                    method="put"
                    action={`/domains/${domain.id}/settings`}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* US-SET-1 */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Consent expiry</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="grid max-w-xs gap-2">
                                        <Label htmlFor="consentExpiryDays">
                                            Days until re-prompt
                                        </Label>
                                        <Input
                                            id="consentExpiryDays"
                                            name="consentExpiryDays"
                                            type="number"
                                            min={1}
                                            max={730}
                                            value={expiry}
                                            onChange={(e) =>
                                                setExpiry(Number(e.target.value) || 0)
                                            }
                                            required
                                        />
                                        <InputError message={errors.consentExpiryDays} />
                                        {expiry > GDPR_RECOMMENDED_MAX_DAYS && (
                                            <p className="text-xs text-amber-600">
                                                GDPR guidance recommends re-prompting within
                                                12 months ({GDPR_RECOMMENDED_MAX_DAYS} days).
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* US-SCAN-5 — scheduled scans live on the same form. */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Scheduled scans</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="scheduledScanEnabled"
                                            name="scheduledScanEnabled"
                                            checked={scheduledEnabled}
                                            onCheckedChange={(v) =>
                                                setScheduledEnabled(v === true)
                                            }
                                        />
                                        <Label htmlFor="scheduledScanEnabled">
                                            Run scans automatically
                                        </Label>
                                    </div>
                                    {scheduledEnabled && (
                                        <div className="grid max-w-xs gap-2">
                                            <Label htmlFor="scanFrequency">Frequency</Label>
                                            <Select
                                                value={scanFreq}
                                                onValueChange={(v) =>
                                                    setScanFreq(v as 'weekly' | 'monthly')
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="weekly">Weekly</SelectItem>
                                                    <SelectItem value="monthly">Monthly</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <input
                                                type="hidden"
                                                name="scanFrequency"
                                                value={scanFreq}
                                            />
                                        </div>
                                    )}
                                    <InputError message={errors.scanFrequency} />
                                </CardContent>
                            </Card>

                            {/* US-SET-3 */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Notifications</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="newCookieAlerts"
                                            name="newCookieAlerts"
                                            checked={alerts}
                                            onCheckedChange={(v) => setAlerts(v === true)}
                                        />
                                        <Label htmlFor="newCookieAlerts">
                                            Email me when a scan finds new or unclassified
                                            cookies
                                        </Label>
                                    </div>
                                    <InputError message={errors.newCookieAlerts} />
                                </CardContent>
                            </Card>

                            <input
                                type="hidden"
                                name="scheduledScanEnabled"
                                value={scheduledEnabled ? '1' : '0'}
                            />
                            <input
                                type="hidden"
                                name="newCookieAlerts"
                                value={alerts ? '1' : '0'}
                            />

                            <div>
                                <Button disabled={processing}>Save settings</Button>
                            </div>
                        </>
                    )}
                </Form>

                {/* US-SET-2 — policy version bump. */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Policy version{' '}
                            <Badge variant="outline" className="ml-2">
                                v{currentPolicyVersion}
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Publishing a new version invalidates existing consent records
                            and re-prompts visitors on next visit.
                        </p>

                        <Form
                            method="post"
                            action={`/domains/${domain.id}/policy-versions`}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setNotes('')}
                            className="space-y-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="notes">
                                            Notes (optional, internal)
                                        </Label>
                                        <Input
                                            id="notes"
                                            name="notes"
                                            value={notes}
                                            onChange={(e) => setNotes(e.target.value)}
                                            placeholder="e.g. added analytics vendor X"
                                        />
                                        <InputError message={errors.notes} />
                                    </div>
                                    <Button disabled={processing}>
                                        Publish new policy version
                                    </Button>
                                </>
                            )}
                        </Form>

                        {policyVersions.length > 0 && (
                            <div className="space-y-2">
                                <h3 className="text-sm font-medium">History</h3>
                                <ul className="space-y-1 text-sm">
                                    {policyVersions.map((v) => (
                                        <li
                                            key={v.version}
                                            className="flex flex-wrap items-center gap-2 border-b py-2 last:border-0"
                                        >
                                            <Badge variant="outline">v{v.version}</Badge>
                                            <span className="text-muted-foreground">
                                                {v.effectiveAt
                                                    ? new Date(v.effectiveAt).toLocaleString()
                                                    : '—'}
                                            </span>
                                            {v.notes && (
                                                <span className="text-xs">{v.notes}</span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* US-SET-5 — link out to banner builder. */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Banner content & languages</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            Edit banner text, languages, layout, and policy URL in the
                            banner builder.
                        </p>
                        <Button asChild variant="outline" className="mt-3">
                            <Link href={`/domains/${domain.id}/banner`}>Open banner builder</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
