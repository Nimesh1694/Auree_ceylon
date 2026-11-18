<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduleAnnouncementRequest;
use App\Http\Requests\Admin\StoreAgentAnnouncementRequest;
use App\Http\Requests\Admin\UpdateAgentAnnouncementRequest;
use App\Models\AgentAnnouncement;
use App\Services\AgentAnnouncementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentAnnouncementController extends Controller
{
    /**
     * Display the announcement management console.
     */
    public function index(Request $request, AgentAnnouncementService $service): Response
    {
        $tab = $request->query('tab', AgentAnnouncement::STATUS_PUBLISHED);
        if (!in_array($tab, [AgentAnnouncement::STATUS_PUBLISHED, AgentAnnouncement::STATUS_SCHEDULED, AgentAnnouncement::STATUS_DRAFT, AgentAnnouncement::STATUS_ARCHIVED], true)) {
            $tab = AgentAnnouncement::STATUS_PUBLISHED;
        }

        $query = AgentAnnouncement::query()
            ->where('status', $tab)
            ->with(['creator:id,name,email', 'scheduler:id,name,email', 'publisher:id,name,email', 'archiver:id,name,email']);

        if ($request->filled('audience') && $request->query('audience') !== 'all_audiences') {
            $query->where('target_audience', $request->query('audience'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title_en', 'like', $search)
                    ->orWhere('title_si', 'like', $search)
                    ->orWhere('content_en', 'like', $search);
            });
        }

        $announcements = $query->orderBy('is_pinned', 'desc')
            ->orderBy('pin_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Attach live analytics metrics to published and archived announcements
        if (in_array($tab, [AgentAnnouncement::STATUS_PUBLISHED, AgentAnnouncement::STATUS_ARCHIVED], true)) {
            $announcements->getCollection()->transform(function (AgentAnnouncement $announcement) use ($service) {
                $analytics = $service->getReadAnalytics($announcement);
                $announcement->analytics = $analytics;
                return $announcement;
            });
        }

        $counts = [
            'published' => AgentAnnouncement::where('status', AgentAnnouncement::STATUS_PUBLISHED)->count(),
            'scheduled' => AgentAnnouncement::where('status', AgentAnnouncement::STATUS_SCHEDULED)->count(),
            'draft' => AgentAnnouncement::where('status', AgentAnnouncement::STATUS_DRAFT)->count(),
            'archived' => AgentAnnouncement::where('status', AgentAnnouncement::STATUS_ARCHIVED)->count(),
        ];

        return Inertia::render('Admin/Agents/Announcements/Index', [
            'announcements' => $announcements,
            'currentTab' => $tab,
            'counts' => $counts,
            'filters' => [
                'tab' => $tab,
                'audience' => $request->query('audience', 'all_audiences'),
                'search' => $request->query('search', ''),
            ],
        ]);
    }

    /**
     * Show the create announcement form.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Agents/Announcements/Create');
    }

    /**
     * Store a new announcement.
     */
    public function store(StoreAgentAnnouncementRequest $request, AgentAnnouncementService $service): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $announcement = $service->createDraft($validated, $user);

        if (($validated['publish_action'] ?? null) === 'publish_now') {
            if (!$user->hasPermissionTo('agents.announcements.publish')) {
                abort(403, 'You do not have permission to publish announcements.');
            }
            $service->publishNow($announcement, $user);
            return redirect()->route('admin.agents.announcements.index', ['tab' => 'published'])
                ->with('success', 'Announcement published successfully.');
        }

        if (($validated['publish_action'] ?? null) === 'schedule' && !empty($validated['scheduled_at'])) {
            if (!$user->hasPermissionTo('agents.announcements.publish')) {
                abort(403, 'You do not have permission to schedule announcements.');
            }
            $service->schedule($announcement, Carbon::parse($validated['scheduled_at']), $user);
            return redirect()->route('admin.agents.announcements.index', ['tab' => 'scheduled'])
                ->with('success', 'Announcement scheduled successfully.');
        }

        return redirect()->route('admin.agents.announcements.index', ['tab' => 'draft'])
            ->with('success', 'Announcement draft created successfully.');
    }

    /**
     * Show the edit announcement form.
     */
    public function edit(AgentAnnouncement $announcement): Response
    {
        return Inertia::render('Admin/Agents/Announcements/Edit', [
            'announcement' => $announcement->load(['creator:id,name', 'scheduler:id,name', 'publisher:id,name']),
        ]);
    }

    /**
     * Update an announcement.
     */
    public function update(UpdateAgentAnnouncementRequest $request, AgentAnnouncement $announcement, AgentAnnouncementService $service): RedirectResponse
    {
        $service->update($announcement, $request->validated(), $request->user());

        return redirect()->back()->with('success', 'Announcement updated successfully.');
    }

    /**
     * Publish an announcement immediately.
     */
    public function publish(AgentAnnouncement $announcement, AgentAnnouncementService $service, Request $request): RedirectResponse
    {
        $service->publishNow($announcement, $request->user());

        return redirect()->back()->with('success', 'Announcement published successfully.');
    }

    /**
     * Schedule an announcement for future publication.
     */
    public function schedule(ScheduleAnnouncementRequest $request, AgentAnnouncement $announcement, AgentAnnouncementService $service): RedirectResponse
    {
        $service->schedule($announcement, Carbon::parse($request->input('scheduled_at')), $request->user());

        return redirect()->back()->with('success', 'Announcement scheduled successfully.');
    }

    /**
     * Cancel a scheduled announcement and return it to draft.
     */
    public function cancelSchedule(AgentAnnouncement $announcement, AgentAnnouncementService $service, Request $request): RedirectResponse
    {
        $service->cancelSchedule($announcement, $request->user());

        return redirect()->back()->with('success', 'Schedule cancelled. Announcement returned to draft.');
    }

    /**
     * Manually archive an announcement.
     */
    public function archive(AgentAnnouncement $announcement, AgentAnnouncementService $service, Request $request): RedirectResponse
    {
        $service->archive($announcement, $request->user());

        return redirect()->back()->with('success', 'Announcement archived successfully.');
    }

    /**
     * Delete a draft announcement.
     */
    public function destroy(AgentAnnouncement $announcement, AgentAnnouncementService $service): RedirectResponse
    {
        $service->deleteDraft($announcement);

        return redirect()->route('admin.agents.announcements.index', ['tab' => 'draft'])
            ->with('success', 'Draft announcement deleted successfully.');
    }

    /**
     * Retrieve live read analytics and recipient list for an announcement.
     */
    public function reads(AgentAnnouncement $announcement, AgentAnnouncementService $service): JsonResponse
    {
        $analytics = $service->getReadAnalytics($announcement);

        $reads = $announcement->reads()
            ->with('agent:id,name,email,role')
            ->orderBy('read_at', 'desc')
            ->paginate(20);

        return response()->json([
            'analytics' => $analytics,
            'reads' => $reads,
        ]);
    }
}
