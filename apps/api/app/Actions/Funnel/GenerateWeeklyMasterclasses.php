<?php

declare(strict_types=1);

namespace App\Actions\Funnel;

use App\Actions\Batches\CreateBatch;
use App\Actions\Crm\MoveLeadStage;
use App\Actions\LiveClasses\ScheduleLiveSession;
use App\Actions\Roster\AddStudentToBatch;
use App\Enums\BatchMemberStatus;
use App\Enums\BatchType;
use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LeadStage;
use App\Models\User;
use App\Support\Crm\PhoneNormalizer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Weekly funnel stage 1 (runs within a tenant context): for every course with
 * masterclass interest — leads of type `masterclass` carrying that course's
 * slug — create the weekend masterclass batch and enrol each interested lead
 * who has registered an account (matched by normalized phone or email).
 *
 * Masterclasses run on BOTH weekend days: each course has a fixed day derived
 * from its position in the catalogue (1st, 3rd, 5th… course → Saturday;
 * 2nd, 4th… → Sunday), spreading trainer load across the weekend while a
 * given course's masterclass always falls on the same weekday.
 *
 * Idempotent: one masterclass batch per course per weekend day, and a student
 * already in a masterclass batch of that course is never enrolled twice, so
 * the command can run daily without duplicating anything.
 */
final readonly class GenerateWeeklyMasterclasses
{
    public function __construct(
        private CreateBatch $createBatch,
        private AddStudentToBatch $addStudent,
        private EnsureStudentAccount $ensureAccount,
        private ScheduleLiveSession $scheduleSession,
        private SendBatchWelcome $welcome,
        private MoveLeadStage $moveStage,
    ) {}

    /**
     * @return array{batches: int, enrolled: int}
     */
    /**
     * The weekend day this course's masterclass runs on, by catalogue position:
     * even index → Saturday, odd index → Sunday.
     */
    public static function weekendDayFor(int $courseIndex): int
    {
        return $courseIndex % 2 === 0 ? CarbonInterface::SATURDAY : CarbonInterface::SUNDAY;
    }

    /**
     * The date this course's next masterclass should sit on.
     *
     * At the default 7-day cadence this is simply the coming weekend day, which
     * is the behaviour this action has always had. A longer cycle
     * (funnel.masterclass_interval_days — settable from the CRM) counts forward
     * from the course's last masterclass instead and then snaps to that course's
     * weekend day, so a course keeps its fixed day and every lead arriving
     * during the cycle collects into that one upcoming batch.
     */
    private function nextClassDay(Course $course, CarbonInterface $reference, int $day): CarbonInterface
    {
        $targetIso = $day === CarbonInterface::SUNDAY ? 7 : 6;

        $snap = static function (CarbonInterface $date) use ($day, $targetIso): CarbonInterface {
            return $date->dayOfWeekIso === $targetIso ? $date : $date->next($day);
        };

        $upcoming = $snap(Carbon::parse($reference)->copy());

        $interval = (int) config('funnel.masterclass_interval_days', 7);

        if ($interval <= 7) {
            return $upcoming;
        }

        $last = Batch::query()
            ->where('course_id', $course->id)
            ->where('type', BatchType::Masterclass->value)
            ->where('status', '!=', 'cancelled')
            ->max('starts_on');

        if ($last === null) {
            return $upcoming;
        }

        $due = $snap(Carbon::parse($last)->addDays($interval));

        // Never schedule into the past: a long-idle course catches up on the
        // coming weekend rather than backdating a batch nobody can attend.
        return $due->lessThan($upcoming) ? $upcoming : $due;
    }

    public function handle(?CarbonInterface $reference = null): array
    {
        $reference = Carbon::parse($reference ?? now());

        $batches = 0;
        $enrolled = 0;

        $students = User::query()->where('user_type', 'student')->get();
        $byPhone = $students->filter(fn (User $u) => filled($u->phone))
            ->keyBy(fn (User $u) => PhoneNormalizer::normalize((string) $u->phone));
        $byEmail = $students->filter(fn (User $u) => filled($u->email))
            ->keyBy(fn (User $u) => mb_strtolower((string) $u->email));

        $courses = Course::query()->orderBy('position')->orderBy('id')->get();

        foreach ($courses as $courseIndex => $course) {
            $day = self::weekendDayFor($courseIndex);
            $classDay = $this->nextClassDay($course, $reference, $day);
            $leads = Lead::query()
                ->where('lead_type', 'masterclass')
                ->whereNull('merged_into_id')
                ->where('course_slug', $course->slug)
                ->get();

            if ($leads->isEmpty()) {
                continue;
            }

            $interested = $leads
                ->map(function (Lead $lead) use (&$byPhone, &$byEmail): ?User {
                    $phone = $lead->phone_normalized ?: PhoneNormalizer::normalize((string) $lead->phone);

                    $user = $byPhone->get($phone)
                        ?? ($lead->email ? $byEmail->get(mb_strtolower($lead->email)) : null);

                    if ($user !== null) {
                        return $user;
                    }

                    // No account yet — create one so nobody waits on manual
                    // registration (they'll sign in by OTP to this phone/email).
                    $user = $this->ensureAccount->handle($lead);

                    if ($user !== null) {
                        $byPhone = $byPhone->put(PhoneNormalizer::normalize((string) $user->phone), $user);
                        if (filled($user->email)) {
                            $byEmail = $byEmail->put(mb_strtolower((string) $user->email), $user);
                        }
                    }

                    return $user;
                })
                ->filter()
                ->unique('id')
                ->values();

            if ($interested->isEmpty()) {
                continue;
            }

            // A student already in ANY cohort for this course is not invited
            // again. Matching on masterclass-type batches alone is not enough:
            // a cohort keeps its batch and only changes type, so someone who
            // has moved on to the bootcamp or paid stage would otherwise be
            // pulled back into a fresh masterclass. A CANCELLED batch doesn't
            // count — its attendees are re-invited to the next one.
            $masterclassIds = Batch::query()
                ->where('course_id', $course->id)
                ->where('status', '!=', 'cancelled')
                ->pluck('id');

            $alreadySeated = $masterclassIds->isEmpty() ? collect() : BatchMember::query()
                ->whereIn('batch_id', $masterclassIds)
                ->pluck('user_id');

            $toEnrol = $interested->reject(fn (User $u) => $alreadySeated->contains($u->id))->values();

            if ($toEnrol->isEmpty()) {
                continue;
            }

            $batch = Batch::query()
                ->where('course_id', $course->id)
                ->where('type', BatchType::Masterclass->value)
                ->whereDate('starts_on', $classDay->toDateString())
                ->where('status', 'active')
                ->first();

            if ($batch === null) {
                // An admin cancelled this date's masterclass — leave the date
                // alone; these students get the following weekend instead.
                $cancelledExists = Batch::query()
                    ->where('course_id', $course->id)
                    ->where('type', BatchType::Masterclass->value)
                    ->whereDate('starts_on', $classDay->toDateString())
                    ->where('status', 'cancelled')
                    ->exists();

                if ($cancelledExists) {
                    continue;
                }

                $batch = $this->createBatch->handle($course, BatchType::Masterclass, [
                    'starts_on' => $classDay->toDateString(),
                    'ends_on' => $classDay->toDateString(),
                ]);
                $batches++;

                // Fully automatic: the class itself is scheduled at the default
                // slot (movable later from the CRM Masterclasses page).
                $this->scheduleSession->handle(
                    $batch,
                    'Masterclass — '.$course->name,
                    $classDay->copy()->setTimeFromTimeString((string) config('funnel.masterclass_time', '11:00')),
                    $classDay->copy()->setTimeFromTimeString((string) config('funnel.masterclass_time', '11:00'))
                        ->addMinutes((int) config('funnel.masterclass_duration_minutes', 90)),
                );
            }

            foreach ($toEnrol as $student) {
                try {
                    $this->addStudent->handle($batch, $student, BatchMemberStatus::Enrolled);
                    $enrolled++;

                    // Instant WhatsApp + email: batch number, class day/time and
                    // the dashboard link. The magic Zoom join link follows in
                    // the 12h/2h/5min reminders once the meeting exists.
                    $this->welcome->handle($student, $batch, $classDay->format('D, d M Y').' at '.config('funnel.masterclass_time', '11:00'));

                    // Reflect the seating on the lead itself, so the CRM shows
                    // the person as registered rather than still open interest.
                    $this->markLeadRegistered($leads, $student);
                } catch (ValidationException) {
                    // capacity / duplicate — skip, the next Saturday batch picks them up
                }
            }
        }

        return ['batches' => $batches, 'enrolled' => $enrolled];
    }

    /**
     * Move the lead behind a freshly seated student to "Masterclass Registered".
     * Without this the lead keeps looking like untouched interest even though
     * its owner already has a seat.
     *
     * @param  Collection<int, Lead>  $leads
     */
    private function markLeadRegistered(Collection $leads, User $student): void
    {
        $phone = $student->phone !== null ? PhoneNormalizer::normalize($student->phone) : null;
        $email = $student->email !== null ? mb_strtolower($student->email) : null;

        $lead = $leads->first(function (Lead $lead) use ($phone, $email): bool {
            $leadPhone = $lead->phone_normalized ?: PhoneNormalizer::normalize((string) $lead->phone);

            return ($phone !== null && $phone !== '' && $leadPhone === $phone)
                || ($email !== null && $lead->email !== null && mb_strtolower($lead->email) === $email);
        });

        if ($lead === null) {
            return;
        }

        $stage = LeadStage::query()->where('slug', 'masterclass-registered')->first();

        if ($stage !== null && $lead->lead_stage_id !== $stage->id) {
            $this->moveStage->handle($lead, $stage);
        }
    }
}
