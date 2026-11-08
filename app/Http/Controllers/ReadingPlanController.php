<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * 読書計画一覧
     */
    public function index(Request $request): View
    {
        $currentStatus = $request->query('status', '');

        $validStatuses = array_column(ReadingPlanStatus::cases(), 'value');

        if (! in_array($currentStatus, $validStatuses, true)) {
            $currentStatus = '';
        }

        $readingPlans = $request->user()
            ->readingPlans()
            ->with('book')
            ->when($currentStatus !== '', function ($query) use ($currentStatus) {
                $query->where('status', $currentStatus);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('reading-plans.index', compact(
            'readingPlans',
            'currentStatus'
        ));
    }

    /**
     * 読書計画の新規作成画面
     */
    public function create(): View
    {
        // 他ユーザーが登録した書籍も選択可能
        $books = Book::query()
            ->orderBy('title')
            ->get(['id', 'title', 'author']);

        return view('reading-plans.create', compact('books'));
    }

    /**
     * 読書計画を登録
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $request->user()->readingPlans()->create([
                'book_id' => $validated['book_id'],
                'target_date' => $validated['target_date'],
                'status' => ReadingPlanStatus::InProgress,
            ]);
        } catch (QueryException $e) {
            // 同時リクエストによる読書中計画の重複を想定
            if ($this->isDuplicateKeyError($e)) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'book_id' => 'この書籍には、すでに読書中の計画があります。',
                    ]);
            }

            throw $e;
        }

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を登録しました。');
    }

    /**
     * 読書計画の編集画面
     */
    public function edit(Request $request, ReadingPlan $readingPlan): View
    {
        $this->ensureOwner($request, $readingPlan);

        abort_if(
            $readingPlan->status === ReadingPlanStatus::Completed,
            403,
            '読了済みの計画は編集できません。'
        );

        $readingPlan->load('book');

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * 読書計画を更新
     */
    public function update(
        UpdateReadingPlanRequest $request,
        ReadingPlan $readingPlan
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($readingPlan, $validated) {
                // 更新時点でも読了済みでないことを確認
                $readingPlan = ReadingPlan::query()
                    ->lockForUpdate()
                    ->findOrFail($readingPlan->id);

                abort_if(
                    $readingPlan->status === ReadingPlanStatus::Completed,
                    403,
                    '読了済みの計画は編集できません。'
                );

                $newTargetDate = $validated['target_date'];
                $currentTargetDate = $readingPlan->target_date->format('Y-m-d');

                // 期限切れ計画は、目標日を変更した場合のみ読書中に戻す
                if (
                    $readingPlan->status === ReadingPlanStatus::Expired
                    && $newTargetDate !== $currentTargetDate
                ) {
                    $readingPlan->status = ReadingPlanStatus::InProgress;
                }

                $readingPlan->target_date = $newTargetDate;
                $readingPlan->save();
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateKeyError($e)) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'target_date' => 'この書籍には、すでに読書中の計画があります。',
                    ]);
            }

            throw $e;
        }

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * 読了操作
     */
    public function complete(
        Request $request,
        ReadingPlan $readingPlan
    ): RedirectResponse {
        $this->ensureOwner($request, $readingPlan);

        DB::transaction(function () use ($readingPlan) {
            $readingPlan = ReadingPlan::query()
                ->lockForUpdate()
                ->findOrFail($readingPlan->id);

            if ($readingPlan->status !== ReadingPlanStatus::Completed) {
                $readingPlan->update([
                    'status' => ReadingPlanStatus::Completed,
                    'completed_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を読了にしました。');
    }

    /**
     * 読書計画を削除
     */
    public function destroy(
        Request $request,
        ReadingPlan $readingPlan
    ): RedirectResponse {
        $this->ensureOwner($request, $readingPlan);

        $readingPlan->delete();

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }

    /**
     * 本人の計画か確認
     */
    private function ensureOwner(
        Request $request,
        ReadingPlan $readingPlan
    ): void {
        abort_unless(
            $readingPlan->user_id === $request->user()->id,
            403,
            '他のユーザーの読書計画は操作できません。'
        );
    }

    /**
     * MySQLの重複キーエラーを判定
     */
    private function isDuplicateKeyError(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
