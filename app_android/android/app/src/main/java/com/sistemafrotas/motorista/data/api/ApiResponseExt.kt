package com.sistemafrotas.motorista.data.api

import org.json.JSONObject
import retrofit2.Response

/**
 * Em HTTP 4xx/5xx o Retrofit costuma deixar [Response.body] nulo; a mensagem útil vem no JSON do errorBody
 * (ex.: `{"success":false,"message":"Coordenadas recusadas: ..."}`).
 */
fun <T> Response<T>.readApiMessage(): String {
    when (val b = body()) {
        is ApiResponse<*> -> b.message?.takeIf { it.isNotBlank() }?.let { return it }
    }
    val raw = errorBody()?.use { it.string() }?.trim().orEmpty()
    if (raw.isEmpty()) return ""
    return try {
        JSONObject(raw).optString("message", "").takeIf { it.isNotBlank() } ?: raw.take(400)
    } catch (_: Exception) {
        raw.take(400)
    }
}
