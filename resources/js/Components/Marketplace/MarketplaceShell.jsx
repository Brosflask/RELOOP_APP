// Developer 3. Public pages: signed-in users get the normal app layout, guests a simple header.
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import FlashMessages from '@/Components/UI/FlashMessages';
import { Link, usePage } from '@inertiajs/react';

export default function MarketplaceShell({ header, children }) {
    const { auth } = usePage().props;
    if (auth?.user) return <AuthenticatedLayout header={header}>{children}</AuthenticatedLayout>;

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="border-b border-gray-200 bg-white">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
                    <Link href={route('marketplace.index')} className="text-lg font-semibold text-green-700">Reloop Marketplace</Link>
                    <div className="flex items-center gap-4 text-sm">
                        <Link href={route('login')} className="text-gray-700 hover:text-gray-900">Log in</Link>
                        {route().has('register') && (
                            <Link href={route('register')} className="rounded-md bg-green-700 px-3 py-1.5 font-medium text-white hover:bg-green-800">Sign up</Link>
                        )}
                    </div>
                </div>
            </nav>
            {header && <header className="bg-white shadow-sm"><div className="mx-auto max-w-7xl px-4 py-5">{header}</div></header>}
            <FlashMessages />
            <main>{children}</main>
        </div>
    );
}
