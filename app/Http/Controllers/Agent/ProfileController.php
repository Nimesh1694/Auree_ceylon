<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the agent's profile and KYC status.
     */
    public function show(Request $request): Response
    {
        $agent = $request->user();
        $agent->loadMissing('agentProfile', 'parent');

        return Inertia::render('Agent/Profile', [
            'agent' => $agent,
        ]);
    }

    /**
     * Update permitted personal and bank account details.
     * Role, status, referral code, and KYC status cannot be self-modified.
     */
    public function update(Request $request): RedirectResponse
    {
        $agent = $request->user();
        $agent->loadMissing('agentProfile');

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'phone' => 'required|string|min:9|max:20|unique:users,phone,' . $agent->id,
            'business_name' => 'nullable|string|max:255',
            'address' => 'required|string|min:5|max:500',
            'bank_name' => 'required|string|max:100',
            'bank_branch' => 'required|string|max:100',
            'bank_account_number' => 'required|string|max:50',
            'bank_account_name' => 'required|string|max:255',
        ]);

        $normalizedPhone = OrderService::normalizePhoneNumber($validated['phone']);

        $oldUserData = $agent->only(['name', 'phone']);
        $agent->update([
            'name' => $validated['name'],
            'phone' => $normalizedPhone,
        ]);

        $oldProfileData = $agent->agentProfile ? $agent->agentProfile->only([
            'business_name', 'address', 'bank_name', 'bank_branch', 'bank_account_number', 'bank_account_name',
        ]) : [];

        $agent->agentProfile()->updateOrCreate(
            ['user_id' => $agent->id],
            [
                'business_name' => $validated['business_name'] ?? null,
                'address' => $validated['address'],
                'bank_name' => $validated['bank_name'],
                'bank_branch' => $validated['bank_branch'],
                'bank_account_number' => $validated['bank_account_number'],
                'bank_account_name' => $validated['bank_account_name'],
            ]
        );

        AuditLog::log('agent_profile_updated', $agent, [
            'user' => $oldUserData,
            'profile' => $oldProfileData,
        ], [
            'user' => $agent->only(['name', 'phone']),
            'profile' => $agent->agentProfile->only([
                'business_name', 'address', 'bank_name', 'bank_branch', 'bank_account_number', 'bank_account_name',
            ]),
        ]);

        return back()->with('success', 'Profile and bank details updated successfully.');
    }
}

