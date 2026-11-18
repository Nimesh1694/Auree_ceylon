import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Product } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    status: 'confirm_prompt' | 'verified_success' | 'already_verified' | 'invalid_token' | 'token_expired';
    message?: string;
    product?: Product | null;
    token?: string;
}

export default function BackInStockVerify({ status, message, product, token }: Props) {
    const { t, locale } = useTranslation();
    const [submitting, setSubmitting] = useState(false);

    const productName = product
        ? locale === 'si' && product.name_si
            ? product.name_si
            : product.name_en
        : '';

    const handleConfirmVerification = (e: React.FormEvent) => {
        e.preventDefault();
        if (submitting || !token) return;

        setSubmitting(true);
        router.post(
            '/back-in-stock/verify',
            { token },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            }
        );
    };

    return (
        <PublicLayout>
            <Head title={`Verify Back-in-Stock Alert | ${t('app_name', 'Aura Luxe')}`} />

            <div className="max-w-xl mx-auto px-4 py-16 sm:py-24">
                <div className="bg-white rounded-2xl border border-stone-200 p-8 shadow-sm text-center">
                    {/* Status Icons & Content */}
                    {status === 'confirm_prompt' && (
                        <div>
                            <div className="w-14 h-14 mx-auto rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-700 text-2xl font-serif mb-4">
                                ✉
                            </div>
                            <h1 className="font-serif text-2xl font-medium text-stone-900 mb-2">
                                {locale === 'si' ? 'දැනුම්දීම් ඉල්ලීම තහවුරු කරන්න' : 'Confirm Alert Request'}
                            </h1>
                            <p className="text-sm text-stone-600 mb-6">
                                {locale === 'si'
                                    ? `ඔබ '${productName}' සඳහා තොග ලැබුණු විට දැනුම්දීම් ලබා ගැනීමට ඉල්ලුම් කර ඇත. මෙම ඉල්ලීම සක්‍රීය කිරීමට කරුණාකර තහවුරු කරන්න.`
                                    : `You requested to receive an alert when '${productName}' is back in stock. Please confirm to activate your alert.`}
                            </p>

                            <form onSubmit={handleConfirmVerification}>
                                <button
                                    type="submit"
                                    disabled={submitting}
                                    className="w-full py-3.5 px-6 bg-stone-900 hover:bg-amber-900 disabled:bg-stone-300 text-white text-xs uppercase tracking-wider font-semibold rounded-xl shadow-md transition-all cursor-pointer"
                                >
                                    {submitting
                                        ? locale === 'si' ? 'තහවුරු කරමින්...' : 'Confirming...'
                                        : locale === 'si' ? 'ඔව්, මට දන්වන්න' : 'Yes, Confirm Alert'}
                                </button>
                            </form>

                            <p className="text-xs text-stone-400 mt-4">
                                {locale === 'si'
                                    ? 'දැනුම්දීම් මඟින් භාණ්ඩය වෙන්කර තැබීමක් සිදු නොවේ.'
                                    : 'Alerts do not reserve inventory and do not guarantee stock availability.'}
                            </p>
                        </div>
                    )}

                    {(status === 'verified_success' || status === 'already_verified') && (
                        <div>
                            <div className="w-14 h-14 mx-auto rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 text-2xl mb-4">
                                ✓
                            </div>
                            <h1 className="font-serif text-2xl font-medium text-stone-900 mb-2">
                                {locale === 'si' ? 'සාර්ථකව තහවුරු කරන ලදී!' : 'Alert Confirmed!'}
                            </h1>
                            <p className="text-sm text-stone-600 mb-6">
                                {message || (locale === 'si'
                                    ? `'${productName}' නැවත තොග ලැබුණු වහාම ඔබට විද්‍යුත් තැපෑලෙන් දන්වනු ලැබේ.`
                                    : `You will receive an email alert as soon as '${productName}' is back in stock.`)}
                            </p>
                            <Link
                                href="/products"
                                className="inline-block py-3 px-6 bg-stone-900 hover:bg-stone-800 text-white text-xs uppercase tracking-wider font-semibold rounded-xl transition-all"
                            >
                                {locale === 'si' ? 'තවත් නිෂ්පාදන බලන්න' : 'Continue Shopping'}
                            </Link>
                        </div>
                    )}

                    {(status === 'invalid_token' || status === 'token_expired') && (
                        <div>
                            <div className="w-14 h-14 mx-auto rounded-full bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-600 text-2xl mb-4">
                                ✕
                            </div>
                            <h1 className="font-serif text-2xl font-medium text-stone-900 mb-2">
                                {status === 'token_expired'
                                    ? locale === 'si' ? 'සබැඳිය කල් ඉකුත් වී ඇත' : 'Link Expired'
                                    : locale === 'si' ? 'වලංගු නොවන සබැඳියකි' : 'Invalid Link'}
                            </h1>
                            <p className="text-sm text-stone-600 mb-6">
                                {message || (locale === 'si'
                                    ? 'මෙම සබැඳිය වලංගු නොවේ හෝ කල් ඉකුත් වී ඇත. කරුණාකර නිෂ්පාදන පිටුවෙන් නැවත ඉල්ලුම් කරන්න.'
                                    : 'This verification link is invalid or has expired. Please subscribe again from the product page.')}
                            </p>
                            <Link
                                href="/products"
                                className="inline-block py-3 px-6 bg-stone-900 hover:bg-stone-800 text-white text-xs uppercase tracking-wider font-semibold rounded-xl transition-all"
                            >
                                {locale === 'si' ? 'නිෂ්පාදන පිටුවට' : 'Browse Products'}
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </PublicLayout>
    );
}
