package com.sistemafrotas.motorista.data

import android.content.Context

/**
 * Mapeia o id da linha em [pending_sync] de um CREATE_ROTA para o id da rota no servidor,
 * para reescrever [rota_id] negativo em abastecimentos/checklists antes do envio.
 */
object RotaPendingServerIdStore {

    private const val PREF = "rota_pending_server_id"

    fun put(context: Context, pendingSyncRowId: Long, serverRotaId: Int) {
        if (serverRotaId <= 0) return
        context.applicationContext.getSharedPreferences(PREF, Context.MODE_PRIVATE)
            .edit()
            .putInt(key(pendingSyncRowId), serverRotaId)
            .apply()
    }

    fun get(context: Context, pendingSyncRowId: Long): Int? {
        val p = context.applicationContext.getSharedPreferences(PREF, Context.MODE_PRIVATE)
        val k = key(pendingSyncRowId)
        if (!p.contains(k)) return null
        val v = p.getInt(k, 0)
        return v.takeIf { it > 0 }
    }

    private fun key(pendingSyncRowId: Long) = "p_$pendingSyncRowId"
}
