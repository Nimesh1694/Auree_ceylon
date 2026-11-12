<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Courier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourierController extends Controller
{
    public function index(): Response
    {
        $couriers = Courier::orderBy('name')->get();

        return Inertia::render('Admin/Couriers/Index', [
            'couriers' => $couriers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:couriers,code',
            'phone' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ]);

        $courier = Courier::create($validated);

        AuditLog::log('courier_created', $courier, null, [
            'name' => $courier->name,
            'code' => $courier->code,
        ]);

        return back()->with('success', 'Courier added successfully.');
    }

    public function update(Request $request, Courier $courier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:couriers,code,' . $courier->id,
            'phone' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ]);

        $courier->update($validated);

        return back()->with('success', 'Courier updated successfully.');
    }
}

