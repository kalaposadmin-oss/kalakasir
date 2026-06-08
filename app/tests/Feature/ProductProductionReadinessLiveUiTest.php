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
        $productionBatchDetailContents = file_get_contents(resource_path('views/production-batch-detail.blade.php'));
        $posKasirCoreContents = file_get_contents(public_path('js/pos-kasir-page-core.js'));

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
        $this->assertStringContainsString('id="edit-enable-tiers"', $editContents);
        $this->assertStringContainsString('id="edit-tier-list"', $editContents);
        $this->assertStringContainsString('const collectTiers = () => {', $editContents);
        $this->assertStringContainsString('payload.price_tiers = collectTiers();', $editContents);

        $this->assertStringContainsString('const evaluateProductionBatchEligibility = (product = {}) => {', $helpersContents);
        $this->assertStringContainsString('const normalizeProductionBatchDraft = (product = {}) => {', $helpersContents);
        $this->assertStringContainsString("!['raw_material', 'packaging'].includes(productType)", $helpersContents);
        $this->assertStringContainsString('Validitas bahan / packaging', $helpersContents);
        $this->assertStringContainsString('window.PosUi = {', $helpersContents);
        $this->assertStringContainsString('evaluateProductionBatchEligibility,', $helpersContents);

        $this->assertStringContainsString('summary.bom_line_count ?? summary.bom_count', $productionBatchContents);
        $this->assertStringContainsString('product.production_batch_eligibility?.summary?.recipe_output_qty', $productionBatchContents);
        $this->assertStringNotContainsString('&& bomItems.length > 0', $productionBatchContents);
        $this->assertStringContainsString('const autoAllocateOutputRecipeFactors = (syncDom = false) => {', $productionBatchContents);
        $this->assertStringContainsString('const outputRecipeFactorForRow = (row) => {', $productionBatchContents);
        $this->assertStringContainsString('productRecipeOutputQty(outputProduct) || productRecipeOutputQty(recipeProduct)', $productionBatchContents);
        $this->assertStringContainsString('const normalizeFactorValue = (value) => {', $productionBatchContents);
        $this->assertStringContainsString('normalizeFactorValue(1 / standardOutputQty)', $productionBatchContents);
        $this->assertStringContainsString('const outputRecipeUsageForRow = (row) => {', $productionBatchContents);
        $this->assertStringContainsString('Number(row.planned_qty || 0) / standardOutputQty', $productionBatchContents);
        $this->assertStringContainsString('data-output-field="recipe_usage_display" readonly', $productionBatchContents);
        $this->assertStringContainsString('type="hidden" value="${row.recipe_factor || \'\'}" data-output-field="recipe_factor"', $productionBatchContents);
        $this->assertStringContainsString('const outputAdvancedIsActive = () => {', $productionBatchContents);
        $this->assertStringContainsString('if (!outputAdvancedIsActive()) {', $productionBatchContents);
        $this->assertStringContainsString('const includeAdvancedOutputs = outputAdvancedIsActive();', $productionBatchContents);
        $this->assertStringContainsString('if (includeAdvancedOutputs) {', $productionBatchContents);
        $this->assertStringContainsString('Produk hasil duplikat. Gabungkan qty pada satu baris', $productionBatchContents);
        $this->assertStringContainsString('const outputAdditionalRecipeSummary = () => {', $productionBatchContents);
        $this->assertStringContainsString('BOM output tambahan:', $productionBatchContents);
        $this->assertStringNotContainsString('el.createSubmit.disabled = !canPost;', $productionBatchContents);
        $this->assertStringContainsString("el.createSubmit.setAttribute('aria-disabled'", $productionBatchContents);
        $this->assertStringContainsString('el.createFeedback?.scrollIntoView', $productionBatchContents);

        $this->assertStringContainsString('batch-post-variance-reason-presets', $productionBatchDetailContents);
        $this->assertStringContainsString('data-variance-reason="Salah input - koreksi draft"', $productionBatchDetailContents);
        $this->assertStringContainsString('const varianceState = () => {', $productionBatchDetailContents);
        $this->assertStringContainsString('reasonIndicatesInputMistake', $productionBatchDetailContents);
        $this->assertStringContainsString('Selisih hasil terlalu besar dan kemungkinan salah input', $productionBatchDetailContents);
        $this->assertStringContainsString('bottom-0 top-4', $productionBatchDetailContents);
        $this->assertStringContainsString('lg:inset-y-8', $productionBatchDetailContents);
        $this->assertStringContainsString('overflow-y-auto overscroll-contain', $productionBatchDetailContents);
        $this->assertStringContainsString('sticky bottom-0', $productionBatchDetailContents);
        $this->assertStringContainsString('batch-detail-posted-actions', $productionBatchDetailContents);
        $this->assertStringContainsString('batch-detail-correct-date-modal', $productionBatchDetailContents);
        $this->assertStringContainsString('batch-detail-void-posting-modal', $productionBatchDetailContents);
        $this->assertStringContainsString('batch-detail-waste-open', $productionBatchDetailContents);
        $this->assertStringContainsString('batch-detail-waste-modal', $productionBatchDetailContents);
        $this->assertStringContainsString('batch-waste-product', $productionBatchDetailContents);
        $this->assertStringContainsString('/correct-date', $productionBatchDetailContents);
        $this->assertStringContainsString('/void-posting', $productionBatchDetailContents);
        $this->assertStringContainsString('/waste', $productionBatchDetailContents);

        $this->assertStringContainsString('const resolveUnitPrice = (product, qty) => {', $posKasirCoreContents);
        $this->assertStringContainsString('const activePriceTier = (product, qty) => {', $posKasirCoreContents);
        $this->assertStringContainsString('price_tiers', $posKasirCoreContents);
    }
}
