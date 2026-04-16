package com.sistemafrotas.motorista.data

import org.junit.Assert.assertEquals
import org.junit.Test

class OutboxPayloadCodecTest {

    @Test
    fun roundTrip_preservesNumbersAndStrings() {
        val original = mapOf<String, Any?>(
            "veiculo_id" to 12,
            "litros" to 45.5,
            "posto" to "Shell",
            "obs" to null,
            "flag" to true,
        )
        val json = OutboxPayloadCodec.mapToJson(original)
        val back = OutboxPayloadCodec.jsonToMap(json)
        assertEquals(12, (back["veiculo_id"] as Number).toInt())
        assertEquals(45.5, (back["litros"] as Number).toDouble(), 0.001)
        assertEquals("Shell", back["posto"])
        assertEquals(null, back["obs"])
        assertEquals(true, back["flag"])
    }
}
