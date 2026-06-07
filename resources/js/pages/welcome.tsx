import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        title: 'Scan for cookies',
        body: 'Crawl your site like a real browser and detect every cookie, tracker, and storage key — even the ones set by JavaScript.',
    },
    {
        title: 'Consent that blocks',
        body: 'A lightweight banner gates analytics and marketing scripts until the visitor agrees. Default-deny, GDPR-first.',
    },
    {
        title: 'Proof on file',
        body: 'Every choice is logged as tamper-proof evidence and exportable for audits. Reopen and change consent anytime.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between p-6 lg:p-8">
                    <div className="flex items-center gap-2">
                        <div className="flex aspect-square size-9 items-center justify-center rounded-md bg-[#1b1b18] text-white dark:bg-white dark:text-[#1b1b18]">
                            <AppLogoIcon className="size-5" />
                        </div>
                        <span className="text-base font-semibold">
                            OpenCookie
                        </span>
                    </div>
                    <nav className="flex items-center gap-3 text-sm">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 leading-normal hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="inline-block rounded-sm border border-transparent px-5 py-1.5 leading-normal hover:border-[#19140035] dark:hover:border-[#3E3E3A]"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={register()}
                                    className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 leading-normal hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
                                >
                                    Register
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center px-6 py-12 lg:px-8">
                    <div className="flex flex-col items-start gap-8 lg:flex-row lg:items-center lg:justify-between">
                        <div className="max-w-xl">
                            <h1 className="text-4xl font-semibold tracking-tight lg:text-5xl">
                                Cookie consent,
                                <br />
                                done properly.
                            </h1>
                            <p className="mt-4 text-base leading-relaxed text-[#706f6c] dark:text-[#A1A09A]">
                                OpenCookie is a self-serve consent platform for
                                website owners. Scan your site, configure a
                                banner, block trackers until visitors agree, and
                                keep an auditable log — built GDPR-first.
                            </p>
                            <div className="mt-8 flex flex-wrap gap-3 text-sm">
                                <Link
                                    href={auth.user ? dashboard() : register()}
                                    className="inline-block rounded-sm border border-black bg-[#1b1b18] px-6 py-2 font-medium text-white hover:bg-black dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
                                >
                                    {auth.user ? 'Go to dashboard' : 'Get started'}
                                </Link>
                                <a
                                    href="https://github.com/nrgribeiro/opencookie"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-block rounded-sm border border-[#19140035] px-6 py-2 font-medium hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
                                >
                                    View on GitHub
                                </a>
                            </div>
                        </div>

                        <div className="flex size-48 shrink-0 items-center justify-center rounded-2xl bg-[#fff7ed] text-[#1b1b18] lg:size-64 dark:bg-[#1a1207] dark:text-[#EDEDEC]">
                            <AppLogoIcon className="size-28 lg:size-36" />
                        </div>
                    </div>

                    <div className="mt-16 grid gap-6 sm:grid-cols-3">
                        {features.map((f) => (
                            <div
                                key={f.title}
                                className="rounded-lg border border-[#19140014] p-5 dark:border-[#ffffff14]"
                            >
                                <h2 className="font-medium">{f.title}</h2>
                                <p className="mt-2 text-sm leading-relaxed text-[#706f6c] dark:text-[#A1A09A]">
                                    {f.body}
                                </p>
                            </div>
                        ))}
                    </div>
                </main>

                <footer className="mx-auto w-full max-w-5xl px-6 py-8 text-xs text-[#706f6c] lg:px-8 dark:text-[#A1A09A]">
                    OpenCookie — GDPR & ePrivacy consent management.
                </footer>
            </div>
        </>
    );
}
