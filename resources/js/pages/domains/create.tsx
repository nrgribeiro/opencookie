import { Form, Head, Link } from '@inertiajs/react';
import DomainController from '@/actions/App/Http/Controllers/DomainController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { index } from '@/routes/domains';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Domains', href: index().url },
    { title: 'Add domain', href: '/domains/create' },
];

export default function DomainsCreate() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add domain" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Add domain"
                    description="Enter the website domain you want to manage consent for."
                />

                <Card className="max-w-xl">
                    <CardContent className="pt-6">
                        <Form
                            {...DomainController.store.form()}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="hostname">Domain</Label>
                                        <Input
                                            id="hostname"
                                            name="hostname"
                                            required
                                            autoFocus
                                            placeholder="example.com"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Enter the bare domain — no http:// and no path.
                                        </p>
                                        <InputError message={errors.hostname} />
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <Button disabled={processing}>Add domain</Button>
                                        <Button variant="ghost" asChild>
                                            <Link href={index().url}>Cancel</Link>
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
