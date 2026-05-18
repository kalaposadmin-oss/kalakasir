package com.umkm.offline.core.database

import java.io.File
import org.junit.Assert.assertTrue
import org.junit.Test

class OfflineDatabaseSchemaMigrationContractTest {

    @Test
    fun schema_v6_to_v7_addsRecipeItems_withoutDroppingCorePurchaseTables() {
        val schemaV6 = File("schemas/com.umkm.offline.core.database.OfflineDatabase/6.json")
        val schemaV7 = File("schemas/com.umkm.offline.core.database.OfflineDatabase/7.json")

        assertTrue(schemaV6.exists())
        assertTrue(schemaV7.exists())

        val v6 = schemaV6.readText()
        val v7 = schemaV7.readText()

        assertTrue(v6.contains("\"version\": 6"))
        assertTrue(v7.contains("\"version\": 7"))

        assertTrue(v6.contains("\"tableName\": \"purchases\""))
        assertTrue(v6.contains("\"tableName\": \"purchase_items\""))
        assertTrue(v7.contains("\"tableName\": \"purchases\""))
        assertTrue(v7.contains("\"tableName\": \"purchase_items\""))

        assertTrue(v6.contains("\"tableName\": \"purchase_return_items\""))
        assertTrue(!v6.contains("\"tableName\": \"recipe_items\""))
        assertTrue(v7.contains("\"tableName\": \"recipe_items\""))
    }
}
