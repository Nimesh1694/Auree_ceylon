import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface TwoFactorSetupProps {
    secret: string;
    formattedSecret: string;
    otpAuthUrl: string;
    isEnrolled: boolean;
}

export default function TwoFactorSetup({
    secret,
    formattedSecret,
    otpAuthUrl,
    isEnrolled,
}: TwoFactorSetupProps) {
    const [copied, setCopied] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });

    const handleCopy = () => {
        navigator.clipboard.writeText(secret);
        setCopied(true);
        setTimeout(() => setCopied(false), 3000);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('two-factor.confirm'));
    };

    return (
        <GuestLayout>
            <Head title="Configure Two-Factor Authentication" />

            <div className="space-y-4">
                <div>
                    <h2 className="text-lg font-bold text-stone-900">
                        Two-Factor Authentication Setup
                    </h2>
                    <p className="mt-1 text-xs text-stone-600">
                        Two-Factor Authentication is strictly mandatory for all administrative and management accounts to safeguard
                        financial operations, commission ledgers, and network data.
                    </p>
                </div>

                {/* Instructions */}
                <div className="p-4 bg-stone-50 border border-stone-200 rounded-xl space-y-3 text-xs">
                    <p className="font-semibold text-stone-800">
                        Step 1: Add account to your Authenticator App
                    </p>
                    <p className="text-stone-600">
                        Open Google Authenticator, Microsoft Authenticator, Apple Passwords, or 1Password, choose <strong>"Enter a setup key"</strong>, and enter the secret below:
                    </p>

                    <div className="p-3 bg-white border border-stone-300 rounded-lg flex items-center justify-between">
                        <span className="font-mono font-bold text-sm text-stone-900 tracking-wider">
                            {formattedSecret}
                        </span>
                        <button
                            type="button"
                            onClick={handleCopy}
                            className="px-2.5 py-1 text-xs font-semibold bg-stone-100 hover:bg-stone-200 text-stone-700 rounded transition cursor-pointer"
                        >
                            {copied ? '✓ Copied' : 'Copy Key'}
                        </button>
                    </div>

                    <div className="text-[11px] text-stone-500">
                        <span>Account Type: <strong>Time-based (TOTP, 30s)</strong></span>
                    </div>
                </div>

                {/* Confirmation Form */}
                <form onSubmit={submit} className="space-y-4 pt-2">
                    <div>
                        <p className="font-semibold text-xs text-stone-800 mb-1">
                            Step 2: Confirm Authenticator Code
                        </p>
                        <InputLabel htmlFor="code" value="Enter 6-digit code from authenticator app" />

                        <TextInput
                            id="code"
                            type="text"
                            inputMode="numeric"
                            name="code"
                            value={data.code}
                            className="mt-1 block w-full text-center font-mono tracking-widest text-lg"
                            autoComplete="one-time-code"
                            isFocused={true}
                            maxLength={6}
                            placeholder="000000"
                            onChange={(e) => setData('code', e.target.value.replace(/\D/g, ''))}
                        />

                        <InputError message={errors.code} className="mt-2" />
                    </div>

                    <div className="flex items-center justify-between pt-2">
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="text-xs text-stone-500 hover:text-stone-800 underline cursor-pointer"
                        >
                            Log Out
                        </Link>

                        <PrimaryButton disabled={processing || data.code.length !== 6}>
                            Verify & Activate MFA
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </GuestLayout>
    );
}

