<?php

namespace App\Services;

use App\Data\InventoryChange;
use App\Enums\ErrorCode;
use App\Enums\InventoryAction;
use App\Enums\InventoryReferenceType;
use App\Enums\RoleSlug;
use App\Enums\StockImportStatus;
use App\Exceptions\DomainException;
use App\Models\ProductVariant;
use App\Models\StockImport;
use App\Models\StockImportItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\CanonicalPayload;
use App\Support\Cost;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StockImportService
{
    public function __construct(private readonly InventoryService $inventory, private readonly BusinessClock $clock) {}

    /** @return array<string,mixed> */
    public function rules(bool $creating = true): array
    {
        $rules = ['command_key' => $creating ? ['required', 'uuid'] : ['prohibited'], 'supplier_id' => ['required', 'integer', 'min:1'], 'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*.variant_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'between:1,2147483647'], 'items.*.unit_cost_amount' => ['required', 'integer', 'min:0']];
        foreach (['import_code', 'status', 'created_by', 'posted_by', 'posted_at', 'total_quantity', 'total_cost_amount'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        foreach (['subtotal_amount', 'sku_snapshot', 'stock_import_id', 'id'] as $field) {
            $rules['items.*.'.$field] = ['prohibited'];
        }

        return $rules;
    }

    /** @param array<string,mixed> $input */
    public function saveDraft(User $actor, ?StockImport $import, array $input): StockImport
    {
        $data = $this->normalize(Validator::make($input, $this->rules($import === null))->validate());
        try {
            return DB::transaction(function () use ($actor, $import, $data): StockImport {
                $currentActor = ProductManagementAccess::lock($actor);
                if ($import === null) {
                    $existing = StockImport::where('command_key', $data['command_key'])->lockForUpdate()->first();
                    if ($existing) {
                        return $this->replay($existing, $currentActor, $data);
                    }
                    $current = new StockImport;
                    $current->import_code = 'NK-'.Str::ulid();
                    $current->command_key = $data['command_key'];
                    $current->created_by = $currentActor->id;
                } else {
                    $current = StockImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
                    $this->draftOnly($current);
                    $current->items()->orderBy('variant_id')->lockForUpdate()->get();
                }
                $supplier = Supplier::whereKey($data['supplier_id'])->lockForUpdate()->first();
                if (! $supplier || ! $supplier->is_active) {
                    $this->invalid('supplier_id', 'Nhà cung cấp đã ngừng hoạt động hoặc không tồn tại.');
                }
                $variants = $this->lockVariants(array_column($data['items'], 'variant_id'));
                $this->checkFreeReceipt($currentActor, $data['note'], $data['items']);
                $current->supplier_id = $supplier->id;
                $current->supplier_code_snapshot = $supplier->code;
                $current->supplier_name_snapshot = $supplier->name;
                $current->note = $data['note'];
                $current->total_quantity = $data['total_quantity'];
                $current->total_cost_amount = $data['total_cost_amount'];
                $current->save();
                $current->items()->delete();
                foreach ($data['items'] as $line) {
                    $variant = $variants->get($line['variant_id']);
                    $item = new StockImportItem;
                    $item->forceFill($line + ['stock_import_id' => $current->id, 'sku_snapshot' => $variant->sku])->save();
                }

                return $current->refresh();
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if ($import !== null) {
                throw $exception;
            }

            return DB::transaction(function () use ($actor, $data, $exception): StockImport {
                $currentActor = ProductManagementAccess::lock($actor);
                $existing = StockImport::where('command_key', $data['command_key'])->lockForUpdate()->first();
                if (! $existing) {
                    throw $exception;
                }

                return $this->replay($existing, $currentActor, $data);
            }, 3);
        }
    }

    public function post(User $actor, StockImport $import): StockImport
    {
        return DB::transaction(function () use ($actor, $import): StockImport {
            $currentActor = ProductManagementAccess::lock($actor);
            $current = StockImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            if ($current->status === StockImportStatus::Posted) {
                return $current;
            }
            $this->draftOnly($current);
            $supplier = Supplier::whereKey($current->supplier_id)->lockForUpdate()->first();
            if (! $supplier || ! $supplier->is_active) {
                $this->invalid('supplier_id', 'Nhà cung cấp đã ngừng hoạt động; hãy sửa phiếu nháp.');
            }
            $items = $current->items()->orderBy('variant_id')->lockForUpdate()->get();
            $data = $this->normalize(Validator::make(['supplier_id' => $current->supplier_id, 'note' => $current->note, 'items' => $items->map(fn ($i) => ['variant_id' => $i->variant_id, 'quantity' => $i->quantity, 'unit_cost_amount' => $i->unit_cost_amount])->all()], $this->rules(false))->validate());
            $variants = $this->lockVariants(array_column($data['items'], 'variant_id'));
            $this->checkFreeReceipt($currentActor, $data['note'], $data['items']);
            foreach ($items as $item) {
                $variant = $variants->get($item->variant_id);
                $item->sku_snapshot = $variant->sku;
                $item->subtotal_amount = Money::multiply($item->quantity, $item->unit_cost_amount);
                $item->save();
                $this->inventory->receive(new InventoryChange('import:'.$current->id.':post:'.$variant->id, $variant->id, InventoryAction::ImportPosted, InventoryReferenceType::StockImport, $current->id, $item->quantity, $item->unit_cost_amount, $currentActor->id, 'Ghi sổ phiếu '.$current->import_code));
            }
            $current->supplier_code_snapshot = $supplier->code;
            $current->supplier_name_snapshot = $supplier->name;
            $current->total_quantity = $data['total_quantity'];
            $current->total_cost_amount = $data['total_cost_amount'];
            $current->status = StockImportStatus::Posted;
            $current->posted_by = $currentActor->id;
            $current->posted_at = $this->clock->now();
            $current->save();

            return $current->refresh();
        }, 3);
    }

    public function cancel(User $actor, StockImport $import, string $reason): StockImport
    {
        $data = Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'max:500']])->validate();

        return DB::transaction(function () use ($actor, $import, $data): StockImport {
            $currentActor = ProductManagementAccess::lock($actor);
            $current = StockImport::whereKey($import->id)->lockForUpdate()->firstOrFail();
            $this->draftOnly($current);
            $current->status = StockImportStatus::Cancelled;
            $current->note = trim(($current->note ?? '')."\n[Hủy ".$this->clock->now()->format('Y-m-d H:i:s.u').' UTC; user '.$currentActor->id.'] '.$data['reason']);
            $current->save();

            return $current->refresh();
        }, 3);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function normalize(array $data): array
    {
        $lines = [];
        $seen = [];
        $totalQuantity = 0;
        $totalCost = 0;
        foreach ($data['items'] as $line) {
            $id = Money::integer($line['variant_id']);
            if (isset($seen[$id])) {
                $this->invalid('items', 'Không nhập trùng SKU trong cùng phiếu.');
            }
            $seen[$id] = true;
            $quantity = Money::integer($line['quantity']);
            $cost = Money::integer($line['unit_cost_amount']);
            Cost::normalize($cost);
            $subtotal = Money::multiply($quantity, $cost);
            $totalQuantity = Money::add($totalQuantity, $quantity);
            $totalCost = Money::add($totalCost, $subtotal);
            if ($totalQuantity > 4294967295) {
                $this->invalid('items', 'Tổng số lượng vượt giới hạn UINT32.');
            }
            $lines[] = ['variant_id' => $id, 'quantity' => $quantity, 'unit_cost_amount' => $cost, 'subtotal_amount' => $subtotal];
        }
        usort($lines, fn ($a, $b) => $a['variant_id'] <=> $b['variant_id']);

        return ['supplier_id' => Money::integer($data['supplier_id']), 'note' => isset($data['note']) && trim($data['note']) !== '' ? trim($data['note']) : null, 'items' => $lines, 'total_quantity' => $totalQuantity, 'total_cost_amount' => $totalCost] + (isset($data['command_key']) ? ['command_key' => $data['command_key']] : []);
    }

    /** @param list<int> $ids @return \Illuminate\Database\Eloquent\Collection<int,ProductVariant> */
    private function lockVariants(array $ids): Collection
    {
        sort($ids, SORT_NUMERIC);
        $result = new Collection;
        foreach ($ids as $id) {
            $variant = ProductVariant::whereKey($id)->lockForUpdate()->first();
            if (! $variant) {
                $this->invalid('items', 'SKU không tồn tại hoặc đã bị xóa mềm; hãy sửa phiếu nháp.');
            }
            $result->put($id, $variant);
        }

        return $result;
    }

    private function draftOnly(StockImport $import): void
    {
        if ($import->status !== StockImportStatus::Draft) {
            throw new DomainException(ErrorCode::StateConflict);
        }
    }

    /** @param list<array<string,int>> $items */
    private function checkFreeReceipt(User $actor, ?string $note, array $items): void
    {
        if (in_array(0, array_column($items, 'unit_cost_amount'), true) && (! $actor->hasRole(RoleSlug::Admin) || mb_strlen($note ?? '') < 20)) {
            $this->invalid('items', 'Nhập tặng giá0 cần Admin ghi/duyệt trực tiếp với lý do ít nhất20 ký tự.');
        }
    }

    /** @param array<string,mixed> $data */
    private function replay(StockImport $existing, User $actor, array $data): StockImport
    {
        $items = $existing->items()->orderBy('variant_id')->lockForUpdate()->get()->map(fn ($i) => ['variant_id' => $i->variant_id, 'quantity' => $i->quantity, 'unit_cost_amount' => $i->unit_cost_amount, 'subtotal_amount' => Money::multiply($i->quantity, $i->unit_cost_amount)])->all();
        $stored = ['supplier_id' => $existing->supplier_id, 'note' => $existing->note, 'items' => $items, 'total_quantity' => $existing->total_quantity, 'total_cost_amount' => $existing->total_cost_amount, 'command_key' => $existing->command_key];
        if ($existing->created_by !== $actor->id || CanonicalPayload::fingerprint($stored) !== CanonicalPayload::fingerprint($data)) {
            throw new DomainException(ErrorCode::IdempotencyMismatch);
        }

        return $existing;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
