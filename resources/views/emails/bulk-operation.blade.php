<h1>Bulk Operation Completed</h1>

<p>Your bulk operation has been completed successfully.</p>

<div class="accent-box">
    <p style="margin: 0 0 8px 0;"><strong>Operation:</strong> {{ ucfirst($operation) }}</p>
    <p style="margin: 0;"><strong>Items Processed:</strong> {{ $count }}</p>
</div>

<p>All items have been processed and are now available in your account.</p>

<div class="text-center">
    <a href="{{ route('receipts.index') }}" class="btn btn-accent">View Receipts</a>
</div>