<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\UpdateAssignmentRuleRequest;
use App\Models\CrmAssignmentRule;
use Illuminate\Http\JsonResponse;

/**
 * Per-tenant counselor assignment rule + speed-to-lead SLA (PRD §6.12).
 */
final class CrmAssignmentRuleController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->rule()]);
    }

    public function update(UpdateAssignmentRuleRequest $request): JsonResponse
    {
        $rule = $this->rule();
        $rule->fill([
            'mode' => $request->string('mode')->toString(),
            'sla_minutes' => $request->integer('sla_minutes'),
            'by_course_map' => $request->input('by_course_map'),
        ])->save();

        return response()->json(['data' => $rule]);
    }

    private function rule(): CrmAssignmentRule
    {
        return CrmAssignmentRule::query()->firstOrCreate([], [
            'mode' => CrmAssignmentRule::MODE_ROUND_ROBIN,
            'sla_minutes' => 15,
        ]);
    }
}
