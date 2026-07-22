<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Flashcards\GenerateFlashcards;
use App\Http\Controllers\Controller;
use App\Models\Flashcard;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin/trainer flashcard authoring for a lesson (PRD §6 learning loop). Gated by
 * `can:manage-curriculum`. Generate a draft with AI or write cards by hand; saving
 * publishes them to students (the save is the human-in-the-loop gate).
 */
final class FlashcardController extends Controller
{
    public function show(Lesson $lesson): JsonResponse
    {
        $cards = Flashcard::query()->where('lesson_id', $lesson->id)->orderBy('position')->orderBy('id')
            ->get(['id', 'front', 'back', 'source']);

        return response()->json(['data' => $cards]);
    }

    /** AI-draft cards from the lesson material. Draft only — saved via upsert. */
    public function generate(Request $request, Lesson $lesson, GenerateFlashcards $generate): JsonResponse
    {
        $count = (int) $request->integer('count', 10);
        $draft = $generate->handle($lesson, $request->user(), max(3, min(20, $count)));

        if ($draft === null) {
            return response()->json(['error' => [
                'code' => 'generation_failed',
                'message' => 'Flashcards could not be generated. Try again.',
                'details' => null,
            ]], 422);
        }

        return response()->json(['data' => $draft]);
    }

    /** Replace the lesson's flashcards with the submitted set. */
    public function upsert(Request $request, Lesson $lesson): JsonResponse
    {
        $validated = $request->validate([
            'cards' => ['array', 'max:200'],
            'cards.*.front' => ['required', 'string', 'max:1000'],
            'cards.*.back' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($lesson, $validated): void {
            Flashcard::query()->where('lesson_id', $lesson->id)->delete();

            foreach (array_values($validated['cards'] ?? []) as $i => $card) {
                Flashcard::query()->create([
                    'tenant_id' => $lesson->tenant_id,
                    'lesson_id' => $lesson->id,
                    'front' => trim((string) $card['front']),
                    'back' => trim((string) $card['back']),
                    'source' => 'manual',
                    'position' => $i,
                ]);
            }
        });

        return $this->show($lesson);
    }
}
