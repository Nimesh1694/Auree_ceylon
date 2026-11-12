import { useTranslation } from '@/hooks/useTranslation';
import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import React from 'react';

interface Props extends PageProps {
    prefill?: {
        name: string;
        email: string;
        phone: string;
    } | null;
    flash: PageProps['flash'] & {
        inquiry_number?: string | null;
    };
}

export default function Contact({ prefill }: Props) {
    const { t } = useTranslation();
    const { flash } = usePage<Props>().props;

    const { data, setData, post, processing, reset, errors, recentlySuccessful } = useForm({
        name: prefill?.name || '',
        email: prefill?.email || '',
        phone: prefill?.phone || '',
        subject: '',
        message: '',
        website: '', // Invisible honeypot field for bot deterrence
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/contact', {
            preserveScroll: true,
            onSuccess: () => {
                reset('subject', 'message', 'website');
            },
        });
    };

    return (
        <PublicLayout>
            <Head title={`${t('contact_us', 'Contact Us')} | ${t('app_name', 'Aura Luxe')}`} />

            <div className="bg-[#F5EFE6] border-b border-stone-200/80 py-16">
                <div className="max-w-4xl mx-auto px-4 text-center">
                    <span className="text-xs uppercase tracking-[0.2em] font-semibold text-rose-900 block mb-2">
                        Customer Care
                    </span>
                    <h1 className="font-serif text-4xl text-stone-900 font-medium tracking-tight">
                        {t('contact_title', 'Get in Touch')}
                    </h1>
                    <p className="text-xs sm:text-sm text-stone-600 mt-3 max-w-md mx-auto leading-relaxed">
                        {t(
                            'contact_subtitle',
                            'Have questions about our botanical formulas or your order? We are here to assist.'
                        )}
                    </p>
                </div>
            </div>

            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-10">
                    {/* Contact Details Column */}
                    <div className="space-y-6 text-xs text-stone-600">
                        <div className="bg-white rounded-2xl border border-stone-200 p-6 shadow-xs space-y-4">
                            <h3 className="font-serif text-base font-semibold text-stone-900">
                                Contact Information
                            </h3>
                            <div>
                                <span className="font-semibold text-stone-800 block">Customer Care Hotline</span>
                                <span className="text-stone-600 mt-0.5 block">+94 11 234 5678</span>
                                <span className="text-[11px] text-stone-400">Mon - Sat: 9:00 AM - 6:00 PM</span>
                            </div>
                            <div>
                                <span className="font-semibold text-stone-800 block">Support Email</span>
                                <span className="text-stone-600 mt-0.5 block">care@auraluxe.lk</span>
                            </div>
                            <div>
                                <span className="font-semibold text-stone-800 block">Head Office</span>
                                <span className="text-stone-600 mt-0.5 block leading-relaxed">
                                    Aura Luxe Cosmetics (Pvt) Ltd<br />
                                    No. 45, Galle Road, Colombo 03,<br />
                                    Sri Lanka.
                                </span>
                            </div>
                        </div>

                        <div className="p-4 bg-emerald-50 rounded-xl border border-emerald-200 text-emerald-800 text-xs">
                            <strong>Order Tracking Help:</strong>
                            <p className="mt-1 text-[11px] text-emerald-700">
                                Have your Order Number and phone ready? You can track package status immediately on our tracking page.
                            </p>
                        </div>
                    </div>

                    {/* Contact Form Column */}
                    <div className="md:col-span-2 bg-white rounded-2xl border border-stone-200 p-6 sm:p-8 shadow-xs">
                        <h2 className="font-serif text-xl font-medium text-stone-900 mb-6">
                            Send Us a Message
                        </h2>

                        {recentlySuccessful && (
                            <div className="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs space-y-1">
                                <div className="font-semibold flex items-center gap-1.5">
                                    <span className="text-emerald-700 font-bold">✓</span>
                                    <span>Inquiry Submitted Successfully!</span>
                                </div>
                                <p className="text-emerald-700 leading-relaxed">
                                    {flash?.success || 'Thank you for your inquiry! Our beauty consultant will reply within 24 hours.'}
                                </p>
                            </div>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-4 text-xs">
                            {/* Honeypot field for bot deterrence - hidden from human view */}
                            <div className="hidden" aria-hidden="true" style={{ display: 'none' }}>
                                <label htmlFor="website">Website</label>
                                <input
                                    type="text"
                                    id="website"
                                    name="website"
                                    value={data.website}
                                    onChange={(e) => setData('website', e.target.value)}
                                    tabIndex={-1}
                                    autoComplete="off"
                                />
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="font-semibold text-stone-800 block mb-1">
                                        Your Name <span className="text-rose-600">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                                    />
                                    {errors.name && <p className="text-rose-600 mt-1">{errors.name}</p>}
                                </div>
                                <div>
                                    <label className="font-semibold text-stone-800 block mb-1">
                                        Email Address <span className="text-rose-600">*</span>
                                    </label>
                                    <input
                                        type="email"
                                        required
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                                    />
                                    {errors.email && <p className="text-rose-600 mt-1">{errors.email}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="font-semibold text-stone-800 block mb-1">Phone Number</label>
                                    <input
                                        type="tel"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="0771234567"
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                                    />
                                    {errors.phone && <p className="text-rose-600 mt-1">{errors.phone}</p>}
                                </div>
                                <div>
                                    <label className="font-semibold text-stone-800 block mb-1">
                                        Subject <span className="text-rose-600">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={data.subject}
                                        onChange={(e) => setData('subject', e.target.value)}
                                        placeholder="Product inquiry, order status..."
                                        className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900 py-2.5"
                                    />
                                    {errors.subject && <p className="text-rose-600 mt-1">{errors.subject}</p>}
                                </div>
                            </div>

                            <div>
                                <label className="font-semibold text-stone-800 block mb-1">
                                    Message <span className="text-rose-600">*</span>
                                </label>
                                <textarea
                                    required
                                    rows={4}
                                    value={data.message}
                                    onChange={(e) => setData('message', e.target.value)}
                                    placeholder="Write your message here..."
                                    className="w-full text-xs rounded-lg border-stone-300 focus:border-rose-900 focus:ring-rose-900"
                                />
                                {errors.message && <p className="text-rose-600 mt-1">{errors.message}</p>}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="py-3 px-8 bg-stone-900 hover:bg-rose-900 disabled:bg-stone-300 text-white text-xs uppercase tracking-[0.2em] font-semibold rounded-xl shadow-md transition-all cursor-pointer"
                            >
                                {processing ? 'Sending...' : t('send_message', 'Send Message')}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
