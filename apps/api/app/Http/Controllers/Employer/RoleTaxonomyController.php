<?php

declare(strict_types=1);

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Support\Roles\RoleTaxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The shared role vocabulary (PRD-E F14) — titles, skills, competencies and
 * question formats — served in one call so a JD form or mock designer can
 * populate every dropdown without a round trip per field.
 *
 * Static reference data, so it is cached at the edge rather than recomputed.
 */
final class RoleTaxonomyController extends Controller
{
    public function index(Request $request, RoleTaxonomy $taxonomy): JsonResponse
    {
        $title = $request->string('title')->trim()->toString();

        return response()->json(['data' => [
            'families' => $taxonomy->families(),
            'titles' => $taxonomy->titles(),
            'competencies' => $taxonomy->competencies(),
            'question_formats' => $taxonomy->questionFormats(),
            // When a title is supplied, the suggested skills for it come back
            // in the same call so the form can pre-select as the user types.
            'suggested' => $title !== '' ? $taxonomy->skillsForTitle($title) : null,
        ]]);
    }
}
