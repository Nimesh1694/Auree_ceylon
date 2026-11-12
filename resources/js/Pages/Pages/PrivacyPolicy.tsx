import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head } from '@inertiajs/react';
import React from 'react';

export default function PrivacyPolicy() {
    const { t } = useTranslation();

    return (
        <PublicLayout>
            <Head title={`${t('privacy_policy_title', 'Privacy Policy')} | ${t('app_name', 'Aura Luxe')}`} />

            <div className="bg-[#F5EFE6] border-b border-stone-200/80 py-14">
                <div className="max-w-4xl mx-auto px-4 text-center">
                    <span className="text-xs uppercase tracking-[0.2em] font-semibold text-rose-900 block mb-2">
                        Data Protection
                    </span>
                    <h1 className="font-serif text-3xl sm:text-4xl text-stone-900 font-medium">
                        {t('privacy_policy_title', 'Privacy Policy')}
                    </h1>
                    <p className="text-xs sm:text-sm text-stone-600 mt-2">
                        Last updated: November 2026
                    </p>
                </div>
            </div>

            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-14 text-stone-700 text-xs sm:text-sm leading-relaxed space-y-6">
                <div className="bg-white rounded-2xl border border-stone-200 p-8 shadow-xs space-y-6">
                    <section>
                        <h2 className="font-serif text-lg font-medium text-stone-900 mb-2">
                            1. Information We Collect
                        </h2>
                        <p>
                            When you place an order on Aura Luxe, we collect necessary delivery information including your full name, delivery address, phone number, and optional email address. We do not store credit card numbers because all purchases are fulfilled strictly via Cash on Delivery (COD).
                        </p>
                    </section>

                    <section>
                        <h2 className="font-serif text-lg font-medium text-stone-900 mb-2">
                            2. Use of Delivery Information
                        </h2>
                        <p>
                            Your information is exclusively used to process, ship, and deliver your cosmetic package, send order status updates, and coordinate with licensed courier delivery personnel.
                        </p>
                    </section>

                    <section>
                        <h2 className="font-serif text-lg font-medium text-stone-900 mb-2">
                            3. Order Attribution & Referral Cookies
                        </h2>
                        <p>
                            If you arrive via an authorized Aura Luxe agent recommendation link, a temporary referral identifier is stored solely to credit genuine product sale commissions to that independent agent upon delivered order completion.
                        </p>
                    </section>

                    <section>
                        <h2 className="font-serif text-lg font-medium text-stone-900 mb-2">
                            4. Protection of Customer Data
                        </h2>
                        <p>
                            We employ strict server-side authentication, encrypted sessions, and access control policies. We never sell, rent, or trade your personal information to third-party marketing brokers.
                        </p>
                    </section>
                </div>
            </div>
        </PublicLayout>
    );
}

