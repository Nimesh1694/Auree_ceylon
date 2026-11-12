import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function TwoFactorChallenge() {
    const [recovery, setRecovery] = useState(false);

    const totpForm = useForm({
        code: '',
    });

    const recoveryForm = useForm({
        recovery_code: '',
    });

    const submitTotp: FormEventHandler = (e) => {
        e.preventDefault();
        totpForm.post(route('two-factor.verify'));
    };

    const submitRecovery: FormEventHandler = (e) => {
        e.preventDefault();
        recoveryForm.post(route('two-factor.recovery'));
    };

    return (
        <GuestLayout>
            <Head title="Two-Factor Authentication" />

            <div className="mb-4 text-sm text-stone-600">
                {!recovery ? (
                    <>
                        Please confirm access to your management account by entering the 6-digit authentication
                        code provided by your authenticator app.
                    </>
                ) : (
                    <>
                        Please confirm access to your management account by entering one of your emergency recovery
                        codes.
                    </>
                )}
            </div>

            {!recovery ? (
                <form onSubmit={submitTotp}>
                    <div>
                        <InputLabel htmlFor="code" value="Authentication Code" />

                        <TextInput
                            id="code"
                            type="text"
                            inputMode="numeric"
                            name="code"
                            value={totpForm.data.code}
                            className="mt-1 block w-full text-center tracking-widest font-mono text-lg"
                            autoComplete="one-time-code"
                            isFocused={true}
                            maxLength={6}
                            placeholder="000000"
                            onChange={(e) => totpForm.setData('code', e.target.value.replace(/\D/g, ''))}
                        />

                        <InputError message={totpForm.errors.code} className="mt-2" />
                    </div>

                    <div className="mt-4 flex items-center justify-between">
                        <button
                            type="button"
                            onClick={() => {
                                setRecovery(true);
                                totpForm.reset();
                            }}
                            className="text-xs text-stone-600 underline hover:text-stone-900 cursor-pointer"
                        >
                            Use an emergency recovery code
                        </button>

                        <PrimaryButton disabled={totpForm.processing}>
                            Verify Code
                        </PrimaryButton>
                    </div>
                </form>
            ) : (
                <form onSubmit={submitRecovery}>
                    <div>
                        <InputLabel htmlFor="recovery_code" value="Emergency Recovery Code" />

                        <TextInput
                            id="recovery_code"
                            type="text"
                            name="recovery_code"
                            value={recoveryForm.data.recovery_code}
                            className="mt-1 block w-full font-mono text-center uppercase tracking-wider"
                            autoComplete="off"
                            isFocused={true}
                            placeholder="XXXX-XXXX"
                            onChange={(e) => recoveryForm.setData('recovery_code', e.target.value.toUpperCase())}
                        />

                        <InputError message={recoveryForm.errors.recovery_code} className="mt-2" />
                    </div>

                    <div className="mt-4 flex items-center justify-between">
                        <button
                            type="button"
                            onClick={() => {
                                setRecovery(false);
                                recoveryForm.reset();
                            }}
                            className="text-xs text-stone-600 underline hover:text-stone-900 cursor-pointer"
                        >
                            Use an authenticator code
                        </button>

                        <PrimaryButton disabled={recoveryForm.processing}>
                            Verify Recovery Code
                        </PrimaryButton>
                    </div>
                </form>
            )}

            <div className="mt-6 pt-4 border-t border-stone-200 text-center">
                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="text-xs text-stone-500 hover:text-stone-800 underline cursor-pointer"
                >
                    Log out of management session
                </Link>
            </div>
        </GuestLayout>
    );
}

