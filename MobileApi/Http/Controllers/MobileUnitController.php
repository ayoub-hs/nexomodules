<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitGroup;

class MobileUnitController extends Controller
{
    /**
     * Lightweight unit list for mobile selection flows.
     *
     * GET /api/mobile/units
     */
    public function index()
    {
        $units = Unit::query()
            ->orderBy('name')
            ->get(['id', 'name', 'identifier'])
            ->map(fn($unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'symbol' => $unit->identifier,
            ]);

        return response()->json($units);
    }

    /**
     * Lightweight unit-group list for mobile product admin flows.
     *
     * GET /api/mobile/unit-groups
     */
    public function groups()
    {
        $groups = UnitGroup::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
            ]);

        return response()->json($groups);
    }
}
