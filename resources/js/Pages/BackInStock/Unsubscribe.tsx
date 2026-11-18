import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Product } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';

interface Props {
    status: 'confirm_prompt' | 'unsubscribed_success' | 'already_unsubscribed' | 'invalid_signature';
    message?: string;
    product?: Product | null;
    subscriptionId?: number;
}

export default function BackInStockUnsubscribe({ status, message, product, subscriptionId }: Props) {
    const { t, locale } = useTranslation();
    const [submitting, setSubmitting] = useState(false);

    const productName = product
        ? locale === 'si' && product.name_si
            ? product.name_si
            : product.name_en
        : '';

    const handleConfirmUnsubscribe = (e: React.FormEvent) => {
        e.preventDefault();
        if (submitting || !subscriptionId) return;

        setSubmitting(true);
        // Post back to current URL which includes the valid signature query parameters
        router.post(
            window.location.href,
            {},
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            }
        );
    };

    return (
        <PublicLayout>
            <Head title={`Unsubscribe | ${t('app_name', 'Aura Luxe')}`} />

            <div className="max-w-xl mx-auto px-4 py-16 sm:py-24">
                <div className="bg-white rounded-2xl border border-stone-200 p-8 shadow-sm text-center">
                    {status === 'confirm_prompt' && (
                        <div>
                            <div className="w-14 h-14 mx-auto rounded-full bg-stone-100 flex items-center justify-center text-stone-600 text-2xl mb-4">
                                🔕
                            </div>
                            <h1 className="font-serif text-2xl font-medium text-stone-900 mb-2">
                                {locale === 'si' ? 'දැනුම්දීම් වලින් ඉවත් වන්න' : 'Unsubscribe from Alerts'}
                            </h1>
                            <p className="text-sm text-stone-600 mb-6">
                                {locale === 'si'
                                    ? `'${productName}' නැවත තොග ලැබුණු විට ලැබෙන දැනුම්දීම් වලින් ඉවත් වීමට ඔබට අවශ්‍යද?`
                                    : `Are you sure you want to stop receiving back-in-stock alerts for '${productName}'?`}
                            </p>

                            <form onSubmit={handleConfirmUnsubscribe}>
                                <button
                                    type="submit"
                                    disabled={submitting}
                                    className="w-full py-3.5 px-6 bg-rose-900 hover:bg-rose-800 disabled:bg-stone-300 text-white text-xs uppercase tracking-wider font-semibold rounded-xl shadow-md transition-all cursor-pointer"
                                >
                                    {submitting
                                        ? locale === 'si' ? 'ඉවත් වෙමින්...' : 'Unsubscribing...'
                                        : locale === 'si' ? 'දැනුම්දීම් නවත්වන්න' : 'Confirm Unsubscribe'}
                                </button>
                            </form>
                        </div>
                    )}

                    {(status === 'unsubscribed_success' || status === 'already_unsubscribed') && (
                        <div>
                            <div className="w-14 h-14 mx-auto rounded-full bg-stone-100 flex items-center justify-center text-stone-700 text-2xl mb-4">
                                ✓
                            </div>
                            <h1 className="font-serif text-2xl font-medium text-stone-900 mb-2">
                                {locale === 'si' ? 'සාර්ථකව ඉවත් කරන ලදී' : 'Unsubscribed'}
                            </h1>
                            <p className="text-sm text-stone-600 mb-6">
                                {message || (locale === 'si'
                                    ? `'${productName}' සඳහා වන දැනුම්දීම් වලින් ඔබව සාර්ථකව ඉවත් කරන ලදී.`
                                    : `You have been unsubscribed from back-in-stock alerts for '${productName}'.`)}
                            </p>
                            <Link
                                href="/products"
                                className="inline-block py-3 px-6 bg-stone-900 hover:bg-stone-800 text-white text-xs uppercase tracking-wider font-semibold rounded-xl transition-all"
                            >
                                {locale === 'si' ? 'නිෂ්පාදන පිටුවට' : 'Browse Products'}
                            </Link>
                        </div>
                    )}

                    {status === 'invalid_signature' && (
                        <div>
                            <div className="w-14 h-14 mx-auto rounded-full bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-600 text-2xl mb-4">
                                ✕
                            </div>
                            <h1 className="font-serif text-2xl font-medium text-stone-900 mb-2">
                                {locale === 'si' ? 'වලංගු නොවන සබැඳියකි' : 'Invalid or Expired Link'}
                            </h1>
                            <p className="text-sm text-stone-600 mb-6">
                                {message || (locale === 'si'
                                    ? 'මෙම සබැඳිය වලංගු නොවේ හෝ කල් ඉකුත් වී ඇත.'
                                    : 'This unsubscribe link is invalid or has expired.')}
                            </p>
                            <Link
                                href="/products"
                                className="inline-block py-3 px-6 bg-stone-900 hover:bg-stone-800 text-white text-xs uppercase tracking-wider font-semibold rounded-xl transition-all"
                            >
                                {locale === 'si' ? 'මුල් පිටුවට' : 'Back to Shop'}
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </PublicLayout>
    );
}
