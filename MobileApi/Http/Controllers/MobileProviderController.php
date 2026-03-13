<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileProviderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! function_exists('ns') || ! (ns()->allowedTo('nexopos.read.providers') || ns()->allowedTo('providers.read'))) {
            return response()->json([
                'status' => 'error',
                'message' => __('Forbidden.'),
            ], 403);
        }

        $query = Provider::query()
            ->select(['id', 'first_name', 'last_name'])
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get()->map(function (Provider $provider) {
                $name = trim($provider->first_name . ' ' . $provider->last_name);

                return [
                    'id' => (int) $provider->id,
                    'name' => $name !== '' ? $name : $provider->first_name,
                ];
            })->values(),
        ]);
    }
}
