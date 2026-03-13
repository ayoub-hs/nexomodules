<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TaxGroup;

class MobileTaxController extends Controller
{
    /**
     * Lightweight tax-group list for mobile product admin flows.
     *
     * GET /api/mobile/tax-groups
     */
    public function index()
    {
        $groups = TaxGroup::query()
            ->with('taxes')
            ->orderBy('name')
            ->get()
            ->map(function (TaxGroup $group) {
                $rate = (float) $group->taxes->sum('rate');

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'rate' => $rate,
                    'description' => $group->description,
                ];
            });

        return response()->json($groups);
    }
}
