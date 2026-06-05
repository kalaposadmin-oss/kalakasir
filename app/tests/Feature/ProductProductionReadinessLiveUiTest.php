<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductProductionReadinessLiveUiTest extends TestCase
{
    public function test_product_forms_expose_live_batch_readiness_controls_and_helpers(): void
    {
        $createContents = file_get_contents(resource_path('views/product-create.blade.php'));
        $editContents = file_get_contents(resource_path('views/product-edit.blade.php'));
        $helpersContents = file_get_contents(resource_path('views/partials/ui-helpers.blade.php'));
        $productionBatchContents = file_get_contents(resource_path('views/production-batches.blade.php'));

        $this->assertStringContainsString('id="is-active"', $createContents);
        $this->assertStringContainsString('id="production-readiness"', $createContents);
        $this->assertStringContainsString('id="production-readiness-summary"', $createContents);
        $this->assertStringContainsString('const buildProductionDraft = () => {', $createContents);
        $this->assertStringContainsString('refreshProductionReadiness()', $createContents);
        $this->assertStringContainsString('el.isActive.addEventListener', $createContents);
        $this->assertStringContainsString('el.bomLineList.addEventListener(\'input\'', $createContents);
        $this->assertStringContainsString('checks.map((check)', $createContents);
        $this->assertStringContainsString('focusInvalidField(el.name, \'main\')', $createContents);
        $this->assertStringNotContainsString('checks.slice(0, 6)', $createContents);
        $this->assertStringNotContainsString("el.recipeOutputQty.value = '1';", $createContents);

        $this->assertStringContainsString('id="edit-is-active"', $editContents);
        $this->assertStringContainsString('id="edit-track-stock"', $editContents);
        $this->assertStringContainsString('id="edit-production-readiness-summary"', $editContents);
        $this->assertStringContainsString('const buildProductionDraft = () => {', $editContents);
        $this->assertStringContainsString('refreshProductionReadiness()', $editContents);
        $this->assertStringContainsString('el.isActive.addEventListener', $editContents);
        $this->assertStringContainsString('el.trackStock.addEventListener', $editContents);
        $this->assertStringContainsString('data-focus-bom-line', $editContents);
        $this->assertStringContainsString('const focusBomLine = (lineNumber) => {', $editContents);
        $this->assertStringContainsString('checks.map((check)', $editContents);
        $this->assertStringNotContainsString('checks.slice(0, 6)', $editContents);
        $this->assertStringNotContainsString("el.recipeOutputQty.value = '1';", $editContents);
        $this->assertStringContainsString("el.recipeProductionMode.value = 'batch_production';", $editContents);
        $this->assertStringContainsString("el.enableBatchOutput.checked = getSelectedRecipeProductionMode() === 'batch_production';", $editContents);
        $this->assertStringContainsString("const productionMode = batchOutputEnabled() ? 'batch_production' : getSelectedRecipeProductionMode();", $editContents);

        $this->assertStringContainsString('const evaluateProductionBatchEligibility = (product = {}) => {', $helpersContents);
        $this->assertStringContainsString('const normalizeProductionBatchDraft = (product = {}) => {', $helpersContents);
        $this->assertStringContainsString("!['raw_material', 'packaging'].includes(productType)", $helpersContents);
        $this->assertStringContainsString('Validitas bahan / packaging', $helpersContents);
        $this->assertStringContainsString('window.PosUi = {', $helpersContents);
        $this->assertStringContainsString('evaluateProductionBatchEligibility,', $helpersContents);

        $this->assertStringContainsString('summary.bom_line_count ?? summary.bom_count', $productionBatchContents);
        $this->assertStringContainsString('product.production_batch_eligibility?.summary?.recipe_output_qty', $productionBatchContents);
        $this->assertStringNotContainsString('&& bomItems.length > 0', $productionBatchContents);
    }
}
