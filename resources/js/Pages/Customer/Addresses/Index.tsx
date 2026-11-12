import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import * as Icons from '@/Components/Icons';
import { PageProps } from '@/types';

export interface CustomerAddress {
    id: number;
    user_id: number;
    label: string;
    recipient_name: string;
    phone: string;
    alternative_phone: string | null;
    address_line_1: string;
    address_line_2: string | null;
    city: string;
    district: string;
    postal_code: string | null;
    delivery_instructions: string | null;
    is_default: boolean;
    full_address?: string;
    created_at?: string;
}

interface Props extends PageProps {
    addresses: CustomerAddress[];
    districts: string[];
    maxAddresses: number;
    addressCount: number;
    customer: {
        name: string;
        email: string;
    };
}

export default function AddressBookIndex({
    addresses,
    districts,
    maxAddresses = 10,
    addressCount = 0,
    customer,
}: Props) {
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingAddress, setEditingAddress] = useState<CustomerAddress | null>(null);
    const [deletingAddress, setDeletingAddress] = useState<CustomerAddress | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    // Form state for creating and updating addresses
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        label: 'Home',
        recipient_name: customer?.name || '',
        phone: '',
        alternative_phone: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        district: 'Colombo',
        postal_code: '',
        delivery_instructions: '',
        is_default: addresses.length === 0,
    });

    const openCreateModal = () => {
        clearErrors();
        reset();
        setEditingAddress(null);
        setData({
            label: 'Home',
            recipient_name: customer?.name || '',
            phone: '',
            alternative_phone: '',
            address_line_1: '',
            address_line_2: '',
            city: '',
            district: 'Colombo',
            postal_code: '',
            delivery_instructions: '',
            is_default: addresses.length === 0,
        });
        setIsModalOpen(true);
    };

    const openEditModal = (address: CustomerAddress) => {
        clearErrors();
        setEditingAddress(address);
        setData({
            label: address.label,
            recipient_name: address.recipient_name,
            phone: address.phone,
            alternative_phone: address.alternative_phone || '',
            address_line_1: address.address_line_1,
            address_line_2: address.address_line_2 || '',
            city: address.city,
            district: address.district,
            postal_code: address.postal_code || '',
            delivery_instructions: address.delivery_instructions || '',
            is_default: address.is_default,
        });
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setEditingAddress(null);
        reset();
        clearErrors();
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingAddress) {
            put(`/customer/addresses/${editingAddress.id}`, {
                onSuccess: () => closeModal(),
            });
        } else {
            post('/customer/addresses', {
                onSuccess: () => closeModal(),
            });
        }
    };

    const handleSetDefault = (address: CustomerAddress) => {
        router.post(
            `/customer/addresses/${address.id}/set-default`,
            {},
            { preserveScroll: true }
        );
    };

    const confirmDelete = () => {
        if (!deletingAddress) return;
        setIsDeleting(true);
        router.delete(`/customer/addresses/${deletingAddress.id}`, {
            onFinish: () => {
                setIsDeleting(false);
                setDeletingAddress(null);
            },
        });
    };

    const isAtLimit = addressCount >= maxAddresses;

    return (
        <PublicLayout>
            <Head title="Saved Delivery Addresses — Aura Luxe" />

            <div className="min-h-screen bg-stone-50/60 pb-16 pt-8 font-sans">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                    {/* Header Banner with Navigation */}
                    <div className="bg-white border border-stone-200/80 rounded-2xl p-6 sm:p-8 shadow-xs">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                            <div>
                                <div className="flex items-center gap-2.5">
                                    <h1 className="font-serif text-2xl sm:text-3xl font-medium tracking-tight text-stone-900">
                                        Saved Address Book
                                    </h1>
                                    <span className="inline-flex items-center gap-1 text-[11px] font-semibold tracking-wide uppercase px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200/60 font-mono">
                                        {addressCount} / {maxAddresses} Saved
                                    </span>
                                </div>
                                <p className="text-xs sm:text-sm text-stone-500 mt-1 font-light">
                                    Manage your delivery addresses for seamless Cash on Delivery checkout
                                </p>
                            </div>

                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={openCreateModal}
                                    disabled={isAtLimit}
                                    className={`inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider rounded-xl transition-all shadow-xs cursor-pointer ${
                                        isAtLimit
                                            ? 'bg-stone-300 text-stone-500 cursor-not-allowed'
                                            : 'bg-stone-900 hover:bg-stone-800 text-white'
                                    }`}
                                    title={isAtLimit ? 'Maximum limit of 10 addresses reached' : 'Add a new delivery address'}
                                >
                                    <Icons.Plus className="w-4 h-4 text-amber-300" />
                                    <span>Add New Address</span>
                                </button>
                            </div>
                        </div>

                        {/* Customer Navigation Tabs */}
                        <div className="mt-8 pt-6 border-t border-stone-100 flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
                            <Link
                                href="/dashboard"
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium text-stone-600 hover:text-stone-900 hover:bg-stone-100 transition-colors cursor-pointer shrink-0"
                            >
                                <Icons.LayoutDashboard className="w-3.5 h-3.5 text-stone-500" />
                                <span>Overview</span>
                            </Link>

                            <Link
                                href="/customer/orders"
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium text-stone-600 hover:text-stone-900 hover:bg-stone-100 transition-colors cursor-pointer shrink-0"
                            >
                                <Icons.Boxes className="w-3.5 h-3.5 text-stone-500" />
                                <span>My Orders</span>
                            </Link>

                            <Link
                                href="/track"
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium text-stone-600 hover:text-stone-900 hover:bg-stone-100 transition-colors cursor-pointer shrink-0"
                            >
                                <Icons.Truck className="w-3.5 h-3.5 text-stone-500" />
                                <span>Track Order</span>
                            </Link>

                            <Link
                                href="/profile"
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-medium text-stone-600 hover:text-stone-900 hover:bg-stone-100 transition-colors cursor-pointer shrink-0"
                            >
                                <Icons.User className="w-3.5 h-3.5 text-stone-500" />
                                <span>Account Profile</span>
                            </Link>

                            <Link
                                href="/customer/addresses"
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-stone-900 text-white shadow-xs cursor-pointer shrink-0"
                            >
                                <Icons.MapPin className="w-3.5 h-3.5 text-amber-300" />
                                <span>Address Book</span>
                            </Link>
                        </div>
                    </div>

                    {/* Address Limit Banner (if reached) */}
                    {isAtLimit && (
                        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-center justify-between text-xs text-amber-900">
                            <div className="flex items-center gap-2">
                                <Icons.AlertCircle className="w-4 h-4 text-amber-600 shrink-0" />
                                <span>
                                    You have reached the maximum limit of <strong>10 saved addresses</strong>. To add a new location, please edit or remove an existing address.
                                </span>
                            </div>
                        </div>
                    )}

                    {/* Addresses Grid or Empty State */}
                    {addresses.length === 0 ? (
                        <div className="bg-white border border-stone-200/80 rounded-2xl p-12 text-center shadow-xs">
                            <div className="w-16 h-16 rounded-full bg-stone-100 text-stone-400 flex items-center justify-center mx-auto mb-4">
                                <Icons.MapPin className="w-8 h-8 text-stone-400" />
                            </div>
                            <h3 className="font-serif text-lg font-medium text-stone-900 mb-1">
                                No Saved Addresses Yet
                            </h3>
                            <p className="text-xs text-stone-500 max-w-sm mx-auto mb-6">
                                Save your delivery addresses here for rapid 1-click Cash on Delivery checkout on all future purchases.
                            </p>
                            <button
                                type="button"
                                onClick={openCreateModal}
                                className="inline-flex items-center gap-2 px-4 py-2.5 bg-stone-900 hover:bg-stone-800 text-white text-xs font-semibold uppercase tracking-wider rounded-xl transition-all shadow-xs cursor-pointer"
                            >
                                <Icons.Plus className="w-4 h-4 text-amber-300" />
                                <span>Add Your First Address</span>
                            </button>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {addresses.map((address) => (
                                <div
                                    key={address.id}
                                    className={`bg-white rounded-2xl border transition-all flex flex-col justify-between p-6 shadow-xs relative ${
                                        address.is_default
                                            ? 'border-amber-400/80 ring-1 ring-amber-300/50'
                                            : 'border-stone-200/80 hover:border-stone-300'
                                    }`}
                                >
                                    <div>
                                        {/* Card Top: Label & Badges */}
                                        <div className="flex items-center justify-between gap-2 mb-3">
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-lg bg-stone-100 text-stone-800 font-mono">
                                                    {address.label || 'Address'}
                                                </span>
                                                {address.is_default && (
                                                    <span className="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 border border-amber-300/60 font-mono">
                                                        <Icons.Check className="w-3 h-3 text-amber-700" />
                                                        Default
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        {/* Recipient Information */}
                                        <h4 className="font-serif text-base font-semibold text-stone-900 mb-1">
                                            {address.recipient_name}
                                        </h4>

                                        <div className="space-y-1 text-xs text-stone-600 mb-4">
                                            <p className="flex items-center gap-1.5 text-stone-700 font-medium">
                                                <Icons.Phone className="w-3.5 h-3.5 text-stone-400 shrink-0" />
                                                <span>{address.phone}</span>
                                                {address.alternative_phone && (
                                                    <span className="text-stone-400 font-normal">/ {address.alternative_phone}</span>
                                                )}
                                            </p>
                                        </div>

                                        {/* Address Lines */}
                                        <div className="text-xs text-stone-700 space-y-0.5 pt-2 border-t border-stone-100 leading-relaxed">
                                            <p className="font-medium text-stone-800">{address.address_line_1}</p>
                                            {address.address_line_2 && (
                                                <p className="text-stone-600">{address.address_line_2}</p>
                                            )}
                                            <p className="text-stone-900 font-semibold">
                                                {address.city}, {address.district}
                                                {address.postal_code ? ` — ${address.postal_code}` : ''}
                                            </p>
                                        </div>

                                        {/* Delivery Instructions (if provided) */}
                                        {address.delivery_instructions && (
                                            <div className="mt-3 p-2.5 bg-stone-50 rounded-xl text-[11px] text-stone-600 border border-stone-100">
                                                <span className="font-semibold text-stone-700 block mb-0.5">Note:</span>
                                                {address.delivery_instructions}
                                            </div>
                                        )}
                                    </div>

                                    {/* Card Footer Actions */}
                                    <div className="mt-6 pt-4 border-t border-stone-100 flex items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => openEditModal(address)}
                                                className="text-xs font-semibold text-stone-700 hover:text-stone-900 px-2.5 py-1.5 rounded-lg hover:bg-stone-100 transition-colors cursor-pointer"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setDeletingAddress(address)}
                                                className="text-xs font-semibold text-rose-600 hover:text-rose-700 px-2.5 py-1.5 rounded-lg hover:bg-rose-50 transition-colors cursor-pointer"
                                            >
                                                Delete
                                            </button>
                                        </div>

                                        {!address.is_default && (
                                            <button
                                                type="button"
                                                onClick={() => handleSetDefault(address)}
                                                className="text-xs font-semibold text-amber-700 hover:text-amber-800 px-2.5 py-1.5 rounded-lg hover:bg-amber-50 transition-colors cursor-pointer"
                                            >
                                                Set Default
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Add / Edit Address Modal */}
            {isModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/60 backdrop-blur-xs overflow-y-auto">
                    <div className="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-stone-200 my-8">
                        <div className="flex items-center justify-between pb-4 border-b border-stone-100">
                            <div>
                                <h3 className="font-serif text-xl font-medium text-stone-900">
                                    {editingAddress ? 'Edit Delivery Address' : 'Add New Delivery Address'}
                                </h3>
                                <p className="text-xs text-stone-500 mt-0.5">
                                    {editingAddress ? 'Update recipient and address details' : 'Save address to your account for fast checkout'}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={closeModal}
                                className="p-2 text-stone-400 hover:text-stone-600 rounded-xl hover:bg-stone-100 transition-colors"
                            >
                                <Icons.XCircle className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Limit error if present */}
                        {(errors as any)?.address_limit && (
                            <div className="mt-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700">
                                {(errors as any).address_limit}
                            </div>
                        )}

                        <form onSubmit={handleFormSubmit} className="mt-6 space-y-4">
                            {/* Label & Default Checkbox */}
                            <div className="grid grid-cols-2 gap-3 items-center">
                                <div>
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        Label <span className="text-stone-400 font-normal">(e.g. Home, Office)</span>
                                    </label>
                                    <input
                                        type="text"
                                        value={data.label}
                                        onChange={(e) => setData('label', e.target.value)}
                                        placeholder="Home"
                                        className="w-full text-xs rounded-xl border-stone-300 focus:border-stone-900 focus:ring-stone-900 py-2"
                                    />
                                    {errors.label && <p className="text-xs text-rose-600 mt-1">{errors.label}</p>}
                                </div>

                                <div className="pt-5">
                                    <label className="flex items-center gap-2 cursor-pointer text-xs font-medium text-stone-800">
                                        <input
                                            type="checkbox"
                                            checked={data.is_default}
                                            onChange={(e) => setData('is_default', e.target.checked)}
                                            disabled={editingAddress?.is_default && addresses.length === 1}
                                            className="rounded border-stone-300 text-stone-900 focus:ring-stone-900"
                                        />
                                        <span>Set as default address</span>
                                    </label>
                                </div>
                            </div>

                            {/* Recipient Full Name */}
                            <div>
                                <label className="text-xs font-semibold text-stone-800 block mb-1">
                                    Recipient Full Name <span className="text-rose-600">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.recipient_name}
                                    onChange={(e) => setData('recipient_name', e.target.value)}
                                    placeholder="e.g. Kasun Fernando"
                                    className="w-full text-xs rounded-xl border-stone-300 focus:border-stone-900 focus:ring-stone-900 py-2"
                                />
                                {errors.recipient_name && (
                                    <p className="text-xs text-rose-600 mt-1">{errors.recipient_name}</p>
                                )}
                            </div>

                            {/* Contact Numbers */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        Primary Phone <span className="text-rose-600">*</span>
                                    </label>
                                    <input
                                        type="tel"
                                        required
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="0771234567"
                                        className="w-full text-xs rounded-xl border-stone-300 focus:border-stone-900 focus:ring-stone-900 py-2"
                                    />
                                    {errors.phone && <p className="text-xs text-rose-600 mt-1">{errors.phone}</p>}
                                </div>

                                <div>
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        Alternative Phone <span className="text-stone-400 font-normal">(Optional)</span>
                                    </label>
                                    <input
                                        type="tel"
                                        value={data.alternative_phone}
                                        onChange={(e) => setData('alternative_phone', e.target.value)}
                                        placeholder="0719876543"
                                        className="w-full text-xs rounded-xl border-stone-300 focus:border-stone-900 focus:ring-stone-900 py-2"
                                    />
                                    {errors.alternative_phone && (
                                        <p className="text-xs text-rose-600 mt-1">{errors.alternative_phone}</p>
                                    )}
                                </div>
                            </div>

                            {/* Street Address Lines */}
                            <div>
                                <label className="text-xs font-semibold text-stone-800 block mb-1">
                                    Street Address Line 1 <span className="text-rose-600">*</span>
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.address_line_1}
                                    onChange={(e) => setData('address_line_1', e.target.value)}
                                    placeholder="House No, Road / Street Name"
                                    className="w-full text-xs rounded-xl border-stone-300 focus:border-stone-900 focus:ring-stone-900 py-2"
                                />
                                {errors.address_line_1 && (
                                    <p className="text-xs text-rose-600 mt-1">{errors.address_line_1}</p>
                                )}
                            </div>

                            <div>
                                <label className="text-xs font-semibold text-stone-800 block mb-1">
                                    Address Line 2 <span className="text-stone-400 font-normal">(Apartment, Suite, Landmark)</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.address_line_2}
                                    onChange={(e) => setData('address_line_2', e.target.value)}
                                    placeholder="Apt 4B, Lotus Residencies"
                                    className="w-full text-xs rounded-xl border-stone-300 focus:border-stone-900 focus:ring-stone-900 py-2"
                                />
                                {errors.address_line_2 && (
                                    <p className="text-xs text-rose-600 mt-1">{errors.address_line_2}</p>
                                )}
                            </div>

                            {/* City, District, Postal Code */}
                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        City <span className="text-rose-600">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        required
                                        value={data.city}
                                        onChange={(e) => setData('city', e.target.value)}
                                        placeholder="Colombo 03"
                                        className="w-full text-xs rounded-xl border-stone-300 focus:border-stone-900 focus:ring-stone-900 py-2"
                                    />
                                    {errors.city && <p className="text-xs text-rose-600 mt-1">{errors.city}</p>}
                                </div>

                                <div>
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        District <span className="text-rose-600">*</span>
                                    </label>
                                    <select
                                        required
                                        value={data.district}
                                        onChange={(e) => setData('district', e.target.value)}
                                        className="w-full text-xs rounded-xl border-stone-300 focus:border-stone-900 focus:ring-stone-900 py-2"
                                    >
                                        {districts.map((dist) => (
                                            <option key={dist} value={dist}>
                                                {dist}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.district && (
                                        <p className="text-xs text-rose-600 mt-1">{errors.district}</p>
                                    )}
                                </div>

                                <div>
                                    <label className="text-xs font-semibold text-stone-800 block mb-1">
                                        Postal Code
                                    </label>
                                    <input
                                        type="text"
                                        value={data.postal_code}
                                        onChange={(e) => setData('postal_code', e.target.value)}
                                        placeholder="00300"
                                        className="w-full text-xs rounded-xl border-stone-300 focus:border-stone-900 focus:ring-stone-900 py-2"
                                    />
                                    {errors.postal_code && (
                                        <p className="text-xs text-rose-600 mt-1">{errors.postal_code}</p>
                                    )}
                                </div>
                            </div>

                            {/* Delivery Instructions */}
                            <div>
                                <label className="text-xs font-semibold text-stone-800 block mb-1">
                                    Delivery Instructions <span className="text-stone-400 font-normal">(Optional)</span>
                                </label>
                                <textarea
                                    rows={2}
                                    value={data.delivery_instructions}
                                    onChange={(e) => setData('delivery_instructions', e.target.value)}
                                    placeholder="e.g. Ring bell twice, leave with security if unavailable"
                                    className="w-full text-xs rounded-xl border-stone-300 focus:border-stone-900 focus:ring-stone-900"
                                />
                                {errors.delivery_instructions && (
                                    <p className="text-xs text-rose-600 mt-1">{errors.delivery_instructions}</p>
                                )}
                            </div>

                            {/* Modal Actions */}
                            <div className="pt-4 border-t border-stone-100 flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={closeModal}
                                    className="px-4 py-2 text-xs font-semibold text-stone-600 hover:text-stone-900 rounded-xl hover:bg-stone-100 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="px-5 py-2 text-xs font-semibold uppercase tracking-wider bg-stone-900 hover:bg-stone-800 text-white rounded-xl transition-all shadow-xs disabled:opacity-50"
                                >
                                    {processing ? 'Saving...' : editingAddress ? 'Update Address' : 'Save Address'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Delete Confirmation Modal */}
            {deletingAddress && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-900/60 backdrop-blur-xs">
                    <div className="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-stone-200">
                        <div className="flex items-center gap-3 text-rose-600 mb-3">
                            <div className="p-2 bg-rose-50 rounded-xl">
                                <Icons.AlertCircle className="w-5 h-5 text-rose-600" />
                            </div>
                            <h3 className="font-serif text-lg font-semibold text-stone-900">
                                Delete Delivery Address?
                            </h3>
                        </div>

                        <p className="text-xs text-stone-600 leading-relaxed mb-6">
                            Are you sure you want to remove <strong>{deletingAddress.label}</strong> ({deletingAddress.recipient_name}, {deletingAddress.city}) from your saved address book?
                            This action cannot be undone. Historical orders placed with this address will remain unchanged.
                        </p>

                        <div className="flex items-center justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => setDeletingAddress(null)}
                                className="px-4 py-2 text-xs font-semibold text-stone-600 hover:text-stone-900 rounded-xl hover:bg-stone-100 transition-colors"
                            >
                                Keep Address
                            </button>
                            <button
                                type="button"
                                onClick={confirmDelete}
                                disabled={isDeleting}
                                className="px-4 py-2 text-xs font-semibold uppercase tracking-wider bg-rose-600 hover:bg-rose-700 text-white rounded-xl transition-all shadow-xs disabled:opacity-50"
                            >
                                {isDeleting ? 'Removing...' : 'Delete Address'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </PublicLayout>
    );
}

