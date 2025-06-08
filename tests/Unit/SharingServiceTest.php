<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\FileShare;
use App\Models\Receipt;
use App\Models\User;
use App\Services\SharingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharingServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected SharingService $sharingService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->sharingService = app(SharingService::class);
    }
    
    public function test_can_share_receipt_with_another_user()
    {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        
        $this->actingAs($owner);
        
        $receipt = Receipt::factory()->create(['user_id' => $owner->id]);
        
        $share = $this->sharingService->shareFile($receipt, $targetUser);
        
        $this->assertInstanceOf(FileShare::class, $share);
        $this->assertEquals(Receipt::class, $share->shareable_type);
        $this->assertEquals($receipt->id, $share->shareable_id);
        $this->assertEquals($targetUser->id, $share->shared_with_user_id);
        $this->assertEquals('view', $share->permission);
    }
    
    public function test_can_share_document_with_edit_permission()
    {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        
        $this->actingAs($owner);
        
        $document = Document::factory()->create(['user_id' => $owner->id]);
        
        $share = $this->sharingService->shareFile($document, $targetUser, [
            'permission' => 'edit',
        ]);
        
        $this->assertEquals('edit', $share->permission);
    }
    
    public function test_can_share_with_expiration()
    {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        
        $this->actingAs($owner);
        
        $document = Document::factory()->create(['user_id' => $owner->id]);
        
        $expiresAt = Carbon::now()->addDays(7);
        
        $share = $this->sharingService->shareFile($document, $targetUser, [
            'expires_at' => $expiresAt,
        ]);
        
        $this->assertEquals($expiresAt->format('Y-m-d'), $share->expires_at->format('Y-m-d'));
    }
    
    public function test_cannot_share_file_not_owned()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $targetUser = User::factory()->create();
        
        $this->actingAs($otherUser);
        
        $receipt = Receipt::factory()->create(['user_id' => $owner->id]);
        
        $this->expectException(\UnauthorizedHttpException::class);
        
        $this->sharingService->shareFile($receipt, $targetUser);
    }
    
    public function test_user_has_access_to_shared_file()
    {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        
        $this->actingAs($owner);
        
        $document = Document::factory()->create(['user_id' => $owner->id]);
        
        $this->sharingService->shareFile($document, $targetUser);
        
        $this->assertTrue($this->sharingService->userHasAccess($document, $targetUser));
        $this->assertTrue($this->sharingService->userHasAccess($document, $owner));
    }
    
    public function test_user_without_share_has_no_access()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        
        $document = Document::factory()->create(['user_id' => $owner->id]);
        
        $this->assertFalse($this->sharingService->userHasAccess($document, $otherUser));
    }
    
    public function test_expired_share_denies_access()
    {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        
        $this->actingAs($owner);
        
        $document = Document::factory()->create(['user_id' => $owner->id]);
        
        $this->sharingService->shareFile($document, $targetUser, [
            'expires_at' => Carbon::now()->subDay(),
        ]);
        
        $this->assertFalse($this->sharingService->userHasAccess($document, $targetUser));
    }
    
    public function test_view_permission_cannot_edit()
    {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        
        $this->actingAs($owner);
        
        $document = Document::factory()->create(['user_id' => $owner->id]);
        
        $this->sharingService->shareFile($document, $targetUser, [
            'permission' => 'view',
        ]);
        
        $this->assertTrue($this->sharingService->userHasAccess($document, $targetUser, 'view'));
        $this->assertFalse($this->sharingService->userHasAccess($document, $targetUser, 'edit'));
    }
    
    public function test_can_unshare_file()
    {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        
        $this->actingAs($owner);
        
        $document = Document::factory()->create(['user_id' => $owner->id]);
        
        $this->sharingService->shareFile($document, $targetUser);
        
        $this->assertTrue($this->sharingService->userHasAccess($document, $targetUser));
        
        $unshared = $this->sharingService->unshare($document, $targetUser);
        
        $this->assertTrue($unshared);
        $this->assertFalse($this->sharingService->userHasAccess($document, $targetUser));
    }
    
    public function test_cleanup_expired_shares()
    {
        $owner = User::factory()->create();
        $targetUser = User::factory()->create();
        
        $this->actingAs($owner);
        
        $document = Document::factory()->create(['user_id' => $owner->id]);
        
        // Create expired share
        $this->sharingService->shareFile($document, $targetUser, [
            'expires_at' => Carbon::now()->subDay(),
        ]);
        
        // Create valid share
        $receipt = Receipt::factory()->create(['user_id' => $owner->id]);
        $this->sharingService->shareFile($receipt, $targetUser, [
            'expires_at' => Carbon::now()->addDay(),
        ]);
        
        $deletedCount = $this->sharingService->cleanupExpiredShares();
        
        $this->assertEquals(1, $deletedCount);
        $this->assertEquals(1, FileShare::count());
    }
}