<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate(20)
            ->through(fn ($n) => [
                'id' => $n->id,
                'type' => $n->data['type'] ?? 'general',
                'message' => $n->data['message'] ?? '',
                'url' => $n->data['url'] ?? null,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request): RedirectResponse
    {
        $notification = $this->findForUser($request, (string) $request->route('id'));
        $notification?->markAsRead();

        return redirect()->back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $notification = $this->findForUser($request, (string) $request->route('id'));
        $notification?->delete();

        return redirect()->back();
    }

    private function findForUser(Request $request, string $id): ?DatabaseNotification
    {
        return DatabaseNotification::query()
            ->where('id', $id)
            ->where('notifiable_type', $request->user()::class)
            ->where('notifiable_id', $request->user()->id)
            ->first();
    }
}
