import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { dashboard as adminDashboard } from '@/routes/admin';
import { destroy as userDestroy, edit as userEdit, index as usersIndex } from '@/routes/admin/users';
import type { BreadcrumbItem } from '@/types';

interface UserRow {
    id: number;
    name: string;
    email: string;
    tier: string | null;
    isSuperAdmin: boolean;
    domains: number;
    createdAt: string | null;
}

interface PaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    users: {
        data: UserRow[];
        links: PaginatorLink[];
    };
    filters: { search: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: adminDashboard().url },
    { title: 'Users', href: usersIndex().url },
];

export default function AdminUsers({ users, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(usersIndex().url, { search }, { preserveState: true, replace: true });
    };

    const remove = (user: UserRow) => {
        if (confirm(`Delete ${user.email}? Their domains and data will be removed.`)) {
            router.delete(userDestroy(user.id).url, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading title="Users" description="Manage account tiers and roles." />

                <form onSubmit={submit} className="flex gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search name or email…"
                        className="max-w-xs"
                    />
                    <Button type="submit" variant="secondary">
                        Search
                    </Button>
                </form>

                <Card>
                    <CardContent className="overflow-x-auto py-4">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="py-2 pr-4 font-medium">Name</th>
                                    <th className="py-2 pr-4 font-medium">Email</th>
                                    <th className="py-2 pr-4 font-medium">Tier</th>
                                    <th className="py-2 pr-4 font-medium">Role</th>
                                    <th className="py-2 pr-4 font-medium">Domains</th>
                                    <th className="py-2 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.map((u) => (
                                    <tr key={u.id} className="border-b last:border-0">
                                        <td className="py-2 pr-4">{u.name}</td>
                                        <td className="py-2 pr-4">{u.email}</td>
                                        <td className="py-2 pr-4">{u.tier ?? '—'}</td>
                                        <td className="py-2 pr-4">
                                            {u.isSuperAdmin ? <Badge>Super admin</Badge> : <span className="text-muted-foreground">User</span>}
                                        </td>
                                        <td className="py-2 pr-4">{u.domains}</td>
                                        <td className="py-2">
                                            <div className="flex gap-2">
                                                <Button asChild size="sm" variant="outline">
                                                    <Link href={userEdit(u.id).url}>Edit</Link>
                                                </Button>
                                                <Button size="sm" variant="ghost" onClick={() => remove(u)}>
                                                    Delete
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {users.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="py-6 text-center text-muted-foreground">
                                            No users found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <div className="flex flex-wrap gap-1">
                    {users.links.map((link) =>
                        link.url ? (
                            <Button
                                key={link.label}
                                asChild
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                            >
                                <Link href={link.url} preserveState dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Button>
                        ) : (
                            <Button
                                key={link.label}
                                size="sm"
                                variant="outline"
                                disabled
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ),
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
