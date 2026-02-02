<?php

namespace Modules\NsContainerManagement\Http\Controllers;

use App\Http\Controllers\DashboardController as BaseDashboardController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\NsContainerManagement\Crud\ContainerTypeCrud;
use Modules\NsContainerManagement\Crud\ContainerInventoryCrud;
use Modules\NsContainerManagement\Crud\ReceiveContainerCrud;
use Modules\NsContainerManagement\Crud\CustomerBalanceCrud;
use Modules\NsContainerManagement\Crud\ContainerAdjustmentCrud;
use Modules\NsContainerManagement\Models\ContainerType;

class DashboardController extends BaseDashboardController
{
    public function containerTypes(): mixed
    {
        return ContainerTypeCrud::table();
    }

    public function createContainerType(): mixed
    {
        return ContainerTypeCrud::form();
    }

    public function editContainerType($id): mixed
    {
        return ContainerTypeCrud::form(
            ContainerType::findOrFail($id)
        );
    }

    public function inventory(): mixed
    {
        return ContainerInventoryCrud::table();
    }

    public function adjustStock(): mixed
    {
        return ContainerAdjustmentCrud::form();
    }

    public function receive(): mixed
    {
        return ReceiveContainerCrud::form();
    }

    public function customers(): mixed
    {
        return CustomerBalanceCrud::table();
    }

    public function filters(): JsonResponse
    {
        return response()->json([
            'customers' => \DB::table('nexopos_users')
                ->where(function($query) {
                    $query->whereNotNull('first_name')
                          ->orWhereNotNull('last_name')
                          ->orWhereNotNull('username');
                })
                ->select('id', \DB::raw("COALESCE(first_name, COALESCE(last_name, username)) as name"))
                ->orderBy('name')
                ->get(),

            'types' => \DB::table('ns_container_types')
                ->where('is_active', true)
                ->select('id', 'name', 'deposit_fee')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function charge(): View
    {
        return view('nscontainermanagement::charge', [
            'title' => __('Charge Customer'),
            'description' => __('Charge customer for container deposits or fees.'),
            'customer_id' => request('customer_id'),
            'container_type_id' => request('container_type_id'),
        ]);
    }

    public function processCharge(Request $request): JsonResponse
    {
        try {
            \Log::info('Processing charge request', [
                'data' => $request->all(),
                'user' => auth()->id() ?? 0
            ]);

            $validated = $request->validate([
                'customer_id' => 'required|exists:nexopos_users,id',
                'container_type_id' => 'required|exists:ns_container_types,id',
                'quantity' => 'required|integer|min:1',
                'unit_price' => 'required|numeric|min:0',
                'total_amount' => 'required|numeric|min:0',
                'charge_type' => 'required|string',
                'notes' => 'nullable|string|max:500'
            ]);

            \Log::info('Validation passed', ['validated' => $validated]);

            // Create a charge movement record
            $movement = new \Modules\NsContainerManagement\Models\ContainerMovement([
                'customer_id' => $validated['customer_id'],
                'container_type_id' => $validated['container_type_id'],
                'quantity' => $validated['quantity'],
                'direction' => 'charge',
                'source_type' => 'charge', // Always use 'charge' for container charges
                'total_deposit_value' => $validated['total_amount'],
                'note' => $validated['notes'] ?? null,
                'author' => auth()->id() ?? 0 // Add current user as author
            ]);

            $movement->save();
            \Log::info('Movement created', ['movement_id' => $movement->id]);

            // Note: Customer balance is automatically updated by ContainerMovement::booted()
            // No need to manually update balance here

            return response()->json([
                'success' => true,
                'message' => 'Charge processed successfully',
                'movement_id' => $movement->id,
                'note' => 'Balance automatically updated by movement system'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->errors()->all()),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Charge processing error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function reports(): View
    {
        return view('nscontainermanagement::reports', [
            'title' => __('Container Reports'),
            'description' => __('View container movement history and outstanding customer balances.'),
        ]);
    }
}
