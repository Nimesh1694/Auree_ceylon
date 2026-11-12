import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head } from '@inertiajs/react';
import React from 'react';

export default function Terms() {
    const { t } = useTranslation();

    return (
        <PublicLayout>
            <Head title={`${t('terms_title', 'Terms & Conditions')} | ${t('app_name', 'Aura Luxe')}`} />

            <div className="bg-[#F5EFE6] border-b border-stone-200/80 py-14">
                <div className="max-w-4xl mx-auto px-4 text-center">
                    <span className="text-xs uppercase tracking-[0.2em] font-semibold text-rose-900 block mb-2">
                        Legal Agreement
                    </span>
                    <h1 className="font-serif text-3xl sm:text-4xl text-stone-900 font-medium">
                        {t('terms_title', 'Terms & Conditions')}
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
                            1. Acceptance of Terms
                        </h2>
                        <p>
                            By accessing or purchasing from Aura Luxe Cosmetics (Pvt) Ltd, you agree to comply with and be bound by these Terms and Conditions. If you do not agree with any part, please refrain from using our storefront.
                        </p>
                    </section>

                    <section>
                        <h2 className="font-serif text-lg font-medium text-stone-900 mb-2">
                            2. Cash on Delivery (COD) Obligations
                        </h2>
                        <p>
                            By placing an order via Cash on Delivery, the customer undertakes to accept delivery and pay the exact order amount in cash to the designated courier upon arrival at the provided delivery address. Fraudulent or refusal of orders without valid cause may result in suspension from future orders.
                        </p>
                    </section>

                    <section>
                        <h2 className="font-serif text-lg font-medium text-stone-900 mb-2">
                            3. Product Availability & Pricing
                        </h2>
                        <p>
                            All product prices are quoted in Sri Lanka Rupees (LKR) and include applicable taxes unless otherwise stated. We reserve the right to correct pricing typographical errors and adjust stock availability in real time.
                        </p>
                    </section>

                    <section>
                        <h2 className="font-serif text-lg font-medium text-stone-900 mb-2">
                            4. Intellectual Property
                        </h2>
                        <p>
                            All brand names, formulas, logos, product descriptions, photography, and website content are the exclusive intellectual property of Aura Luxe Cosmetics (Pvt) Ltd.
                        </p>
                    </section>
                </div>
            </div>
        </PublicLayout>
    );
}

