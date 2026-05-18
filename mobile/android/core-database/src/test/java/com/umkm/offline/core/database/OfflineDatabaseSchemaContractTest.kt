package com.umkm.offline.core.database

import java.io.File
import org.junit.Assert.assertTrue
import org.junit.Test

class OfflineDatabaseSchemaContractTest {

    @Test
    fun offlineDatabase_registersFifoEntities_andKeepsVersionTen() {
        val schemaFile = File("schemas/com.umkm.offline.core.database.OfflineDatabase/10.json")
        assertTrue(schemaFile.exists())
        val schema = schemaFile.readText()

        assertTrue(schema.contains("\"version\": 10"))
        assertTrue(schema.contains("\"tableName\": \"purchase_return_items\""))
        assertTrue(schema.contains("\"tableName\": \"recipe_items\""))
        assertTrue(schema.contains("\"tableName\": \"inventory_layers_local\""))
        assertTrue(schema.contains("\"tableName\": \"sale_fifo_allocations\""))
    }
}
