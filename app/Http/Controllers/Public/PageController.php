<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\CustomerInquiry;
use App\Services\CustomerInquiryEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function about(): Response
    {
        return Inertia::render('Pages/About');
    }

    public function contact(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Pages/Contact', [
            'prefill' => $user ? [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
            ] : null,
        ]);
    }

    public function submitContact(Request $request): RedirectResponse
    {
        // Silent bot honeypot protection: if hidden field is filled, silently return without persisting
        if ($request->filled('website') || $request->filled('fax_number')) {
            return back()->with('success', 'Thank you for reaching out! Our beauty care team will get back to you shortly.');
        }

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|string|email|max:255',
            'phone' => 'nullable|string|max:30',
            'subject' => 'required|string|min:3|max:200',
            'message' => 'required|string|min:10|max:3000',
        ]);

        $user = $request->user();
        $customerId = $user?->id;
        $inquiryNumber = CustomerInquiry::generateInquiryNumber();

        $inquiry = DB::transaction(function () use ($validated, $customerId, $inquiryNumber, $request) {
            return CustomerInquiry::create([
                'inquiry_number' => $inquiryNumber,
                'customer_id' => $customerId,
                'name' => trim($validated['name']),
                'email' => strtolower(trim($validated['email'])),
                'phone' => !empty($validated['phone']) ? trim($validated['phone']) : null,
                'subject' => trim($validated['subject']),
                'message' => trim($validated['message']),
                'status' => CustomerInquiry::STATUS_NEW,
                'priority' => CustomerInquiry::PRIORITY_NORMAL,
                'notification_status' => CustomerInquiry::NOTIFICATION_PENDING,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        });

        // Dispatch staff email notification via dedicated service
        app(CustomerInquiryEmailService::class)->dispatchStaffNotification($inquiry);

        return back()
            ->with('success', "Thank you, {$inquiry->name}! Your inquiry has been received (Ref: {$inquiry->inquiry_number}). Our beauty care team will get back to you shortly.")
            ->with('inquiry_number', $inquiry->inquiry_number);
    }

    public function returnPolicy(): Response
    {
        return Inertia::render('Pages/ReturnPolicy');
    }

    public function privacyPolicy(): Response
    {
        return Inertia::render('Pages/PrivacyPolicy');
    }

    public function terms(): Response
    {
        return Inertia::render('Pages/Terms');
    }
}
