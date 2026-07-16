<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeadStageResource;
use App\Models\LeadStage;
use Illuminate\Http\JsonResponse;

final class LeadStageController extends Controller
{
    public function index(): JsonResponse
    {
        $stages = LeadStage::query()
            ->withCount(['leads' => fn ($q) => $q->whereNull('merged_into_id')])
            ->orderBy('position')
            ->get();

        return LeadStageResource::collection($stages)->response();
    }
}
