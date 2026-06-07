import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as usersIndex, update as userUpdate } from '@/routes/admin/users';
import type { BreadcrumbItem } from '@/types';

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        tierId: number | null;
        isSuperAdmin: boolean;
        domains: number;
    };
    tiers: { id: number; name: string }[];
}

const NO_TIER = 'none';

export default function EditUser({ user, tiers }: Props) {
    const form = useForm({
        tier_id: user.tierId,
        is_super_admin: user.isSuperAdmin,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin', href: adminDashboard().url },
        { title: 'Users', href: usersIndex().url },
        { title: user.email, href: '#' },
    ];

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(userUpdate(user.id).url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${user.email}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading title={user.name} description={user.email} />

                <Card className="max-w-lg">
                    <CardContent className="py-6">
                        <form onSubmit={submit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="tier">Tier</Label>
                                <Select
                                    value={form.data.tier_id === null ? NO_TIER : String(form.data.tier_id)}
                                    onValueChange={(v) => form.setData('tier_id', v === NO_TIER ? null : Number(v))}
                                >
                                    <SelectTrigger id="tier" className="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_TIER}>Default tier</SelectItem>
                                        {tiers.map((t) => (
                                            <SelectItem key={t.id} value={String(t.id)}>
                                                {t.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors.tier_id && (
                                    <p className="text-sm text-destructive">{form.errors.tier_id}</p>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_super_admin"
                                    checked={form.data.is_super_admin}
                                    onCheckedChange={(c) => form.setData('is_super_admin', c === true)}
                                />
                                <Label htmlFor="is_super_admin">Super admin</Label>
                            </div>
                            {form.errors.is_super_admin && (
                                <p className="text-sm text-destructive">{form.errors.is_super_admin}</p>
                            )}

                            <Button type="submit" disabled={form.processing}>
                                Save changes
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
