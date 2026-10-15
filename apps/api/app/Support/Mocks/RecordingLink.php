<?php

declare(strict_types=1);

namespace App\Support\Mocks;

use App\Models\MockInterview;
use Illuminate\Support\Facades\URL;

/**
 * The link a client opens to watch an interview recording without an account:
 * signed (cannot be guessed or edited) and expiring. The page behind it hands
 * out a short-lived storage URL for the video, so the bucket path itself never
 * leaves the server. Valid whether or not the recording has finished
 * uploading when the link is made — the page says so until it is ready.
 */
final class RecordingLink
{
    public const DAYS = 30;

    public static function for(MockInterview $mock): string
    {
        return URL::temporarySignedRoute('public.interview-recording', now()->addDays(self::DAYS), ['mock' => $mock->id]);
    }
}
