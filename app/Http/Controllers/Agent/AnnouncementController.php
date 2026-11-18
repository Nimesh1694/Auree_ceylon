<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentAnnouncement;
use App\Services\AgentAnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    /**
     * Display the agent announcement feed.
     */
    public function index(Request $request, AgentAnnouncementService $service): Response
    {
        $agent = $request->user();

        $userStatus = $agent->status instanceof \App\Enums\UserStatus ? $agent->status->value : (string) $agent->status;
        if (!$agent->isAgent() || !$agent->agentProfile || $userStatus !== 'active') {
            abort(403, 'You do not have access to the agent announcements feed.');
        }

        $feed = $service->getFeedForAgent($agent, 20);
        $unreadCount = $service->getUnreadCountForAgent($agent);
        $urgentAnnouncements = $service->getUnreadUrgentAnnouncements($agent);

        return Inertia::render('Agent/Announcements/Index', [
            'announcements' => $feed,
            'unreadCount' => $unreadCount,
            'urgentAnnouncements' => $urgentAnnouncements,
        ]);
    }

    /**
     * Mark an announcement as read by the authenticated agent.
     */
    public function markRead(Request $request, AgentAnnouncement $announcement, AgentAnnouncementService $service): JsonResponse
    {
        $agent = $request->user();

        if (!$service->isAgentEligible($agent, $announcement)) {
            abort(403, 'You are not eligible to access or acknowledge this announcement.');
        }

        $service->recordRead($announcement, $agent);
        $newUnreadCount = $service->getUnreadCountForAgent($agent);

        return response()->json([
            'message' => 'Announcement acknowledged successfully.',
            'unread_count' => $newUnreadCount,
        ]);
    }
}

