import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link } from '@inertiajs/react';
import React from 'react';

export default function ReturnPolicy() {
    const { t } = useTranslation();

    return (
        <PublicLayout>
            <Head title={`${t('return_policy_title', 'Return Policy')} | ${t('app_name', 'Aura Luxe')}`} />

            <div className="bg-[#F5EFE6] border-b border-stone-200/80 py-14">
                <div className="max-w-4xl mx-auto px-4 text-center">
                    <span className="text-xs uppercase tracking-[0.2em] font-semibold text-rose-900 block mb-2">
                        Trust & Guarantee
                    </span>
                    <h1 className="font-serif text-3xl sm:text-4xl text-stone-900 font-medium">
                        {t('return_policy_title', 'Return & Refund Policy')}
                    </h1>
                    <p className="text-xs sm:text-sm text-stone-600 mt-2">
                        Last updated: November 2026
                    </p>
                </div>
            </div>

            <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-14 text-stone-700 text-xs sm:text-sm leading-relaxed space-y-8">
                <div className="bg-white rounded-2xl border border-stone-200 p-8 shadow-xs space-y-6">
                    <section>
                        <h2 className="font-serif text-lg font-medium text-stone-900 mb-2">
                            1. 7-Day Return Eligibility
                        </h2>
                        <p>
                            We take utmost pride in the craftsmanship and purity of our botanical cosmetic formulas. If your package arrives damaged in transit, or if you receive an incorrect product, you may request an exchange or return within <strong>7 calendar days</strong> of receiving your delivery.
                        </p>
                    </section>

                    <section>
                        <h2 className="font-serif text-lg font-medium text-stone-900 mb-2">
                            2. Conditions for Cosmetic Products
                        </h2>
                        <p>
                            Due to strict hygiene, cosmetic safety, and Sri Lanka health standards, products must be returned in their original, unopened condition with tamper-evident seals fully intact. Products that have been unsealed, opened, or used cannot be returned unless verified defective.
                        </p>
                    </section>

                    <section>
                        <h2 className="font-serif text-lg font-medium text-stone-900 mb-2">
                            3. Cash on Delivery (COD) Refunds
                        </h2>
                        <p>
                            For orders paid via Cash on Delivery, approved refunds are remitted via secure Sri Lankan bank transfer or provided as an Aura Luxe store credit within 3 to 5 business days following return inspection at our warehouse.
                        </p>
                    </section>

                    <section>
                        <h2 className="font-serif text-lg font-medium text-stone-900 mb-2">
                            4. How to Initiate a Return
                        </h2>
                        <p>
                            Please reach out to our Customer Care team via our <Link href="/contact" className="text-rose-800 underline font-medium">Contact Form</Link> or call our hotline at <strong>+94 11 234 5678</strong> with your Order Number and photos of the damaged/incorrect parcel.
                        </p>
                    </section>
                </div>
            </div>
        </PublicLayout>
    );
}

