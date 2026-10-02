<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\File;
use App\Models\Receipt;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->limit(50)->get();
        $targets = $notifications->mapWithKeys(fn (DatabaseNotification $notification): array => [
            $notification->id => $this->notificationTarget($notification->data),
        ]);
        $destinations = [];
        foreach ($targets->filter()->groupBy('model') as $model => $modelTargets) {
            $allowedIds = $model::accessibleBy($user)->whereKey($modelTargets->pluck('id')->all())->pluck('id');
            foreach ($modelTargets as $target) {
                if ($allowedIds->contains($target['id'])) {
                    $destinations[$model][$target['id']] = route($target['route'], $target['id']);
                }
            }
        }

        return response()->json([
            'notifications' => $notifications->map(function (DatabaseNotification $notification) use ($targets, $destinations): array {
                $target = $targets[$notification->id];
                $url = $target ? ($destinations[$target['model']][$target['id']] ?? null) : match ($notification->data['type'] ?? null) {
                    'scanner_files_imported' => route('pulsedav.index'),
                    'bulk_operation_completed' => route('receipts.index'),
                    'receipt_failed' => route('documents.upload'),
                    'weekly_summary' => route('dashboard'),
                    default => null,
                };

                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'url' => $url,
                ];
            }),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * @return array{model: class-string, id: int, route: string}|null
     */
    private function notificationTarget(array $data): ?array
    {
        [$model, $field, $route] = match ($data['type'] ?? null) {
            'receipt_processed', 'receipt_shared' => [Receipt::class, 'receipt_id', 'receipts.show'],
            'document_shared' => [Document::class, 'document_id', 'documents.show'],
            'voucher_expiring' => [Voucher::class, 'voucher_id', 'vouchers.show'],
            'warranty_ending' => [File::class, 'file_id', 'files.show'],
            'duplicate_file_detected' => [File::class, 'existing_file_id', 'files.show'],
            default => [null, null, null],
        };

        return $model && isset($data[$field]) ? ['model' => $model, 'id' => (int) $data[$field], 'route' => $route] : null;
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['success' => true, 'unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true, 'unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return response()->json(['success' => true, 'unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function clear(Request $request): JsonResponse
    {
        $request->user()->notifications()->delete();

        return response()->json(['success' => true, 'unread_count' => 0]);
    }
}
