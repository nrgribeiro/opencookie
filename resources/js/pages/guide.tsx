import { Head, Link } from '@inertiajs/react';
import {
    BadgeCheck,
    Bell,
    BookOpen,
    Check,
    CircleAlert,
    ClipboardList,
    Code2,
    Cookie,
    FileDown,
    Globe,
    Lightbulb,
    Palette,
    ScanLine,
    ShieldCheck,
} from 'lucide-react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { index as domainsIndex } from '@/routes/domains';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Setup & Compliance Guide',
        href: '/guide',
    },
];

function CodeBlock({ children }: { children: string }) {
    return (
        <pre className="mt-3 overflow-x-auto rounded-lg border bg-muted/60 p-4 text-xs leading-relaxed text-foreground">
            <code>{children}</code>
        </pre>
    );
}

function Step({
    number,
    icon: Icon,
    title,
    children,
}: {
    number: number;
    icon: typeof Globe;
    title: string;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-3 text-base">
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                        {number}
                    </span>
                    <Icon className="h-5 w-5 text-muted-foreground" />
                    <span>{title}</span>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm leading-relaxed text-muted-foreground">
                {children}
            </CardContent>
        </Card>
    );
}

export default function Guide() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Setup & Compliance Guide" />

            <div className="mx-auto w-full max-w-4xl space-y-8 p-4 md:p-6">
                <Heading
                    title="Set up cookie consent on your website"
                    description="A plain-English, step-by-step guide to installing OpenCookie and staying GDPR-compliant. No legal degree or coding background required — just follow the steps in order."
                />

                {/* What this is */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <BookOpen className="h-5 w-5 text-muted-foreground" />
                            What does OpenCookie do?
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm leading-relaxed text-muted-foreground">
                        <p>
                            European privacy law (the{' '}
                            <strong className="text-foreground">GDPR</strong>{' '}
                            and the ePrivacy Directive) says you must{' '}
                            <strong className="text-foreground">
                                ask visitors for permission
                            </strong>{' '}
                            before you load tracking cookies — analytics,
                            advertising, and so on. You also have to{' '}
                            <strong className="text-foreground">
                                prove they agreed
                            </strong>{' '}
                            and let them change their mind later.
                        </p>
                        <p>OpenCookie handles all of that for you:</p>
                        <ul className="list-inside list-disc space-y-1">
                            <li>
                                Scans your site to find every cookie and
                                tracker.
                            </li>
                            <li>
                                Shows visitors a consent banner and remembers
                                their choice.
                            </li>
                            <li>
                                Blocks tracking scripts until the visitor says
                                yes.
                            </li>
                            <li>
                                Keeps a tamper-proof log of every consent as
                                legal proof.
                            </li>
                        </ul>
                        <p>
                            Work through the seven steps below. Most sites are
                            live in under 30 minutes.
                        </p>
                    </CardContent>
                </Card>

                {/* Steps */}
                <div className="space-y-4">
                    <Step number={1} icon={Globe} title="Add and verify your domain">
                        <p>
                            Go to{' '}
                            <Link
                                href={domainsIndex()}
                                className="font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Domains
                            </Link>{' '}
                            and add the website you want to protect (for example{' '}
                            <code className="rounded bg-muted px-1 py-0.5">
                                example.com
                            </code>
                            ).
                        </p>
                        <p>
                            We then ask you to{' '}
                            <strong className="text-foreground">
                                verify you own it
                            </strong>{' '}
                            — usually by adding a small DNS record, a meta tag,
                            or uploading a file. The verification screen shows
                            the exact value to copy. This stops other people
                            from configuring consent for your site.
                        </p>
                        <Alert>
                            <Lightbulb className="h-4 w-4" />
                            <AlertTitle>Not sure how?</AlertTitle>
                            <AlertDescription>
                                If you don't manage your own DNS, the meta-tag or
                                file method is easiest — forward the instructions
                                to whoever built your site.
                            </AlertDescription>
                        </Alert>
                    </Step>

                    <Step number={2} icon={ScanLine} title="Scan your site for cookies">
                        <p>
                            Open your verified domain and click{' '}
                            <strong className="text-foreground">Run scan</strong>
                            . OpenCookie visits your pages like a real browser
                            and records every cookie, tracker, and storage key it
                            finds — including the sneaky ones set by JavaScript
                            (Google Analytics, Meta Pixel, etc.).
                        </p>
                        <p>
                            The scan runs in the background. When it finishes
                            you'll see a list grouped into four categories:
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="secondary">Necessary</Badge>
                            <Badge variant="secondary">Preferences</Badge>
                            <Badge variant="secondary">Statistics</Badge>
                            <Badge variant="secondary">Marketing</Badge>
                        </div>
                    </Step>

                    <Step
                        number={3}
                        icon={Cookie}
                        title="Review and correct the cookie list"
                    >
                        <p>
                            We auto-classify cookies using a built-in database,
                            but you know your site best. Check each one and,
                            where needed,{' '}
                            <strong className="text-foreground">
                                change the category
                            </strong>{' '}
                            or fill in the details: purpose, who controls the data
                            (the data controller), how long it's kept (retention),
                            and a privacy/GDPR rights link.
                        </p>
                        <p>
                            Anything marked{' '}
                            <Badge variant="outline">Unclassified</Badge> should
                            be reviewed before you go live. Your edits are saved
                            as <strong className="text-foreground">overrides</strong>{' '}
                            and survive future scans, so you only do this once.
                        </p>
                        <Alert>
                            <CircleAlert className="h-4 w-4" />
                            <AlertTitle>Why it matters</AlertTitle>
                            <AlertDescription>
                                Accurate categories are what make the banner
                                block the right scripts. A marketing cookie filed
                                as "necessary" would load without consent — a
                                compliance gap.
                            </AlertDescription>
                        </Alert>
                    </Step>

                    <Step
                        number={4}
                        icon={Palette}
                        title="Design and publish your banner"
                    >
                        <p>
                            In{' '}
                            <strong className="text-foreground">
                                Banner builder
                            </strong>{' '}
                            choose the layout, colours, and wording, and add
                            translations if your site is multilingual. Preview it,
                            then click{' '}
                            <strong className="text-foreground">Publish</strong>.
                        </p>
                        <p className="font-medium text-foreground">
                            To stay compliant, your banner must:
                        </p>
                        <ul className="list-inside list-disc space-y-1">
                            <li>
                                Give{' '}
                                <strong className="text-foreground">
                                    "Accept" and "Reject" equal prominence
                                </strong>{' '}
                                — rejecting must be as easy as accepting.
                            </li>
                            <li>
                                Leave every optional category{' '}
                                <strong className="text-foreground">
                                    switched off by default
                                </strong>{' '}
                                (no pre-ticked boxes).
                            </li>
                            <li>
                                Link to your cookie/privacy policy and explain
                                each category clearly.
                            </li>
                        </ul>
                        <p>
                            OpenCookie's defaults already follow these rules, so
                            you mostly just need to keep them.
                        </p>
                    </Step>

                    <Step
                        number={5}
                        icon={Code2}
                        title="Add the snippet to your website"
                    >
                        <p>
                            On the domain page, copy the{' '}
                            <strong className="text-foreground">
                                install snippet
                            </strong>{' '}
                            and paste it into the{' '}
                            <code className="rounded bg-muted px-1 py-0.5">
                                &lt;head&gt;
                            </code>{' '}
                            of every page — ideally as the{' '}
                            <strong className="text-foreground">
                                very first script
                            </strong>
                            , before any analytics or ad tags.
                        </p>
                        <CodeBlock>{`<script
  src="https://your-opencookie-host/sdk/v1/cmp.js"
  data-domain="YOUR_DOMAIN_ID"
  async
></script>`}</CodeBlock>
                        <Alert>
                            <Lightbulb className="h-4 w-4" />
                            <AlertTitle>
                                Using WordPress, Shopify, or a site builder?
                            </AlertTitle>
                            <AlertDescription>
                                Look for a "custom code", "header scripts", or
                                "code injection" setting, or use a header/footer
                                plugin. Paste the snippet there once and it
                                applies site-wide.
                            </AlertDescription>
                        </Alert>
                    </Step>

                    <Step
                        number={6}
                        icon={ShieldCheck}
                        title="Block your tracking scripts until consent"
                    >
                        <p>
                            This is the step that actually makes you compliant.
                            For each tracking script already on your site, change{' '}
                            <code className="rounded bg-muted px-1 py-0.5">
                                type="text/javascript"
                            </code>{' '}
                            to{' '}
                            <code className="rounded bg-muted px-1 py-0.5">
                                type="text/plain"
                            </code>{' '}
                            and add a{' '}
                            <code className="rounded bg-muted px-1 py-0.5">
                                data-cmp-category
                            </code>{' '}
                            attribute. OpenCookie only switches them on once the
                            visitor agrees to that category.
                        </p>
                        <p className="font-medium text-foreground">
                            Example — Google Analytics 4 (statistics):
                        </p>
                        <CodeBlock>{`<!-- Will only run after "statistics" consent -->
<script type="text/plain" data-cmp-category="statistics" async
        src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXX"></script>
<script type="text/plain" data-cmp-category="statistics">
  window.dataLayer = window.dataLayer || [];
  function gtag(){ dataLayer.push(arguments); }
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXX');
</script>`}</CodeBlock>
                        <p className="font-medium text-foreground">
                            Example — Meta (Facebook) Pixel (marketing):
                        </p>
                        <CodeBlock>{`<script type="text/plain" data-cmp-category="marketing">
  !function(f,b,e,v,n,t,s){/* ...Meta pixel code... */}();
  fbq('init', 'YOUR_PIXEL_ID');
  fbq('track', 'PageView');
</script>`}</CodeBlock>
                        <p>
                            The four categories you can use are{' '}
                            <code className="rounded bg-muted px-1 py-0.5">
                                necessary
                            </code>{' '}
                            (always on),{' '}
                            <code className="rounded bg-muted px-1 py-0.5">
                                preferences
                            </code>
                            ,{' '}
                            <code className="rounded bg-muted px-1 py-0.5">
                                statistics
                            </code>
                            , and{' '}
                            <code className="rounded bg-muted px-1 py-0.5">
                                marketing
                            </code>
                            . If you use Google Ads or Analytics, OpenCookie also
                            sends the matching{' '}
                            <strong className="text-foreground">
                                Google Consent Mode v2
                            </strong>{' '}
                            signals automatically — no extra setup.
                        </p>
                    </Step>

                    <Step
                        number={7}
                        icon={BadgeCheck}
                        title="Test that it works"
                    >
                        <p>
                            Open your site in a private/incognito window. You
                            should see:
                        </p>
                        <ul className="list-inside list-disc space-y-1">
                            <li>The consent banner appears on first visit.</li>
                            <li>
                                Before you choose, your tracking tags do{' '}
                                <strong className="text-foreground">not</strong>{' '}
                                fire (check the browser's Network tab — no
                                Analytics/Pixel requests).
                            </li>
                            <li>
                                After clicking{' '}
                                <strong className="text-foreground">
                                    Accept
                                </strong>
                                , the tags load and your choice is remembered on
                                reload.
                            </li>
                            <li>
                                The floating button lets you reopen the banner and
                                change your mind.
                            </li>
                        </ul>
                    </Step>
                </div>

                <Separator />

                {/* Ongoing compliance */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Bell className="h-5 w-5 text-muted-foreground" />
                            Staying compliant over time
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm leading-relaxed text-muted-foreground">
                        <p>
                            Compliance isn't a one-off. OpenCookie keeps you
                            covered as your site changes:
                        </p>
                        <ul className="space-y-2">
                            <li className="flex gap-2">
                                <ScanLine className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                <span>
                                    <strong className="text-foreground">
                                        Scheduled scans
                                    </strong>{' '}
                                    re-check your site automatically (weekly or
                                    monthly) and flag any new cookies.
                                </span>
                            </li>
                            <li className="flex gap-2">
                                <FileDown className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                <span>
                                    <strong className="text-foreground">
                                        Consent logs
                                    </strong>{' '}
                                    record every choice and can be exported as a
                                    CSV if a regulator ever asks for proof. Logs
                                    are kept for 24 months.
                                </span>
                            </li>
                            <li className="flex gap-2">
                                <ClipboardList className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                <span>
                                    When you change your cookies or policy,{' '}
                                    <strong className="text-foreground">
                                        bump the policy version
                                    </strong>{' '}
                                    in settings to re-ask visitors for fresh
                                    consent.
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                {/* Checklist */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ClipboardList className="h-5 w-5 text-muted-foreground" />
                            Compliance checklist
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2 text-sm">
                            {[
                                'Domain added and verified',
                                'Site scanned and every cookie reviewed (no "Unclassified" left)',
                                'Banner gives Accept and Reject equal prominence',
                                'No optional category pre-ticked',
                                'Banner links to your cookie/privacy policy',
                                'Install snippet added as the first script on every page',
                                'All analytics & marketing tags switched to type="text/plain" with a category',
                                'Tested in incognito: nothing tracks before consent',
                                'Scheduled scans enabled',
                            ].map((item) => (
                                <li
                                    key={item}
                                    className="flex items-start gap-2"
                                >
                                    <Check className="mt-0.5 h-4 w-4 shrink-0 text-green-600" />
                                    <span className="text-muted-foreground">
                                        {item}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <Alert>
                    <CircleAlert className="h-4 w-4" />
                    <AlertTitle>A note on legal advice</AlertTitle>
                    <AlertDescription>
                        OpenCookie gives you the tools to meet GDPR and ePrivacy
                        requirements, but it isn't legal advice. For your exact
                        obligations, check with a qualified data-protection
                        professional.
                    </AlertDescription>
                </Alert>
            </div>
        </AppLayout>
    );
}
