import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard as adminDashboard } from '@/routes/admin';
import { destroy as tierDestroy, index as tiersIndex, store as tierStore, update as tierUpdate } from '@/routes/admin/tiers';
import type { BreadcrumbItem } from '@/types';

interface Tier {
    id: number;
    name: string;
    slug: string;
    maxDomains: number | null;
    maxScanPages: number;
    monthlyPageviewCap: number | null;
    scheduledScansAllowed: boolean;
    isDefault: boolean;
    users: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: adminDashboard().url },
    { title: 'Tiers', href: tiersIndex().url },
];

const unlimited = (v: number | null) => (v === null ? '∞' : v.toLocaleString());

function TierDialog({ tier, trigger }: { tier?: Tier; trigger: React.ReactNode }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: tier?.name ?? '',
        slug: tier?.slug ?? '',
        max_domains: tier?.maxDomains ?? null,
        max_scan_pages: tier?.maxScanPages ?? 100,
        monthly_pageview_cap: tier?.monthlyPageviewCap ?? null,
        scheduled_scans_allowed: tier?.scheduledScansAllowed ?? false,
        is_default: tier?.isDefault ?? false,
    });

    const num = (v: string): number | null => (v === '' ? null : Number(v));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const onSuccess = () => setOpen(false);

        if (tier) {
            form.put(tierUpdate(tier.id).url, { onSuccess });
        } else {
            form.post(tierStore().url, { onSuccess });
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{tier ? `Edit ${tier.name}` : 'New tier'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">Name</Label>
                            <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                            {form.errors.name && <p className="text-sm text-destructive">{form.errors.name}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="slug">Slug</Label>
                            <Input id="slug" value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} />
                            {form.errors.slug && <p className="text-sm text-destructive">{form.errors.slug}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="max_domains">Max domains (blank = ∞)</Label>
                            <Input
                                id="max_domains"
                                type="number"
                                min={1}
                                value={form.data.max_domains ?? ''}
                                onChange={(e) => form.setData('max_domains', num(e.target.value))}
                            />
                            {form.errors.max_domains && <p className="text-sm text-destructive">{form.errors.max_domains}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="max_scan_pages">Max scan pages</Label>
                            <Input
                                id="max_scan_pages"
                                type="number"
                                min={1}
                                value={form.data.max_scan_pages}
                                onChange={(e) => form.setData('max_scan_pages', Number(e.target.value))}
                            />
                            {form.errors.max_scan_pages && <p className="text-sm text-destructive">{form.errors.max_scan_pages}</p>}
                        </div>
                        <div className="col-span-2 space-y-2">
                            <Label htmlFor="monthly_pageview_cap">Monthly pageview cap (blank = ∞)</Label>
                            <Input
                                id="monthly_pageview_cap"
                                type="number"
                                min={0}
                                value={form.data.monthly_pageview_cap ?? ''}
                                onChange={(e) => form.setData('monthly_pageview_cap', num(e.target.value))}
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="scheduled_scans_allowed"
                            checked={form.data.scheduled_scans_allowed}
                            onCheckedChange={(c) => form.setData('scheduled_scans_allowed', c === true)}
                        />
                        <Label htmlFor="scheduled_scans_allowed">Scheduled scans allowed</Label>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="is_default"
                            checked={form.data.is_default}
                            onCheckedChange={(c) => form.setData('is_default', c === true)}
                        />
                        <Label htmlFor="is_default">Default tier for new accounts</Label>
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {tier ? 'Save' : 'Create'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function AdminTiers({ tiers }: { tiers: Tier[] }) {
    const remove = (tier: Tier) => {
        if (confirm(`Delete the ${tier.name} tier?`)) {
            router.delete(tierDestroy(tier.id).url, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tiers" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="Tiers" description="Account limits applied to users." />
                    <TierDialog trigger={<Button>New tier</Button>} />
                </div>

                <Card>
                    <CardContent className="overflow-x-auto py-4">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 pr-4 font-medium">Name</th>
                                    <th className="py-2 pr-4 font-medium">Max domains</th>
                                    <th className="py-2 pr-4 font-medium">Scan pages</th>
                                    <th className="py-2 pr-4 font-medium">Pageview cap</th>
                                    <th className="py-2 pr-4 font-medium">Scheduled</th>
                                    <th className="py-2 pr-4 font-medium">Users</th>
                                    <th className="py-2 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {tiers.map((t) => (
                                    <tr key={t.id} className="border-b last:border-0">
                                        <td className="py-2 pr-4 font-medium">
                                            {t.name}
                                            {t.isDefault && (
                                                <Badge variant="secondary" className="ml-2">
                                                    Default
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="py-2 pr-4">{unlimited(t.maxDomains)}</td>
                                        <td className="py-2 pr-4">{t.maxScanPages.toLocaleString()}</td>
                                        <td className="py-2 pr-4">{unlimited(t.monthlyPageviewCap)}</td>
                                        <td className="py-2 pr-4">{t.scheduledScansAllowed ? 'Yes' : 'No'}</td>
                                        <td className="py-2 pr-4">{t.users}</td>
                                        <td className="py-2">
                                            <div className="flex gap-2">
                                                <TierDialog
                                                    tier={t}
                                                    trigger={
                                                        <Button size="sm" variant="outline">
                                                            Edit
                                                        </Button>
                                                    }
                                                />
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={t.isDefault || t.users > 0}
                                                    onClick={() => remove(t)}
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
