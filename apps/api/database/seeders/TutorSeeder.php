<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Tutor\AskTutor;
use App\Actions\Tutor\IngestKnowledgeDocument;
use App\Actions\Tutor\RebuildTutorIndex;
use App\Enums\BatchMemberStatus;
use App\Models\BatchMember;
use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Models\TutorConversation;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Makes the AI Tutor (P3.3) demo-able immediately: ingests the seeded curriculum
 * into the knowledge base, adds a curated authored doc, and records one sample
 * conversation. Uses the FakeAiClient so seeding never spends real tokens.
 */
class TutorSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        // A curated authored document (survives reindex — it is source_type=manual).
        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $doc = KnowledgeDocument::query()->firstOrCreate(
                ['source_type' => 'manual', 'title' => 'How to debug a failing lab'],
                [
                    'tenant_id' => $tenant->id,
                    'body' => "When a coding lab fails, read the error message first.\n\n"
                        .'Print your variables to see what the program actually holds. '
                        .'Re-read the instructions and check your input parsing before your logic.',
                    'is_active' => true,
                ],
            );
            app(IngestKnowledgeDocument::class)->handle($doc);
        });

        app(RebuildTutorIndex::class)->handle($tenant);

        // One sample conversation, answered by the fake client (no token spend).
        $fake = new FakeAiClient;
        $fake->reply = 'Start by reading the error message, then print your variables to see '
            ."what your program holds [C1]. Re-check your input parsing before the logic.\n\nCONFIDENCE: high";
        app()->instance(AiClient::class, $fake);

        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        app(TenantContext::class)->run($tenant, function () use ($tenant, $occupying): void {
            $member = BatchMember::query()->whereIn('status', $occupying)->first();
            if ($member === null) {
                return;
            }

            $conversation = TutorConversation::query()->create([
                'tenant_id' => $tenant->id,
                'student_id' => $member->user_id,
                'title' => 'My lab keeps failing',
            ]);

            app(AskTutor::class)->handle(
                $member->student,
                'My coding lab keeps failing and I am not sure why. How should I debug it?',
                $conversation,
            );
        });
    }
}
