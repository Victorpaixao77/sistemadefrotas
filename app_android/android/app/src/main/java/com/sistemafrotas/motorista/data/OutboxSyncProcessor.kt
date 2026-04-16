package com.sistemafrotas.motorista.data

import android.content.Context
import com.sistemafrotas.motorista.data.api.Api
import com.sistemafrotas.motorista.data.local.DatabaseProvider
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.File

/**
 * Processa um item da fila [pending_sync] (FIFO). Retorna true se enviou e removeu da fila.
 */
object OutboxSyncProcessor {

    private fun resolveRotaIdInPayload(context: Context, payload: Map<String, Any?>): Map<String, Any?> {
        val m = payload.toMutableMap()
        val rotaId = (m["rota_id"] as? Number)?.toInt() ?: return m
        if (rotaId >= 0) return m
        val pendingRowId = -rotaId.toLong()
        val sid = RotaPendingServerIdStore.get(context, pendingRowId)
            ?: throw IllegalStateException("Sincronize a rota antes deste registro.")
        m["rota_id"] = sid
        return m
    }

    suspend fun processNext(context: Context): Boolean = withContext(Dispatchers.IO) {
        val db = DatabaseProvider.get(context)
        val dao = db.pendingSyncDao()
        val row = dao.peekOldest() ?: return@withContext false
        val payload = try {
            OutboxPayloadCodec.jsonToMap(row.payloadJson)
        } catch (_: Exception) {
            dao.deleteById(row.id)
            return@withContext true
        }
        val store = AuthDataStore(context.applicationContext)
        if (Api.getToken().isNullOrBlank()) {
            store.getToken()?.let { Api.setToken(it) }
        }
        if (Api.getToken().isNullOrBlank()) {
            return@withContext false
        }

        try {
            when (row.operation) {
                PendingSyncOperation.CREATE_ROTA -> {
                    val res = Api.service.criarRota(payload)
                    if (!res.isSuccessful || res.body()?.success != true) {
                        throw IllegalStateException(res.body()?.message ?: "HTTP ${res.code()}")
                    }
                    val serverId = res.body()?.data?.id
                    if (serverId != null && serverId > 0) {
                        RotaPendingServerIdStore.put(context.applicationContext, row.id, serverId)
                    }
                }
                PendingSyncOperation.UPDATE_ROTA -> {
                    val res = Api.service.atualizarRota(payload)
                    if (!res.isSuccessful || res.body()?.success != true) {
                        throw IllegalStateException(res.body()?.message ?: "HTTP ${res.code()}")
                    }
                }
                PendingSyncOperation.DELETE_ROTA -> {
                    val id = (payload["id"] as? Number)?.toInt() ?: 0
                    if (id <= 0) throw IllegalArgumentException("id inválido")
                    val res = Api.service.excluirRota(id)
                    if (!res.isSuccessful || res.body()?.success != true) {
                        throw IllegalStateException(res.body()?.message ?: "HTTP ${res.code()}")
                    }
                }
                PendingSyncOperation.CREATE_ABASTECIMENTO_JSON -> {
                    val toSend = resolveRotaIdInPayload(context.applicationContext, payload)
                    val res = Api.service.criarAbastecimento(toSend)
                    if (!res.isSuccessful || res.body()?.success != true) {
                        throw IllegalStateException(res.body()?.message ?: "HTTP ${res.code()}")
                    }
                }
                PendingSyncOperation.CREATE_ABASTECIMENTO_MULTIPART -> {
                    val path = payload["comprovante_abs_path"]?.toString()
                    val file = path?.let { File(it) }
                    if (file == null || !file.exists() || file.length() == 0L) {
                        throw IllegalStateException("Comprovante ausente ou vazio")
                    }
                    val resolved = resolveRotaIdInPayload(context.applicationContext, payload)
                    val bodyMap = resolved.filterKeys { it != "comprovante_abs_path" }
                    val text = "text/plain; charset=utf-8".toMediaType()
                    val parts = bodyMap.mapNotNull { (k, v) ->
                        if (v == null) null
                        else {
                            val s = when (v) {
                                is Number -> v.toString()
                                is Boolean -> if (v) "1" else "0"
                                else -> v.toString()
                            }
                            k to s.toRequestBody(text)
                        }
                    }.toMap()
                    val mime = when {
                        path.endsWith(".png") -> "image/png"
                        path.endsWith(".webp") -> "image/webp"
                        path.endsWith(".pdf") -> "application/pdf"
                        else -> "image/jpeg"
                    }
                    val part = MultipartBody.Part.createFormData(
                        "comprovante",
                        file.name,
                        file.asRequestBody(mime.toMediaType()),
                    )
                    val res = Api.service.criarAbastecimentoMultipart(parts, part)
                    if (!res.isSuccessful || res.body()?.success != true) {
                        throw IllegalStateException(res.body()?.message ?: "HTTP ${res.code()}")
                    }
                    runCatching { file.delete() }
                }
                PendingSyncOperation.CREATE_CHECKLIST -> {
                    val toSend = resolveRotaIdInPayload(context.applicationContext, payload)
                    val res = Api.service.criarChecklist(toSend)
                    if (!res.isSuccessful || res.body()?.success != true) {
                        throw IllegalStateException(res.body()?.message ?: "HTTP ${res.code()}")
                    }
                }
                PendingSyncOperation.SAVE_DESPESAS -> {
                    val toSend = resolveRotaIdInPayload(context.applicationContext, payload)
                    val res = Api.service.salvarDespesas(toSend)
                    if (!res.isSuccessful || res.body()?.success != true) {
                        throw IllegalStateException(res.body()?.message ?: "HTTP ${res.code()}")
                    }
                }
                else -> {
                    dao.deleteById(row.id)
                    return@withContext true
                }
            }
            dao.deleteById(row.id)
            true
        } catch (e: Exception) {
            val msg = e.message?.take(500)
            dao.bumpRetry(row.id, msg)
            if (row.retryCount + 1 >= 15) {
                dao.deleteById(row.id)
            }
            false
        }
    }
}
