import React from 'react';
import CustomerDashboard, { CustomerInfo, CustomerStats, RecentOrder } from './Customer/Dashboard';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

interface Props extends PageProps {
    customer?: CustomerInfo;
    statistics?: CustomerStats;
    recentOrders?: RecentOrder[];
}

export default function Dashboard(props: Props) {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    const fallbackCustomer: CustomerInfo = props.customer || {
        id: user?.id ?? 0,
        name: user?.name ?? 'Valued Customer',
        email: user?.email ?? '',
        phone: user?.phone ?? null,
        joined_date: user?.created_at ? new Date(user.created_at).toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) : 'Recently',
    };

    const fallbackStatistics: CustomerStats = props.statistics || {
        total_orders: 0,
        pending_orders: 0,
        completed_orders: 0,
        cancelled_orders: 0,
        total_spend: 0,
        total_spend_formatted: 'LKR 0.00',
    };

    const fallbackRecentOrders: RecentOrder[] = props.recentOrders || [];

    return (
        <CustomerDashboard
            {...props}
            customer={fallbackCustomer}
            statistics={fallbackStatistics}
            recentOrders={fallbackRecentOrders}
        />
    );
}
