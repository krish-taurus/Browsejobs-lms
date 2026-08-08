<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Support\CreateTicket;
use App\Actions\Support\DeflectTicket;
use App\Actions\Support\TriageTicket;
use App\Actions\Tutor\IngestKnowledgeDocument;
use App\Enums\BatchMemberStatus;
use App\Enums\TicketCategory;
use App\Models\BatchMember;
use App\Models\KnowledgeDocument;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\AiClient;
use App\Services\AI\FakeAiClient;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * The support policy corpus AI deflection answers from (PRD §6.13, P3.5d-a), plus one
 * demo deflection so `/support` is demo-able immediately.
 *
 * **Every fact below is transcribed from the PRD** (§6.8 fee/EMI/escalation ladder,
 * §6.9 pause-and-defer, §14 fee model, Platform Spec §3 contact + guarantee). Nothing
 * here is invented: deflection may only state policy that the company has actually
 * committed to, and a seeded guess would be indistinguishable from a real promise once
 * the model repeats it to a student.
 *
 * These are starter documents, not a finished corpus. The operational questions that
 * drive most real ticket volume — batch-transfer rules, refund mechanics beyond the
 * 30-day window, hardware requirements — are deliberately absent because the PRD does
 * not answer them. Staff author those (`source_type=support`) once the answers exist;
 * see the "support corpus source-of-truth" item in docs/session-prompts.md. Deflection
 * quality is a function of this corpus, not of the model.
 */
class SupportCorpusSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'browsejobs')->first();
        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            foreach ($this->documents() as $doc) {
                $document = KnowledgeDocument::query()->updateOrCreate(
                    ['source_type' => 'support', 'title' => $doc['title']],
                    [
                        'tenant_id' => $tenant->id,
                        'category' => $doc['category'],
                        'body' => $doc['body'],
                        'is_active' => true,
                    ],
                );

                app(IngestKnowledgeDocument::class)->handle($document);
            }
        });

        $this->demoDeflection($tenant);
        $this->demoTriage($tenant);
    }

    /**
     * @return list<array{title: string, category: string, body: string}>
     */
    private function documents(): array
    {
        return [
            [
                'title' => 'Registration fee and EMI schedule',
                'category' => TicketCategory::Payments->value,
                'body' => "Registration is ₹30,000. You pay it only after the free masterclass and the free 7-hour Python bootcamp — never before.\n\n"
                    ."You can pay it in one payment, or as EMI in up to 3 monthly instalments of ₹10,000. The first instalment is charged at checkout. The rest fall on the same calendar day of each following month. You see the full schedule before you confirm anything.\n\n"
                    ."Payment links are sent to you on WhatsApp and email. They carry your name, your course, your batch start date, and any voucher already applied. UPI AutoPay is available for automatic EMI debit.\n\n"
                    .'Every amount is set by us and shown to you in writing before you pay.',
            ],
            [
                'title' => 'Placement fee — what it is and when it is due',
                'category' => TicketCategory::Payments->value,
                'body' => "The placement fee is your first 3 months' CTC. It is due only after you accept an offer. If you do not accept an offer, you do not owe it.\n\n"
                    ."It is payable in 6 monthly EMIs. The ₹30,000 you already paid at registration is adjusted inside it — you are not charged that twice.\n\n"
                    ."The placement-fee agreement is signed, not a click-through.\n\n"
                    .'Nobody can guarantee employment — the market decides. What we put in writing is the process and the fee.',
            ],
            [
                'title' => '30-day money-back guarantee',
                'category' => TicketCategory::Payments->value,
                'body' => "You can ask for your money back within 30 days of paying, for any reason. You do not have to justify it.\n\n"
                    ."The guarantee is in writing.\n\n"
                    .'To start it, raise a ticket under Payments and a human will process it.',
            ],
            [
                'title' => 'What happens if an instalment is late',
                'category' => TicketCategory::Payments->value,
                'body' => "You get reminders 7 days, 3 days, and 1 day before an instalment is due, and again on the due day.\n\n"
                    ."After the due date there is a grace period — by default 5 days — with a daily reminder.\n\n"
                    ."After grace, live classes and the Recordings tab are locked. The AI tutor stays open. Seven days after that, only the fee screen stays reachable and a counsellor is asked to call you.\n\n"
                    ."The moment your payment is captured, access unlocks automatically. You do not have to ask anyone to restore it.\n\n"
                    .'If you cannot pay on time, raise a ticket under Payments before the due date and talk to us. Waivers, extensions, and exceptions are decided by a person, not by this assistant.',
            ],
            [
                'title' => 'Class recordings and missed classes',
                'category' => TicketCategory::Technical->value,
                'body' => "Every live class is recorded and appears in your Recordings tab.\n\n"
                    ."If you miss a class, we follow up with the recording link.\n\n"
                    ."The Recordings tab is locked while fees are overdue past the grace period, and unlocks automatically once payment is captured.\n\n"
                    .'If a recording is missing or will not play, raise a ticket under Technical.',
            ],
            [
                'title' => 'If a class is rescheduled or cancelled',
                'category' => TicketCategory::Training->value,
                'body' => "If a class moves, you are notified with the old time and the new time, and your calendar invite is updated.\n\n"
                    ."Your reminders are re-armed automatically for the new time — 12 hours, 2 hours, and 5 minutes before, each with a join link.\n\n"
                    .'If a class was cancelled and you have not heard about a replacement, raise a ticket under Training and your trainer will pick it up.',
            ],
            [
                'title' => 'Pausing or deferring your enrolment',
                'category' => TicketCategory::Training->value,
                'body' => "If life gets in the way, you do not have to drop out. You can freeze your enrolment and rejoin a later batch.\n\n"
                    ."The limits on how long and how often depend on your enrolment — a human will confirm what applies to you.\n\n"
                    .'Raise a ticket under Training to start it.',
            ],
            [
                'title' => 'How to reach a human',
                'category' => TicketCategory::Other->value,
                'body' => "Support hours are Monday to Saturday, 9:00 to 19:00 IST.\n\n"
                    ."Phone: +91 79756 66665 or +91 63634 02404. Email: hello@browsejobs.ai. Office: Whitefield, Bengaluru.\n\n"
                    .'Raising a ticket here reaches the right team directly, and you can see who it was assigned to.',
            ],
        ];
    }

    /**
     * Triage the seeded demo ticket, plus one angry payments ticket, so the staff
     * workspace demonstrates what P3.5d-b actually does: an AI-raised priority that
     * jumps the queue, carries an "AI raised this" line, and can be undone.
     */
    private function demoTriage(Tenant $tenant): void
    {
        $fake = new FakeAiClient;
        app()->instance(AiClient::class, $fake);

        app(TenantContext::class)->run($tenant, function () use ($fake): void {
            $existing = Ticket::query()->where('subject', 'Recordings will not play on my phone')->first();
            if ($existing !== null) {
                $fake->reply = '{"category":"technical","urgency":"normal","sentiment":"frustrated","duplicate_of":null}';
                app(TriageTicket::class)->handle($existing);
            }

            $student = $existing?->student ?? User::query()->where('user_type', 'student')->first();
            if ($student === null) {
                return;
            }

            $angry = app(CreateTicket::class)->handle(
                $student,
                TicketCategory::Payments,
                'My EMI was debited twice',
                'You have taken ₹10,000 from my account twice this month. I have emailed twice and nobody has replied. This is not acceptable — fix it or refund me today.',
            );

            $fake->reply = '{"category":"payments","urgency":"high","sentiment":"angry","duplicate_of":null}';
            app(TriageTicket::class)->handle($angry);
        });
    }

    /** One offered deflection so the student support page has something to show. */
    private function demoDeflection(Tenant $tenant): void
    {
        $fake = new FakeAiClient;
        $fake->reply = 'Your registration fee is ₹30,000, and you only pay it after the free masterclass and bootcamp [C1]. '
            ."You can split it into 3 monthly instalments of ₹10,000, with the first charged at checkout [C1].\n\nCONFIDENCE: high";
        app()->instance(AiClient::class, $fake);

        $occupying = array_map(fn (BatchMemberStatus $s) => $s->value, BatchMemberStatus::occupying());

        app(TenantContext::class)->run($tenant, function () use ($occupying): void {
            $member = BatchMember::query()->whereIn('status', $occupying)->first();
            if ($member === null) {
                return;
            }

            app(DeflectTicket::class)->handle(
                $member->student,
                TicketCategory::Payments,
                'How much is the registration fee and can I pay it in instalments?',
            );
        });
    }
}
