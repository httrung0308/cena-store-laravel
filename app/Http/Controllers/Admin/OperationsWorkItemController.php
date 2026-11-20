<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OperationsSourceType;
use App\Enums\OperationsWorkItemStatus;
use App\Enums\RoleSlug;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OperationsWorkItemUpdateRequest;
use App\Models\OperationsWorkItem;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ShipmentReturnItem;
use App\Services\OperationsQueueService;
use App\Services\OperationsWorkItemService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationsWorkItemController extends Controller
{
    public function __construct(
        private readonly OperationsWorkItemService $service,
        private readonly OperationsQueueService $queues,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_merge([OperationsQueueService::TYPE_ALL], OperationsQueueService::allTypes()))],
            'source_type' => ['nullable', Rule::enum(OperationsSourceType::class)],
            'list' => ['nullable', Rule::in(['active', 'completed'])],
        ]);

        $user = $request->user();
        $isAdmin = $user?->hasRole(RoleSlug::Admin) ?? false;
        $completed = ($filters['list'] ?? 'active') === 'completed';
        $activeType = (string) ($filters['type'] ?? OperationsQueueService::TYPE_ALL);
        $sourceType = isset($filters['source_type']) ? OperationsSourceType::from($filters['source_type']) : null;
        $page = max(1, (int) $request->query('page', 1));

        $counts = $this->queues->counts($isAdmin);
        $queueDefinitions = $this->queues->queueDefinitions($isAdmin, $counts, $activeType);
        $items = $completed
            ? $this->queues->paginateCompleted($isAdmin, $activeType, $filters['search'] ?? null, $sourceType, 20, $page)
            : $this->queues->paginateActive($isAdmin, $activeType, $filters['search'] ?? null, $sourceType, 20, $page);

        return view('admin.operations-work-items.index', [
            'items' => $items,
            'filters' => $filters,
            'completed' => $completed,
            'counts' => $counts,
            'queueDefinitions' => $queueDefinitions,
            'isAdmin' => $isAdmin,
            'activeType' => $activeType,
        ]);
    }

    public function show(Request $request, OperationsWorkItem $workItem): View
    {
        if ($this->isAdminOnly($workItem)) {
            abort_unless($request->user()?->hasRole(RoleSlug::Admin) ?? false, 403);
        }

        $workItem->load(['creator', 'histories.actor' => fn ($query) => $query->withTrashed()]);
        $workItem->setRelation('histories', $workItem->histories->sortBy([['created_at', 'asc'], ['id', 'asc']])->values());

        $workflowLink = $this->resolveWorkflowLink($workItem);

        return view('admin.operations-work-items.show', [
            'workItem' => $workItem,
            'isWorkflowBound' => $this->isWorkflowBound($workItem),
            'workflowLink' => $workflowLink,
        ]);
    }

    public function update(OperationsWorkItemUpdateRequest $request, OperationsWorkItem $workItem): RedirectResponse
    {
        if ($this->isAdminOnly($workItem)) {
            abort_unless($request->user()?->hasRole(RoleSlug::Admin) ?? false, 403);
        }

        $values = $request->validated();
        try {
            $this->service->update($workItem, $request->user(), (int) $values['version'], OperationsWorkItemStatus::from($values['status']), $values['note'] ?? null);
        } catch (DomainException $exception) {
            $errors = collect($exception->errors)->flatten()->all();

            return back()->withErrors(['work_item' => $errors === [] ? [$exception->getMessage()] : $errors])->withInput();
        }

        return redirect()->route('admin.operations-work-items.show', $workItem)->with('status', 'Đã cập nhật công việc.');
    }

    private function isAdminOnly(OperationsWorkItem $workItem): bool
    {
        return $workItem->source_type === OperationsSourceType::Payment
            || str_ends_with($workItem->source_event_key, ':write-off-approval');
    }

    private function isWorkflowBound(OperationsWorkItem $workItem): bool
    {
        $key = $workItem->source_event_key;

        return (str_starts_with($key, 'shipment:') && str_ends_with($key, ':failed-return'))
            || (str_starts_with($key, 'shipment-return:') && str_ends_with($key, ':write-off-approval'))
            || (str_starts_with($key, 'refund:') && str_ends_with($key, ':approval'));
    }

    /** @return array{url: string, label: string}|null */
    private function resolveWorkflowLink(OperationsWorkItem $workItem): ?array
    {
        $key = $workItem->source_event_key;

        if (preg_match('/^shipment:(\d+):failed-return$/', $key, $matches) === 1) {
            return [
                'url' => route('admin.shipment-returns.show', (int) $matches[1]),
                'label' => 'Nhận hàng hoàn',
            ];
        }

        if (preg_match('/^shipment-return:(\d+):write-off-approval$/', $key, $matches) === 1) {
            $returnItem = ShipmentReturnItem::query()->find((int) $matches[1]);
            if ($returnItem !== null) {
                return [
                    'url' => route('admin.shipment-returns.show', $returnItem->shipment_id),
                    'label' => 'Duyệt ghi hỏng',
                ];
            }
        }

        if (preg_match('/^refund:(\d+):approval$/', $key, $matches) === 1) {
            $refund = Refund::query()->find((int) $matches[1]);
            if ($refund !== null) {
                return [
                    'url' => route('admin.orders.show', $refund->order_id),
                    'label' => 'Duyệt hoàn tiền',
                ];
            }
        }

        $orderId = Order::query()->where('order_code', $workItem->source_reference)->value('id');
        if ($orderId !== null) {
            return [
                'url' => route('admin.orders.show', (int) $orderId),
                'label' => 'Xem đơn hàng',
            ];
        }

        return null;
    }
}
