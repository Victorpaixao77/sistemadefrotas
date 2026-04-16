package com.sistemafrotas.motorista.data.api

import org.junit.Assert.assertTrue
import org.junit.Test

class NetworkErrorsTest {

    @Test
    fun httpHint_commonCodes_areReadable() {
        assertTrue(NetworkErrors.httpHint(401, null).contains("login", ignoreCase = true))
        assertTrue(NetworkErrors.httpHint(503, null).contains("indispon", ignoreCase = true))
        assertTrue(NetworkErrors.httpHint(429, null).contains("Aguarde", ignoreCase = true))
    }
}
