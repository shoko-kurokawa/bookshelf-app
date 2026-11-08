<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * ログインユーザーの通知一覧
     */
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(10);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 通知を既読にする
     */
    public function read(
        Request $request,
        string $id
    ): RedirectResponse {
        // 自分の通知からのみ検索する
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        if ($notification->unread()) {
            $notification->markAsRead();
        }

        return redirect()
            ->route('notifications.index')
            ->with('success', '通知を既読にしました。');
    }
}
