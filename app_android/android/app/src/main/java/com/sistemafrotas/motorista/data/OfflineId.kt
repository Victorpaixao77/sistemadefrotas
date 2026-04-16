package com.sistemafrotas.motorista.data

/** ID negativo exibido no app para registros ainda não sincronizados com o servidor. */
object OfflineId {
    fun fromPendingRow(rowId: Long): Int {
        val p = rowId.coerceIn(1L, 2_147_000_000L)
        return -p.toInt()
    }
}
