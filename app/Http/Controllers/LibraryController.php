<?php

namespace App\Http\Controllers;

use App\Http\Requests\LibraryRequest;
use App\Http\Resources\Inertia\FileInertiaResource;
use App\Models\File;
use App\Models\SavedSearch;
use App\Services\LibraryService;
use Inertia\Inertia;
use Inertia\Response;

class LibraryController extends Controller
{
    public function index(LibraryRequest $request, LibraryService $library): Response
    {
        $user = $request->user();
        $filters = array_filter($request->safe()->except(['page', 'saved_search']), fn ($value): bool => $value !== null && $value !== '');
        $files = $library->query($user, $filters)->paginate(24)->withQueryString()
            ->through(function (File $file) use ($request, $user): array {
                $data = FileInertiaResource::forIndex($file)->withDetailsUrl()->toArray($request);
                if ($file->user_id !== $user->id) {
                    unset($data['review']);
                }

                $primary = $file->primaryEntity;
                $entity = $primary?->entity;
                $identity = null;
                if ($entity?->user_id === $file->user_id) {
                    $dateField = match ($primary->entity_type) {
                        'receipt' => 'receipt_date', 'invoice' => 'invoice_date', 'document' => 'document_date',
                        'contract' => 'effective_date', 'bank_statement' => 'statement_date',
                        'voucher' => 'expiry_date', 'warranty' => 'warranty_end_date', 'return_policy' => 'return_deadline',
                    };
                    $identity = [
                        'title' => match ($primary->entity_type) {
                            'receipt' => $entity->merchant?->name, 'invoice' => $entity->from_name,
                            'document' => $entity->title, 'contract' => $entity->contract_title,
                            'bank_statement' => $entity->bank_name, 'voucher' => $entity->code,
                            'warranty' => $entity->product_name, 'return_policy' => null,
                        },
                        'date' => $entity->{$dateField}?->toDateString(),
                        'amount' => match ($primary->entity_type) {
                            'receipt', 'invoice' => $entity->total_amount, 'contract' => $entity->contract_value,
                            'bank_statement' => $entity->closing_balance, 'voucher' => $entity->current_value,
                            default => null,
                        },
                        'currency' => $entity->currency,
                        'tax' => $entity->tax_amount,
                    ];
                }

                return [...$data,
                    'detailsUrl' => route('files.show', ['file' => $file->id, 'return_to' => $request->getRequestUri()]),
                    'identity' => $identity,
                    'entity_types' => $file->extractableEntities->where('user_id', $file->user_id)->pluck('entity_type')->unique()->values(),
                    'folder' => $file->user_id === $user->id ? $file->primaryFolder?->only(['id', 'name']) : null,
                    'owner' => $file->user?->name,
                    'is_shared' => $file->user_id !== $user->id,
                ];
            });
        $activeView = $request->filled('saved_search')
            ? SavedSearch::query()->where('user_id', $user->id)->find($request->integer('saved_search')) : null;

        return Inertia::render('Library/Index', [
            'files' => $files, 'filters' => $filters, 'options' => $library->options($user),
            'activeView' => $activeView?->only(['id', 'name', 'scope', 'filters', 'is_pinned']),
        ]);
    }
}
