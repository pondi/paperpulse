<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesEntityCrud;
use App\Http\Requests\EntityIndexRequest;
use App\Http\Resources\Inertia\VoucherInertiaResource;
use App\Models\Tag;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends BaseResourceController
{
    use HandlesEntityCrud;

    protected string $model = Voucher::class;

    protected string $resource = 'Vouchers';

    protected array $indexWith = ['merchant', 'file'];

    protected array $showWith = ['merchant', 'file', 'tags'];

    protected array $searchableFields = ['code', 'voucher_type'];

    protected string $defaultSort = 'expiry_date';

    protected string $defaultSortDirection = 'asc';

    /**
     * Display a listing of vouchers.
     */
    public function index(Request $request): Response
    {
        $filters = app(EntityIndexRequest::class)->validated();
        $query = Voucher::where('user_id', $request->user()->id)->with($this->indexWith);

        if ($search = $filters['search'] ?? null) {
            $query->where(function ($query) use ($search): void {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('merchant', fn ($merchant) => $merchant->where('name', 'like', "%{$search}%"));
            });
        }

        if ($type = $filters['type'] ?? null) {
            $query->where('voucher_type', $type);
        }

        $today = $request->user()->currentDate()->toDateString();
        match ($filters['status'] ?? null) {
            'redeemed' => $query->where('is_redeemed', true),
            'expired' => $query->where('expiry_date', '<', $today),
            'active' => $query->where('is_redeemed', false)
                ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', $today)),
            default => null,
        };

        $vouchers = $query->orderBy($filters['sort'] ?? $this->defaultSort, $filters['sort_direction'] ?? $this->defaultSortDirection)
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? $this->perPage)->withQueryString()
            ->through(fn (Voucher $voucher) => VoucherInertiaResource::forIndex($voucher)->toArray($request));

        return Inertia::render('Vouchers/Index', [
            'vouchers' => $vouchers->items(),
            'filters' => $filters,
            'pagination' => [
                'links' => $vouchers->linkCollection(),
                'from' => $vouchers->firstItem(),
                'to' => $vouchers->lastItem(),
                'total' => $vouchers->total(),
            ],
        ]);
    }

    /**
     * Display the specified voucher.
     */
    public function show($id): Response
    {
        $voucher = Voucher::accessibleBy(auth()->user())->with($this->showWith)
            ->with(['file' => fn ($query) => $query->withoutGlobalScope('user')])->findOrFail($id instanceof Voucher ? $id->id : $id);

        $this->authorize('view', $voucher);

        return Inertia::render('Vouchers/Show', [
            'voucher' => VoucherInertiaResource::forShow($voucher)->toArray(request()),
            'available_tags' => auth()->user()->tags()->orderBy('name')->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'Vouchers', 'href' => route('vouchers.index')],
                ['label' => $voucher->code ?? 'Voucher #'.$voucher->id],
            ],
        ]);
    }

    /**
     * Transform item for index display.
     */
    protected function transformForIndex(Model $item): array
    {
        return VoucherInertiaResource::forIndex($item)->toArray(request());
    }

    /**
     * Transform item for show display.
     */
    protected function transformForShow(Model $item): array
    {
        return VoucherInertiaResource::forShow($item)->toArray(request());
    }

    /**
     * Mark voucher as redeemed.
     */
    public function redeem(Request $request, Voucher $voucher): mixed
    {
        $this->authorize('update', $voucher);

        $voucher->update([
            'is_redeemed' => true,
            'redeemed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Voucher marked as redeemed');
    }

    public function download(Voucher $voucher): mixed
    {
        return $this->entityDownload($voucher);
    }

    public function destroy($id): mixed
    {
        $voucher = $id instanceof Voucher
            ? $id
            : Voucher::findOrFail($id);

        return $this->entityDestroy($voucher);
    }

    public function attachTag(Request $request, Voucher $voucher): mixed
    {
        return $this->entityAttachTag($request, $voucher);
    }

    public function detachTag(Voucher $voucher, Tag $tag): mixed
    {
        return $this->entityDetachTag($voucher, $tag);
    }

    protected function getRouteName(): string
    {
        return 'vouchers';
    }
}
