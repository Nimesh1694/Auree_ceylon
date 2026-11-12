import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface TwoFactorRecoveryCodesProps {
    recoveryCodes: string[];
    newRecoveryCodes?: string[];
}

export default function TwoFactorRecoveryCodes({
    recoveryCodes,
    newRecoveryCodes,
}: TwoFactorRecoveryCodesProps) {
    const [copied, setCopied] = useState(false);
    const codes = newRecoveryCodes && newRecoveryCodes.length > 0 ? newRecoveryCodes : recoveryCodes;

    const handleCopyAll = () => {
        navigator.clipboard.writeText(codes.join('\n'));
        setCopied(true);
        setTimeout(() => setCopied(false), 3000);
    };

    const handleDownload = () => {
        const text = `AuraLuxe Management 2FA Emergency Recovery Codes\nGenerated: ${new Date().toISOString()}\n\n` +
            codes.map((c, i) => `${i + 1}. ${c}`).join('\n') +
            `\n\nEach code can be used exactly once. Store this file in a secure location.`;
        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'auraluxe-recovery-codes.txt';
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <GuestLayout>
            <Head title="Emergency Recovery Codes" />

            <div className="space-y-4">
                <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-950 text-xs">
                    <p className="font-bold text-sm">Two-Factor Authentication is Active</p>
                    <p className="mt-1">
                        Please save your emergency recovery codes. If you ever lose access to your authenticator
                        device, you will need one of these codes to sign in.
                    </p>
                </div>

                <div className="p-4 bg-stone-900 rounded-xl text-white font-mono text-xs">
                    <div className="grid grid-cols-2 gap-2 text-center py-2">
                        {codes.map((code, index) => (
                            <div key={index} className="p-2 bg-stone-800 rounded tracking-wider font-bold">
                                {code}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex items-center justify-between text-xs pt-1">
                    <button
                        type="button"
                        onClick={handleCopyAll}
                        className="px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-semibold rounded-lg cursor-pointer"
                    >
                        {copied ? '✓ All Codes Copied' : 'Copy All Codes'}
                    </button>

                    <button
                        type="button"
                        onClick={handleDownload}
                        className="px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-semibold rounded-lg cursor-pointer"
                    >
                        Download as .txt
                    </button>
                </div>

                <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 text-[11px]">
                    <strong>Important:</strong> Each recovery code can only be used once. Once used, it is permanently invalidated.
                </div>

                <div className="pt-4 border-t border-stone-200 flex justify-end">
                    <Link
                        href={route('admin.dashboard')}
                        className="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white text-xs font-bold rounded-lg shadow-xs"
                    >
                        Proceed to Admin Dashboard &rarr;
                    </Link>
                </div>
            </div>
        </GuestLayout>
    );
}

