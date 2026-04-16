package com.sistemafrotas.motorista.data

import org.junit.Assert.assertEquals
import org.junit.Test

class OfflineIdTest {

    @Test
    fun fromPendingRow_negativeOfRowId() {
        assertEquals(-1, OfflineId.fromPendingRow(1L))
        assertEquals(-42, OfflineId.fromPendingRow(42L))
    }
}
