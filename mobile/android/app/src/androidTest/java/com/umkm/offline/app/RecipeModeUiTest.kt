package com.umkm.offline.app

import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.performClick
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.umkm.offline.feature.pos.presentation.PosProductItemUi
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class RecipeModeUiTest {

    @get:Rule
    val composeRule = createComposeRule()

    @Test
    fun recipe_mode_shows_material_picker_and_candidate_items() {
        composeRule.setContent {
            ProductsScreen(
                products = listOf(
                    PosProductItemUi(localId = "prod-1", name = "Es Kopi Susu", categoryName = "Minuman", price = 22000),
                ),
                recipeMaterialCandidates = listOf(
                    RecipeMaterialCandidateUi(
                        localId = "mat-1",
                        name = "Susu Segar",
                        categoryName = "Bahan Baku",
                        itemType = "raw_material",
                        productMode = "standard",
                    ),
                    RecipeMaterialCandidateUi(
                        localId = "addon-1",
                        name = "Topping Keju",
                        categoryName = "Add On",
                        itemType = "add_on",
                        productMode = "add_on",
                    ),
                ),
                canUseMaterials = true,
                existingCategories = listOf("Minuman", "Bahan Baku"),
                recipeCountByProduct = emptyMap(),
                recipeLinesByProduct = emptyMap(),
                onCreateProduct = {},
                onSaveRecipe = { _, _ -> },
            )
        }

        composeRule.onNodeWithTag("add_product_button", useUnmergedTree = true)
            .performClick()

        composeRule.onNodeWithTag("product_mode_recipe", useUnmergedTree = true)
            .performClick()

        composeRule.onNodeWithTag("recipe_material_picker", useUnmergedTree = true)
            .performClick()

        composeRule.onNodeWithTag("recipe_material_item", useUnmergedTree = true)
            .performClick()

        composeRule.onNodeWithTag("save_product_button", useUnmergedTree = true)
    }
}
