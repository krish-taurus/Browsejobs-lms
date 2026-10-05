<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * WhatsApp copy for the two-round interview flow.
 *
 * The `name` is the Meta-approved template this maps onto; until Meta approves
 * it, Messenger falls back to sending the body as session text, which only
 * reaches someone already inside the 24-hour window. That is fine for the panel
 * (staff message us constantly) and the reason the student-facing ones matter
 * most — get bj_interview_approved and bj_interview_rescheduled approved first.
 */
class InterviewMessagingSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'interview_applied',
                'name' => 'bj_interview_applied',
                'body' => "*New interview request*\n{{student}} has applied for Round {{round}} ({{stage}}).\nSlot asked for: {{slot}}\n\nApprove or move it here:\n{{url}}",
            ],
            [
                'key' => 'interview_approved',
                'name' => 'bj_interview_approved',
                'body' => "*Interview confirmed* ✅\nRound {{round}} — {{stage}}\nWhen: {{slot}}\nWith: {{interviewer}}\n\nYour interview page: {{url}}",
            ],
            [
                'key' => 'interview_rescheduled',
                'name' => 'bj_interview_rescheduled',
                'body' => "*Interview moved*\nRound {{round}} — {{stage}}\nWas: {{was}}\nNow: {{slot}}\nWith: {{interviewer}}\n\n{{note}}\nYour interview page: {{url}}",
            ],
            // Cleared and not-cleared are separate templates on purpose. One
            // template with a swapped-in verdict would either congratulate
            // someone who failed or flatten the good news — and this is the
            // message a candidate remembers.
            [
                'key' => 'interview_cleared',
                'name' => 'bj_interview_cleared',
                'body' => "*You cleared Round {{round}}* 🎉\n{{stage}}\n\n{{note}}\n\n{{next}}\n{{url}}",
            ],
            [
                'key' => 'interview_not_cleared',
                'name' => 'bj_interview_not_cleared',
                'body' => "*Round {{round}} result*\n{{stage}}\n\nYou did not clear this round this time.\n\n{{note}}\n\nYou can apply again when you are ready:\n{{url}}",
            ],
        ];

        foreach (Tenant::query()->pluck('id') as $tenantId) {
            foreach ($templates as $t) {
                DB::table('message_templates')->updateOrInsert(
                    [
                        'tenant_id' => $tenantId,
                        'key' => $t['key'],
                        'channel' => 'whatsapp',
                        'locale' => 'en',
                    ],
                    [
                        'name' => $t['name'],
                        'category' => 'utility',
                        'body' => $t['body'],
                        'active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }
}
