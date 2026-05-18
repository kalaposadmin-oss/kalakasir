package com.umkm.offline.app

import androidx.compose.ui.test.SemanticsMatcher
import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.junit4.AndroidComposeTestRule

internal fun AndroidComposeTestRule<*, *>.assertNodeDisplayedEventually(
    matcher: SemanticsMatcher,
    timeoutMillis: Long = 20_000,
    useUnmergedTree: Boolean = true,
) {
    waitUntil(timeoutMillis = timeoutMillis) {
        onAllNodes(matcher, useUnmergedTree = useUnmergedTree)
            .fetchSemanticsNodes().isNotEmpty()
    }
    onNode(matcher, useUnmergedTree = useUnmergedTree).assertIsDisplayed()
}
