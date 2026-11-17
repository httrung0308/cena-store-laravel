<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OperationsSourceType;
use App\Enums\OperationsWorkItemStatus;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OperationsWorkItemUpdateRequest;
use App\Models\OperationsWorkItem;
use App\Services\OperationsWorkItemService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationsWorkItemController extends Controller
{
    public function __construct(private readonly OperationsWorkItemService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'source_type' => ['nullable', Rule::enum(OperationsSourceType::class)],
            'list' => ['nullable', Rule::in(['active', 'completed'])],
        ]);
        $completed = ($filters['list'] ?? 'active') === 'completed';
        $items = OperationsWorkItem::query()
            ->whereIn('status', $completed
                ? [OperationsWorkItemStatus::Completed->value]
                : [OperationsWorkItemStatus::Pending->value, OperationsWorkItemStatus::InProgress->value])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $query->where(fn ($inner) => $inner->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("content LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("source_reference LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("source_label LIKE ? ESCAPE '!'", [$pattern]));
            })
            ->when($filters['source_type'] ?? null, fn ($query, string $sourceType) => $query->where('source_type', $sourceType))
            ->orderByRaw("FIELD(status, 'PENDING', 'IN_PROGRESS', 'COMPLETED')")
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('admin.operations-work-items.index', ['items' => $items, 'filters' => $filters, 'completed' => $completed]);
    }

    public function show(OperationsWorkItem $workItem): View
    {
        $workItem->load(['creator', 'histories.actor' => fn ($query) => $query->withTrashed()]);
        $workItem->setRelation('histories', $workItem->histories->sortBy([['created_at', 'asc'], ['id', 'asc']])->values());

        return view('admin.operations-work-items.show', ['workItem' => $workItem]);
    }

    public function update(OperationsWorkItemUpdateRequest $request, OperationsWorkItem $workItem): RedirectResponse
    {
        $values = $request->validated();
        try {
            $this->service->update($workItem, $request->user(), (int) $values['version'], OperationsWorkItemStatus::from($values['status']), $values['note'] ?? null);
        } catch (DomainException $exception) {
            $errors = collect($exception->errors)->flatten()->all();

            return back()->withErrors(['work_item' => $errors === [] ? [$exception->getMessage()] : $errors])->withInput();
        }

        return redirect()->route('admin.operations-work-items.show', $workItem)->with('status', 'Đã cập nhật công việc.');
    }
}
