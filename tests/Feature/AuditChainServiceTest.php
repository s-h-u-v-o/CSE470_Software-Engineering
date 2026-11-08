<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AuditEntry;
use App\Models\User;
use App\Services\AuditChainService;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/** F26: hash-chained audit trail. */
class AuditChainServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuditChainService $audit;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-09 09:00:00'));
        $this->audit = app(AuditChainService::class);
    }

    public function test_first_entry_links_to_the_genesis_hash(): void
    {
        $entry = $this->audit->append('test.first', ['a' => 1]);

        $this->assertSame(1, $entry->sequence);
        $this->assertSame(AuditChainService::GENESIS_HASH, $entry->prev_hash);
        $this->assertSame(64, strlen($entry->hash));
    }

    public function test_entries_form_a_chain_with_consecutive_sequences(): void
    {
        $first = $this->audit->append('test.one');
        $second = $this->audit->append('test.two');
        $third = $this->audit->append('test.three');

        $this->assertSame([1, 2, 3], [$first->sequence, $second->sequence, $third->sequence]);
        $this->assertSame($first->hash, $second->prev_hash);
        $this->assertSame($second->hash, $third->prev_hash);
    }

    public function test_an_empty_chain_verifies(): void
    {
        $result = $this->audit->verify();

        $this->assertTrue($result['valid']);
        $this->assertSame(0, $result['entries_checked']);
        $this->assertNull($result['first_break']);
    }

    public function test_an_untouched_chain_verifies_after_a_database_round_trip(): void
    {
        // Unsorted keys and nested arrays must hash the same after being stored and read back.
        $this->audit->append('test.payload', ['b' => 1, 'a' => ['z' => 'ü/é', 'y' => [3, 2, 1]]]);
        $this->audit->append('test.empty');

        $result = $this->audit->verify();

        $this->assertTrue($result['valid'], json_encode($result));
        $this->assertSame(2, $result['entries_checked']);
    }

    public function test_actor_and_subject_are_recorded(): void
    {
        $user = User::factory()->create();
        $user->role = Role::SUPERVISOR;
        $user->save();
        $category = AssetCategory::create(['name' => 'Ultrasound']);
        $asset = Asset::create(['asset_category_id' => $category->id, 'name' => 'US 01', 'serial_number' => 'US-1']);

        $entry = $this->audit->append('asset.test', ['note' => 'x'], $user, $asset);

        $this->assertSame($user->id, $entry->actor_id);
        $this->assertSame(Role::SUPERVISOR, $entry->actor_role);
        $this->assertSame(Asset::class, $entry->subject_type);
        $this->assertSame($asset->id, $entry->subject_id);
        $this->assertTrue($this->audit->verify()['valid']);
    }

    public function test_a_tampered_payload_is_detected(): void
    {
        $this->seedThree();

        DB::table('audit_entries')->where('sequence', 2)->update(['payload' => json_encode(['status' => 'forged'])]);
        $result = $this->audit->verify();

        $this->assertFalse($result['valid']);
        $this->assertSame(2, $result['first_break']['sequence']);
        $this->assertSame('hash_mismatch', $result['first_break']['reason']);
        $this->assertSame(1, $result['entries_checked']);
    }

    public function test_a_tampered_previous_hash_is_detected(): void
    {
        $this->seedThree();

        DB::table('audit_entries')->where('sequence', 3)->update(['prev_hash' => str_repeat('a', 64)]);
        $result = $this->audit->verify();

        $this->assertFalse($result['valid']);
        $this->assertSame(3, $result['first_break']['sequence']);
        $this->assertSame('prev_hash_mismatch', $result['first_break']['reason']);
    }

    public function test_a_deleted_middle_entry_is_detected(): void
    {
        $this->seedThree();

        DB::table('audit_entries')->where('sequence', 2)->delete();
        $result = $this->audit->verify();

        $this->assertFalse($result['valid']);
        $this->assertSame(2, $result['first_break']['sequence']);
        $this->assertSame('sequence_gap', $result['first_break']['reason']);
    }

    public function test_a_deleted_newest_entry_is_detected_by_the_chain_head(): void
    {
        $this->seedThree();

        DB::table('audit_entries')->where('sequence', 3)->delete();
        $result = $this->audit->verify();

        $this->assertFalse($result['valid']);
        $this->assertSame('head_mismatch', $result['first_break']['reason']);
    }

    public function test_entries_are_append_only(): void
    {
        $entry = $this->audit->append('test.locked', ['a' => 1]);

        $this->expectException(LogicException::class);
        $entry->update(['event_type' => 'test.changed']);
    }

    public function test_entries_cannot_be_deleted_through_the_model(): void
    {
        $entry = $this->audit->append('test.locked');

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_a_rolled_back_transaction_leaves_no_audit_entry_and_no_gap(): void
    {
        try {
            DB::transaction(function () {
                $this->audit->append('test.rolled_back');
                throw new \RuntimeException('business change failed');
            });
        } catch (\RuntimeException) {
        }
        $this->audit->append('test.after');

        $this->assertSame(1, AuditEntry::count());
        $this->assertSame(1, AuditEntry::first()->sequence);
        $this->assertTrue($this->audit->verify()['valid']);
    }

    private function seedThree(): void
    {
        $this->audit->append('test.one', ['n' => 1]);
        $this->audit->append('test.two', ['n' => 2]);
        $this->audit->append('test.three', ['n' => 3]);
    }
}
