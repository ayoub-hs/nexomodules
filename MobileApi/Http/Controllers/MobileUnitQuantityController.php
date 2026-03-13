<?php

namespace Modules\MobileApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProductUnitQuantity;
use App\Services\TaxService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MobileApi\Support\MobileProductTransformer;
use Modules\NsContainerManagement\Services\ContainerService;

class MobileUnitQuantityController extends Controller
{
    public function __construct(
        protected TaxService $taxService,
        protected MobileProductTransformer $productTransformer,
        protected ContainerService $containerService
    ) {
    }

    /**
     * PATCH /api/mobile/unit-quantities/{id}
     */
    public function update(Request $request, int $id)
    {
        $unitQuantity = ProductUnitQuantity::with(['product', 'unit'])->find($id);

        if (! $unitQuantity instanceof ProductUnitQuantity) {
            return response()->json([
                'message' => 'Unit quantity not found',
            ], 404);
        }

        $validated = $request->validate([
            'sale_price_edit' => 'sometimes|numeric|min:0',
            'wholesale_price_edit' => 'sometimes|numeric|min:0',
            'cogs' => 'sometimes|numeric|min:0',
            'low_quantity' => 'sometimes|numeric|min:0',
            'stock_alert_enabled' => 'sometimes|boolean',
            'visible' => 'sometimes|boolean',
            'convert_unit_id' => 'sometimes|nullable|integer|exists:nexopos_units,id',
            'preview_url' => 'sometimes|nullable|string',
            'is_manufactured' => 'sometimes|boolean',
            'is_raw_material' => 'sometimes|boolean',
            'container_type_id' => [
                'sometimes',
                'integer',
                'min:0',
                Rule::when(
                    (int) $request->input('container_type_id', 0) > 0,
                    Rule::exists('ns_container_types', 'id')
                ),
            ],
        ]);

        $priceChanged = false;
        if (array_key_exists('sale_price_edit', $validated)) {
            $unitQuantity->sale_price_edit = $validated['sale_price_edit'];
            $priceChanged = true;
        }

        if (array_key_exists('wholesale_price_edit', $validated)) {
            $unitQuantity->wholesale_price_edit = $validated['wholesale_price_edit'];
            $priceChanged = true;
        }

        if (array_key_exists('cogs', $validated)) {
            $unitQuantity->cogs = $validated['cogs'];
        }

        if (array_key_exists('low_quantity', $validated)) {
            $unitQuantity->low_quantity = $validated['low_quantity'];
        }

        if (array_key_exists('stock_alert_enabled', $validated)) {
            $unitQuantity->stock_alert_enabled = (bool) $validated['stock_alert_enabled'];
        }

        if (array_key_exists('visible', $validated)) {
            $unitQuantity->visible = (bool) $validated['visible'];
        }

        if (array_key_exists('convert_unit_id', $validated)) {
            $unitQuantity->convert_unit_id = $validated['convert_unit_id'];
        }

        if (array_key_exists('preview_url', $validated)) {
            $unitQuantity->preview_url = $validated['preview_url'];
        }

        if (array_key_exists('is_manufactured', $validated)) {
            $unitQuantity->is_manufactured = (bool) $validated['is_manufactured'];
        }

        if (array_key_exists('is_raw_material', $validated)) {
            $unitQuantity->is_raw_material = (bool) $validated['is_raw_material'];
        }

        if ($priceChanged) {
            $product = $unitQuantity->product;
            $this->taxService->computeTax(
                $unitQuantity,
                $product?->tax_group_id,
                $product?->tax_type
            );
        }

        $unitQuantity->save();

        if ($request->exists('container_type_id')) {
            $containerTypeId = (int) ($validated['container_type_id'] ?? 0);
            if ($containerTypeId > 0) {
                $this->containerService->linkProductToContainer(
                    $unitQuantity->product_id,
                    $containerTypeId,
                    $unitQuantity->id,
                    $unitQuantity->unit_id
                );
            } else {
                $this->containerService->unlinkProductFromContainer(
                    $unitQuantity->product_id,
                    $unitQuantity->id
                );
            }
        }

        $unitQuantity->refresh()->loadMissing(['product', 'unit']);
        $linkIndex = $this->productTransformer->buildLinkIndex(collect([$unitQuantity->product]));
        $unitPayload = $this->productTransformer->transformUnitQuantity($unitQuantity->product, $unitQuantity, $linkIndex);

        return response()->json([
            'product_id' => $unitQuantity->product_id,
            'unit_quantity' => $unitPayload,
        ]);
    }
}
