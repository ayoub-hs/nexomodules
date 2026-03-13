<?php

namespace Modules\MobileApi\Support;

use App\Models\Product;
use Illuminate\Support\Collection;
use Modules\NsContainerManagement\Services\ContainerService;

class MobileProductTransformer
{
    public function __construct(
        protected ContainerService $containerService
    ) {
    }

    public function transformProducts(Collection $products): Collection
    {
        return $products->map(fn (Product $product) => $this->transformProduct($product))
            ->values();
    }

    public function transformProduct(Product $product): array
    {
        return $this->transformProductWithLinkIndex($product, $this->buildLinkIndex(collect([$product])));
    }

    public function transformUnitQuantity(Product $product, $unitQuantity, ?array $linkIndex = null): array
    {
        $linkIndex = $linkIndex ?? $this->buildLinkIndex(collect([$product]));
        $linkKey = $this->buildLinkKey($product->id, $unitQuantity->id);

        return [
            'id' => $unitQuantity->id,
            'unit_id' => $unitQuantity->unit_id,
            'barcode' => $unitQuantity->barcode,
            'sale_price' => (float) $unitQuantity->sale_price,
            'sale_price_edit' => (float) ($unitQuantity->sale_price_edit ?? $unitQuantity->sale_price),
            'sale_price_with_tax' => (float) $unitQuantity->sale_price_with_tax,
            'wholesale_price' => (float) $unitQuantity->wholesale_price,
            'wholesale_price_edit' => (float) $unitQuantity->wholesale_price_edit,
            'cogs' => (float) ($unitQuantity->cogs ?? 0),
            'low_quantity' => (float) ($unitQuantity->low_quantity ?? 0),
            'stock_alert_enabled' => (bool) ($unitQuantity->stock_alert_enabled ?? false),
            'visible' => (bool) ($unitQuantity->visible ?? true),
            'convert_unit_id' => $unitQuantity->convert_unit_id,
            'preview_url' => $unitQuantity->preview_url,
            'is_manufactured' => (bool) ($unitQuantity->is_manufactured ?? false),
            'is_raw_material' => (bool) ($unitQuantity->is_raw_material ?? false),
            'quantity' => (float) $unitQuantity->quantity,
            'unit' => $unitQuantity->unit ? [
                'id' => $unitQuantity->unit->id,
                'name' => $unitQuantity->unit->name,
                'identifier' => $unitQuantity->unit->identifier,
            ] : null,
            'container_link' => $linkIndex[$linkKey] ?? null,
        ];
    }

    public function transformProductWithLinkIndex(Product $product, array $linkIndex): array
    {
        $primaryUnitQuantity = $product->unit_quantities->first();

        return [
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'barcode_type' => $product->barcode_type,
            'sku' => $product->sku,
            'status' => $product->status,
            'category_id' => $product->category_id,
            'description' => $product->description,
            'stock_management' => $product->stock_management,
            'tax_group_id' => $product->tax_group_id,
            'tax_type' => $product->tax_type,
            'unit_group' => $product->unit_group,
            'accurate_tracking' => (bool) $product->accurate_tracking,
            'auto_cogs' => (bool) $product->auto_cogs,
            'on_expiration' => $product->on_expiration,
            'is_manufactured' => (bool) ($product->is_manufactured ?? false),
            'is_raw_material' => (bool) ($product->is_raw_material ?? false),
            'unit_quantities' => $product->unit_quantities->map(function ($unitQuantity) use ($product, $linkIndex) {
                $linkKey = $this->buildLinkKey($product->id, $unitQuantity->id);

                return [
                    'id' => $unitQuantity->id,
                    'unit_id' => $unitQuantity->unit_id,
                    'barcode' => $unitQuantity->barcode,
                    'sale_price' => (float) $unitQuantity->sale_price,
                    'sale_price_edit' => (float) ($unitQuantity->sale_price_edit ?? $unitQuantity->sale_price),
                    'sale_price_with_tax' => (float) $unitQuantity->sale_price_with_tax,
                    'wholesale_price' => (float) $unitQuantity->wholesale_price,
                    'wholesale_price_edit' => (float) $unitQuantity->wholesale_price_edit,
                    'cogs' => (float) ($unitQuantity->cogs ?? 0),
                    'low_quantity' => (float) ($unitQuantity->low_quantity ?? 0),
                    'stock_alert_enabled' => (bool) ($unitQuantity->stock_alert_enabled ?? false),
                    'visible' => (bool) ($unitQuantity->visible ?? true),
                    'convert_unit_id' => $unitQuantity->convert_unit_id,
                    'preview_url' => $unitQuantity->preview_url,
                    'is_manufactured' => (bool) ($unitQuantity->is_manufactured ?? false),
                    'is_raw_material' => (bool) ($unitQuantity->is_raw_material ?? false),
                    'quantity' => (float) $unitQuantity->quantity,
                    'unit' => $unitQuantity->unit ? [
                        'id' => $unitQuantity->unit->id,
                        'name' => $unitQuantity->unit->name,
                        'identifier' => $unitQuantity->unit->identifier,
                    ] : null,
                    'container_link' => $linkIndex[$linkKey] ?? null,
                ];
            })->toArray(),
            'low_stock_threshold' => $primaryUnitQuantity?->low_quantity !== null ? (int) $primaryUnitQuantity->low_quantity : null,
            'stock_quantity' => $primaryUnitQuantity?->quantity !== null ? (float) $primaryUnitQuantity->quantity : null,
            'updated_at' => $product->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => null,
        ];
    }

    public function buildLinkIndex(Collection $products): array
    {
        $productIds = $products->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        return $this->containerService->getProductContainerLinksForProducts($productIds)
            ->reduce(function (array $index, array $link) {
                $index[$this->buildLinkKey($link['product_id'] ?? null, $link['unit_quantity_id'] ?? null)] = [
                    'container_type_id' => (int) $link['container_type_id'],
                    'container_type_name' => $link['container_type_name'],
                    'capacity' => (float) $link['capacity'],
                    'capacity_unit' => $link['capacity_unit'],
                    'deposit_fee' => (float) $link['deposit_fee'],
                ];

                return $index;
            }, []);
    }

    private function buildLinkKey(mixed $productId, mixed $unitQuantityId): string
    {
        return (int) $productId . ':' . (int) $unitQuantityId;
    }
}
