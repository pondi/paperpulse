<?php

use App\Services\TenantReferenceBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(TenantReferenceBackfill::class)->run('merchants');
        app(TenantReferenceBackfill::class)->run('vendors');
    }

    public function down(): void
    {
        throw new RuntimeException('Tenant repairs cannot be reversed safely. Restore a pre-upgrade database backup.');
    }
};
