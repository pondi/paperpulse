<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Models\Collection;
use App\Models\Contract;
use App\Models\Document;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\ReturnPolicy;
use App\Models\Tag;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warranty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LibraryService
{
    public const TYPES = [
        'receipt' => Receipt::class, 'document' => Document::class, 'invoice' => Invoice::class,
        'contract' => Contract::class, 'bank_statement' => BankStatement::class,
        'voucher' => Voucher::class, 'warranty' => Warranty::class, 'return_policy' => ReturnPolicy::class,
    ];

    /** @return Builder<File> */
    public function query(User $user, array $filters): Builder
    {
        $view = $filters['view'] ?? 'all';
        $query = $view === 'shared'
            ? File::query()->accessibleBy($user)->where('files.user_id', '!=', $user->id)
            : File::query()->where('files.user_id', $user->id);
        $query->with(['extractableEntities', 'primaryFolder', 'user:id,name']);

        $type = $filters['type'] ?? 'all';
        if ($type !== 'all') {
            $query->where(function (Builder $files) use ($type): void {
                $files->whereIn('files.id', $this->entityQuery(self::TYPES[$type])->select('file_id'));
                if (in_array($type, ['receipt', 'document'], true)) {
                    $files->orWhere(function (Builder $unprocessed) use ($type): void {
                        $unprocessed->where('file_type', $type)->whereDoesntHave('extractableEntities');
                    });
                }
            });
        }

        if (! empty($filters['status'])) {
            $query->where('files.status', $filters['status']);
        }
        if (! empty($filters['collection_id'])) {
            $query->whereHas('collections', fn (Builder $collections) => $collections->whereKey($filters['collection_id']));
        }
        if (! empty($filters['tag_id'])) {
            $query->whereHas('tags', fn (Builder $tags) => $tags->whereKey($filters['tag_id']));
        }

        $today = $user->currentDate();
        [$from, $to] = match ($filters['date_range'] ?? 'all') {
            'last_30_days' => [$today->copy()->subDays(29)->toDateString(), $today->toDateString()],
            'this_month' => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
            'this_year' => [$today->copy()->startOfYear()->toDateString(), $today->copy()->endOfYear()->toDateString()],
            default => [$filters['date_from'] ?? null, $filters['date_to'] ?? null],
        };
        if ($view === 'recent') {
            $from = max($from ?? '', $today->copy()->subDays(29)->toDateString());
        }
        if ($from) {
            $query->where('uploaded_at', '>=', Carbon::parse($from, $today->getTimezone())->startOfDay()->utc());
        }
        if ($to) {
            $query->where('uploaded_at', '<', Carbon::parse($to, $today->getTimezone())->addDay()->startOfDay()->utc());
        }
        if ($view === 'needs-review') {
            $query->whereIn('files.status', ['needs_review', 'failed']);
        } elseif ($view === 'processing') {
            $query->whereIn('files.status', ['pending', 'processing']);
        } elseif ($view === 'unpaid') {
            $query->whereIn('files.id', $this->entityQuery(Invoice::class)
                ->whereIn('payment_status', ['unpaid', 'partial', 'overdue'])->where('amount_due', '>', 0)->select('file_id'));
        } elseif ($view === 'expiring') {
            $window = [$today->toDateString(), $today->copy()->addDays(90)->toDateString()];
            $query->where(function (Builder $files) use ($window): void {
                foreach ([Contract::class => 'expiry_date', Warranty::class => 'warranty_end_date', Voucher::class => 'expiry_date', ReturnPolicy::class => 'return_deadline'] as $model => $column) {
                    $entities = $this->entityQuery($model)->whereBetween($column, $window);
                    if ($model === Voucher::class) {
                        $entities->where('is_redeemed', false);
                    }
                    $files->orWhereIn('files.id', $entities->select('file_id'));
                }
            });
        }
        if ($term = trim($filters['query'] ?? '')) {
            $query->where(function (Builder $files) use ($term): void {
                $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
                $files->whereLike('fileName', $pattern)->orWhereLike('note', $pattern);
                foreach ([
                    Receipt::class => ['receipt_description'], Document::class => ['title', 'description', 'summary', 'content'],
                    Invoice::class => ['invoice_number', 'from_name', 'to_name', 'notes'], Contract::class => ['contract_title', 'contract_number', 'summary'],
                    BankStatement::class => ['bank_name', 'account_holder_name'], Voucher::class => ['code'],
                    Warranty::class => ['product_name', 'manufacturer'], ReturnPolicy::class => ['conditions'],
                ] as $model => $columns) {
                    $entities = $this->entityQuery($model)->where(function (Builder $entities) use ($columns, $pattern, $model): void {
                        foreach ($columns as $column) {
                            $entities->orWhereLike($column, $pattern);
                        }
                        if (in_array($model, [Receipt::class, Invoice::class, Voucher::class], true)) {
                            $entities->orWhereHas('merchant', fn (Builder $merchants) => $merchants->withoutGlobalScope('user')
                                ->whereColumn('merchants.user_id', $entities->getModel()->getTable().'.user_id')->whereLike('name', $pattern));
                        }
                    });
                    $files->orWhereIn('files.id', $entities->select('file_id'));
                }
            });
        }

        return match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('uploaded_at')->orderBy('files.id'),
            'name' => $query->orderBy('fileName')->orderBy('files.id'),
            default => $query->orderByDesc('uploaded_at')->orderByDesc('files.id'),
        };
    }

    /** @param class-string<Model> $model */
    private function entityQuery(string $model): Builder
    {
        return $model::withoutGlobalScope('user')->whereColumn($model::make()->getTable().'.user_id', 'files.user_id');
    }

    public function options(User $user): array
    {
        return [
            'collections' => Collection::query()->where('user_id', $user->id)->active()->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->where('user_id', $user->id)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
