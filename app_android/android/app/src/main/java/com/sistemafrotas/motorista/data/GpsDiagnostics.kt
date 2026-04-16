package com.sistemafrotas.motorista.data

import android.content.Context
import android.content.SharedPreferences

/**
 * Último resultado do envio GPS (para tela de diagnóstico).
 */
object GpsDiagnostics {
    private const val PREFS = "gps_diagnostics"
    private const val K_TIME = "last_time_ms"
    private const val K_LAT = "last_lat"
    private const val K_LNG = "last_lng"
    private const val K_OK = "last_ok"
    private const val K_DETAIL = "last_detail"

    private fun prefs(ctx: Context): SharedPreferences =
        ctx.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun recordSend(ctx: Context, lat: Double, lng: Double, ok: Boolean, detail: String) {
        prefs(ctx).edit()
            .putLong(K_TIME, System.currentTimeMillis())
            .putString(K_LAT, String.format(java.util.Locale.US, "%.6f", lat))
            .putString(K_LNG, String.format(java.util.Locale.US, "%.6f", lng))
            .putBoolean(K_OK, ok)
            .putString(K_DETAIL, detail.take(500))
            .apply()
    }

    fun formatLines(ctx: Context, apiBaseUrl: String, pendingCount: Int): String {
        val p = prefs(ctx)
        val t = p.getLong(K_TIME, 0L)
        val timeStr = if (t > 0) {
            java.text.SimpleDateFormat("dd/MM/yyyy HH:mm:ss", java.util.Locale("pt", "BR"))
                .format(java.util.Date(t))
        } else {
            "—"
        }
        val lat = p.getString(K_LAT, "—") ?: "—"
        val lng = p.getString(K_LNG, "—") ?: "—"
        val ok = p.getBoolean(K_OK, false)
        val st = if (t == 0L) "—" else if (ok) "OK" else "Falha"
        val det = p.getString(K_DETAIL, "")?.ifBlank { "—" } ?: "—"
        return buildString {
            appendLine("URL da API: $apiBaseUrl")
            appendLine("Último envio: $timeStr")
            appendLine("Status: $st")
            appendLine("Coordenadas: $lat , $lng")
            appendLine("Detalhe: $det")
            appendLine("Pontos na fila offline: $pendingCount")
        }
    }
}
