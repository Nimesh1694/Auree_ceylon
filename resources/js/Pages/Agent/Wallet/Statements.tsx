import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AgentLayout from '@/Layouts/AgentLayout';
import { PageProps, WalletTransaction } from '@/types';
import { CreditCard, CheckCircle2 } from '@/Components/Icons';

interface StatementData {
    period: {
        year: number;
        month: number;
        month_name: string;
        start_date: string;
        end_date: string;
    };
    agent: {
        id: number;
        name: string;
        email: string;
        referral_code: string;
        role: string;
        nic?: string | null;
        business_name?: string | null;
        bank_name?: string | null;
        bank_account_number?: string | null;
        bank_account_name?: string | null;
    };
    opening_balance: number;
    opening_gross_balance: number;
    opening_reserved_balance: number;
    opening_available_balance: number;
    total_credited: number;
    total_reversed: number;
    total_paid_out: number;
    new_reservations: number;
    released_reservations: number;
    closing_balance: number;
    closing_gross_balance: number;
    closing_reserved_balance: number;
    closing_available_balance: number;
    current_wallet_balance?: number;
    current_available_balance?: number;
    transactions: WalletTransaction[];
}

interface StatementsProps extends PageProps {
    statement: StatementData;
    year: number;
    month: number;
}

export default function Statements({ statement, year, month }: StatementsProps) {
    const [selectedYear, setSelectedYear] = useState(year);
    const [selectedMonth, setSelectedMonth] = useState(month);

    const handlePeriodChange = (newYear: number, newMonth: number) => {
        setSelectedYear(newYear);
        setSelectedMonth(newMonth);
        router.get('/agent/wallet/statements', { year: newYear, month: newMonth }, { preserveState: true });
    };

    const handlePrint = () => {
        window.print();
    };

    const months = [
        { num: 1, name: 'January' },
        { num: 2, name: 'February' },
        { num: 3, name: 'March' },
        { num: 4, name: 'April' },
        { num: 5, name: 'May' },
        { num: 6, name: 'June' },
        { num: 7, name: 'July' },
        { num: 8, name: 'August' },
        { num: 9, name: 'September' },
        { num: 10, name: 'October' },
        { num: 11, name: 'November' },
        { num: 12, name: 'December' },
    ];

    const currentYear = new Date().getFullYear();
    const years = [currentYear - 1, currentYear, currentYear + 1];

    return (
        <AgentLayout>
            <Head title={`Monthly Statement - ${statement.period.month_name}`} />

            <div className="space-y-6">
                {/* Screen Header Controls (Hidden on Print) */}
                <div className="print:hidden flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-stone-200 shadow-xs">
                    <div>
                        <h1 className="text-xl font-serif font-bold text-stone-900">Official Monthly Statement</h1>
                        <p className="text-xs text-stone-500 mt-0.5">
                            Select month and year to inspect, download, or print your certified financial ledger statement.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <select
                            value={selectedYear}
                            onChange={(e) => handlePeriodChange(Number(e.target.value), selectedMonth)}
                            className="px-3 py-1.5 border border-stone-300 rounded-lg text-xs font-medium"
                        >
                            {years.map((y) => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>

                        <select
                            value={selectedMonth}
                            onChange={(e) => handlePeriodChange(selectedYear, Number(e.target.value))}
                            className="px-3 py-1.5 border border-stone-300 rounded-lg text-xs font-medium"
                        >
                            {months.map((m) => (
                                <option key={m.num} value={m.num}>{m.name}</option>
                            ))}
                        </select>

                        <button
                            type="button"
                            onClick={handlePrint}
                            className="px-4 py-1.5 bg-stone-900 hover:bg-stone-800 text-white rounded-lg text-xs font-semibold cursor-pointer shadow-xs flex items-center gap-1.5"
                        >
                            <span>🖨️</span> Print / Save PDF
                        </button>
                    </div>
                </div>

                {/* The Formal Printable Statement Document */}
                <div className="bg-white rounded-2xl border border-stone-200 p-8 sm:p-10 shadow-sm print:border-none print:shadow-none print:p-0">
                    {/* Brand & Document Header */}
                    <div className="flex flex-col sm:flex-row justify-between items-start pb-6 border-b-2 border-stone-900 gap-6">
                        <div>
                            <span className="font-serif text-2xl font-bold tracking-wider text-rose-950 uppercase">
                                Aura Luxe
                            </span>
                            <span className="block text-xs font-semibold uppercase tracking-widest text-stone-500 mt-0.5">
                                Botanical Cosmetics Ltd. — Colombo, Sri Lanka
                            </span>
                            <p className="text-[11px] text-stone-400 mt-1">
                                Agent Network Financial Services & Payout Clearing Division
                            </p>
                        </div>

                        <div className="sm:text-right">
                            <span className="inline-block px-3 py-1 rounded bg-stone-100 text-stone-800 text-xs font-mono font-bold uppercase tracking-wider">
                                Financial Statement
                            </span>
                            <p className="text-base font-bold text-stone-900 mt-2 font-mono">
                                {statement.period.month_name}
                            </p>
                            <p className="text-[11px] text-stone-500 font-mono">
                                Period: {statement.period.start_date} to {statement.period.end_date}
                            </p>
                        </div>
                    </div>

                    {/* Agent & Clearing Metadata */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 py-6 border-b border-stone-200 text-xs">
                        <div className="space-y-1">
                            <span className="text-[11px] uppercase font-bold text-stone-400">Agent Account Details</span>
                            <p className="text-sm font-bold text-stone-900">{statement.agent.name}</p>
                            {statement.agent.business_name && (
                                <p className="text-stone-700">Business: {statement.agent.business_name}</p>
                            )}
                            <p className="text-stone-600 font-mono">Referral Code: {statement.agent.referral_code}</p>
                            <p className="text-stone-600">Email: {statement.agent.email}</p>
                            {statement.agent.nic && <p className="text-stone-600 font-mono">NIC: {statement.agent.nic}</p>}
                        </div>

                        <div className="space-y-1 md:text-right">
                            <span className="text-[11px] uppercase font-bold text-stone-400">Remittance Destination</span>
                            <p className="text-sm font-bold text-stone-900">
                                {statement.agent.bank_name || 'Bank Not Configured'}
                            </p>
                            {statement.agent.bank_account_number && (
                                <p className="text-stone-700 font-mono">Account: {statement.agent.bank_account_number}</p>
                            )}
                            {statement.agent.bank_account_name && (
                                <p className="text-stone-600">Account Name: {statement.agent.bank_account_name}</p>
                            )}
                            <p className="text-[11px] text-stone-400">Settlement Currency: LKR (Sri Lankan Rupee)</p>
                        </div>
                    </div>

                    {/* Financial Reconciliation Summary Table (Gross Wallet Balance) */}
                    <div className="my-6 grid grid-cols-2 sm:grid-cols-5 gap-3 text-center">
                        <div className="p-3 bg-stone-50 rounded-xl border border-stone-200">
                            <span className="text-[10px] font-semibold text-stone-500 uppercase">Opening Gross Balance</span>
                            <p className="text-base font-bold font-mono text-stone-900 mt-1">
                                LKR {(statement.opening_gross_balance ?? statement.opening_balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>

                        <div className="p-3 bg-emerald-50 rounded-xl border border-emerald-200">
                            <span className="text-[10px] font-semibold text-emerald-800 uppercase">+ Period Credits</span>
                            <p className="text-base font-bold font-mono text-emerald-950 mt-1">
                                LKR {statement.total_credited.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>

                        <div className="p-3 bg-rose-50 rounded-xl border border-rose-200">
                            <span className="text-[10px] font-semibold text-rose-800 uppercase">- Period Reversals</span>
                            <p className="text-base font-bold font-mono text-rose-950 mt-1">
                                LKR {statement.total_reversed.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>

                        <div className="p-3 bg-purple-50 rounded-xl border border-purple-200">
                            <span className="text-[10px] font-semibold text-purple-800 uppercase">- Period Paid Out</span>
                            <p className="text-base font-bold font-mono text-purple-950 mt-1">
                                LKR {statement.total_paid_out.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>

                        <div className="p-3 bg-stone-900 text-white rounded-xl col-span-2 sm:col-span-1">
                            <span className="text-[10px] font-semibold text-stone-300 uppercase">Closing Gross Balance</span>
                            <p className="text-base font-bold font-mono text-white mt-1">
                                LKR {(statement.closing_gross_balance ?? statement.closing_balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                            </p>
                        </div>
                    </div>

                    {/* Escrow Holds & Withdrawable Position Breakdown (FIN-06-01) */}
                    <div className="my-4 p-4 bg-stone-50 rounded-xl border border-stone-200 text-xs">
                        <span className="text-[11px] font-bold text-stone-500 uppercase tracking-wider block mb-2">
                            Payout Escrow Holds & Withdrawable Position (Reconciliation Note)
                        </span>
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-stone-700">
                            <div>
                                <span className="text-stone-500 block text-[11px]">Period New Reservations:</span>
                                <strong className="font-mono text-stone-900">
                                    LKR {(statement.new_reservations ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </strong>
                            </div>
                            <div>
                                <span className="text-stone-500 block text-[11px]">Period Released Reservations:</span>
                                <strong className="font-mono text-stone-900">
                                    LKR {(statement.released_reservations ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </strong>
                            </div>
                            <div>
                                <span className="text-stone-500 block text-[11px]">Period-End Reserved Escrow:</span>
                                <strong className="font-mono text-amber-800">
                                    LKR {(statement.closing_reserved_balance ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </strong>
                            </div>
                            <div>
                                <span className="text-stone-500 block text-[11px]">Period-End Spendable Available:</span>
                                <strong className="font-mono text-emerald-800">
                                    LKR {(statement.closing_available_balance ?? statement.closing_balance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                </strong>
                            </div>
                        </div>
                        <p className="text-[11px] text-stone-400 mt-2 italic">
                            * Note: Payout reservations represent funds temporarily held in escrow pending bank clearance. Reservations do not reduce gross balance until payment completion.
                        </p>
                    </div>

                    {/* Period Transaction Journal */}
                    <div className="mt-8">
                        <h3 className="text-xs font-bold uppercase tracking-wider text-stone-700 mb-3">
                            Period Transactions Journal ({statement.transactions.length})
                        </h3>

                        {statement.transactions.length === 0 ? (
                            <div className="py-8 text-center text-stone-400 text-xs italic bg-stone-50 rounded-xl border border-stone-200">
                                No wallet transactions recorded during {statement.period.month_name}.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-xs divide-y divide-stone-200">
                                    <thead className="bg-stone-50 text-stone-500 uppercase font-semibold">
                                        <tr>
                                            <th className="py-2.5 px-3 text-left">Date</th>
                                            <th className="py-2.5 px-3 text-left">Transaction #</th>
                                            <th className="py-2.5 px-3 text-left">Description</th>
                                            <th className="py-2.5 px-3 text-right">Amount (LKR)</th>
                                            <th className="py-2.5 px-3 text-right">Balance After</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-stone-100 font-sans">
                                        {statement.transactions.map((tx) => {
                                            const amount = Number(tx.amount);
                                            const isPositive = amount > 0;
                                            return (
                                                <tr key={tx.id}>
                                                    <td className="py-2 px-3 text-stone-600 whitespace-nowrap">
                                                        {new Date(tx.created_at).toLocaleDateString()}
                                                    </td>
                                                    <td className="py-2 px-3 font-mono text-stone-900 whitespace-nowrap">
                                                        {tx.transaction_number}
                                                    </td>
                                                    <td className="py-2 px-3 text-stone-800">
                                                        {tx.description}
                                                    </td>
                                                    <td className={`py-2 px-3 text-right font-mono font-bold whitespace-nowrap ${
                                                        isPositive ? 'text-emerald-700' : 'text-stone-700'
                                                    }`}>
                                                        {isPositive ? '+' : ''}{amount.toFixed(2)}
                                                    </td>
                                                    <td className="py-2 px-3 text-right font-mono font-semibold text-stone-900 whitespace-nowrap">
                                                        {Number(tx.balance_after).toFixed(2)}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* Official Footer Verification */}
                    <div className="mt-12 pt-6 border-t border-stone-200 text-[11px] text-stone-500 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div>
                            This is an automated computer-generated statement issued by Aura Luxe Botanical Cosmetics.
                            <br />All transactions are cryptographically journaled and reconciled with the immutable ledger.
                        </div>
                        <div className="text-right font-mono text-[10px]">
                            Generated: {new Date().toISOString()}
                        </div>
                    </div>
                </div>
            </div>
        </AgentLayout>
    );
}

