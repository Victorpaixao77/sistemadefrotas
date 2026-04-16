package com.sistemafrotas.motorista.data

import android.content.Context
import android.net.Uri
import com.sistemafrotas.motorista.data.api.*
import com.sistemafrotas.motorista.data.local.LocalCache
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import org.json.JSONObject
import java.io.File

class AuthRepository(
    private val dataStore: AuthDataStore,
    private val localCache: LocalCache? = null,
    private val appContext: Context,
) {

    val isLoggedIn: Flow<Boolean> = dataStore.token.map { !it.isNullOrBlank() }
    val nome: Flow<String?> = dataStore.nome

    suspend fun login(nome: String, senha: String): Result<Unit> {
        return try {
            val res = Api.service.login(LoginRequest(nome.trim(), senha))
            val body = res.body()
            if (res.isSuccessful && body != null && body.success && body.data != null) {
                val d = body.data
                dataStore.saveLogin(d.token, d.motoristaId, d.empresaId, d.nome, d.refreshToken)
                Api.setToken(d.token)
                Result.success(Unit)
            } else {
                val msg = body?.message ?: parseError(res)
                Result.failure(Exception(msg))
            }
        } catch (e: Exception) {
            Result.failure(Exception(NetworkErrors.message(e)))
        }
    }

    suspend fun logout() {
        try {
            Api.service.logout(mapOf("action" to "logout"))
        } catch (_: Exception) { }
        dataStore.clear()
        Api.setToken(null)
    }

    /** Limpa token apenas localmente (útil quando o servidor retorna 401). */
    suspend fun clearTokenLocally() {
        dataStore.clear()
        Api.setToken(null)
    }

    suspend fun restoreToken() {
        val t = dataStore.getToken()
        if (!t.isNullOrBlank()) {
            Api.setToken(t)
        }
    }

    /**
     * Tenta renovar o token usando refresh_token. Usado pelo Authenticator do OkHttp em 401.
     * Retorna success se conseguiu renovar e atualizou o token; failure caso contrário.
     */
    suspend fun refreshToken(): Result<Unit> {
        val refreshToken = dataStore.getRefreshToken() ?: return Result.failure(Exception("Sem refresh token"))
        return try {
            val res = Api.refreshService.refreshToken(mapOf("action" to "refresh", "refresh_token" to refreshToken))
            val body = res.body()
            if (res.isSuccessful && body != null && body.success && body.data != null) {
                val d = body.data
                dataStore.saveLogin(d.token, d.motoristaId, d.empresaId, d.nome, d.refreshToken)
                Api.setToken(d.token)
                Result.success(Unit)
            } else {
                Result.failure(Exception(body?.message ?: "Falha ao renovar token"))
            }
        } catch (e: Exception) {
            Result.failure(Exception(NetworkErrors.message(e)))
        }
    }

    suspend fun loadApiBaseUrl() {
        dataStore.getApiBaseUrl()?.takeIf { it.isNotBlank() }?.let { Api.baseUrl = it }
    }

    suspend fun getApiBaseUrl(): String? = dataStore.getApiBaseUrl()

    suspend fun setApiBaseUrl(url: String) {
        dataStore.setApiBaseUrl(url)
        Api.baseUrl = url
    }

    private suspend fun <T> safeApiCall(call: suspend () -> retrofit2.Response<ApiResponse<T>>): Result<T> {
        return try {
            // Garante que o token esteja carregado antes de qualquer chamada
            if (Api.getToken().isNullOrBlank()) {
                restoreToken()
            }
            val res = call()
            val body = res.body()
            when {
                res.isSuccessful && body != null && body.success && body.data != null ->
                    Result.success(body.data)
                res.code() == 401 -> {
                    val msg = body?.message ?: parseError(res)
                    // Só limpa token se for expirado/inválido; "Token não informado" é problema de envio do header
                    if (!msg.contains("não informado", ignoreCase = true)) {
                        clearTokenLocally()
                    }
                    Result.failure(Exception(msg))
                }
                else ->
                    Result.failure(Exception(body?.message ?: parseError(res)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(NetworkErrors.message(e)))
        }
    }

    suspend fun loadDashboardResult() = safeApiCall { Api.service.dashboard() }

    suspend fun loadMe(): Result<MeData> = safeApiCall { Api.service.me() }
    
    suspend fun loadRotas(
        status: String? = null,
        dataInicio: String? = null,
        dataFim: String? = null,
        limite: Int = 50,
    ): Result<RotasResponse> {
        val result = safeApiCall { Api.service.rotas(status = status, dataInicio = dataInicio, dataFim = dataFim, limite = limite) }
        result.onSuccess { localCache?.saveRotas(it.rotas ?: emptyList()) }
        if (result.isFailure && localCache != null) {
            localCache.loadRotasOfflineMerged()?.let { list -> return Result.success(RotasResponse(list)) }
        }
        return result
    }

    suspend fun loadRotaDetalhe(id: Int): Result<RotaDetalheItem> {
        return try {
            if (Api.getToken().isNullOrBlank()) restoreToken()
            val res = Api.service.rotaDetalhe(id)
            val body = res.body()
            when {
                res.isSuccessful && body != null && body.success && body.data != null ->
                    Result.success(body.data!!.rota)
                res.code() == 401 -> {
                    val msg = body?.message ?: parseError(res)
                    if (!msg.contains("não informado", ignoreCase = true)) clearTokenLocally()
                    Result.failure(Exception(msg))
                }
                else -> Result.failure(Exception(body?.message ?: parseError(res)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(NetworkErrors.message(e)))
        }
    }

    suspend fun atualizarRota(body: Map<String, Any?>): Result<Unit> {
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null) {
            val idInt = (body["id"] as? Number)?.toInt()
                ?: return Result.failure(Exception("Rota sem id."))
            if (idInt < 0) {
                val rowId = -idInt.toLong()
                val fields = body.filterKeys { it != "id" }
                return if (localCache.mergePendingRotaCreatePayload(rowId, fields)) {
                    Result.success(Unit)
                } else {
                    Result.failure(Exception("Rota pendente não encontrada."))
                }
            }
            localCache.enqueuePending(PendingSyncOperation.UPDATE_ROTA, body)
            return Result.success(Unit)
        }
        return try {
            if (Api.getToken().isNullOrBlank()) restoreToken()
            val res = Api.service.atualizarRota(body)
            val b = res.body()
            when {
                res.isSuccessful && b != null && b.success -> Result.success(Unit)
                res.code() == 401 -> {
                    val msg = b?.message ?: parseError(res)
                    if (!msg.contains("não informado", ignoreCase = true)) clearTokenLocally()
                    Result.failure(Exception(msg))
                }
                else -> Result.failure(Exception(b?.message ?: parseError(res)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(NetworkErrors.message(e)))
        }
    }

    suspend fun excluirRota(id: Int): Result<Unit> {
        if (id < 0 && localCache != null) {
            localCache.removePendingSyncRow(-id.toLong())
            return Result.success(Unit)
        }
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null) {
            localCache.enqueuePending(PendingSyncOperation.DELETE_ROTA, mapOf("id" to id))
            localCache.removeRotaFromCache(id)
            return Result.success(Unit)
        }
        return try {
            if (Api.getToken().isNullOrBlank()) restoreToken()
            val res = Api.service.excluirRota(id)
            val b = res.body()
            when {
                res.isSuccessful && b != null && b.success -> Result.success(Unit)
                res.code() == 401 -> {
                    val msg = b?.message ?: parseError(res)
                    if (!msg.contains("não informado", ignoreCase = true)) clearTokenLocally()
                    Result.failure(Exception(msg))
                }
                else -> Result.failure(Exception(b?.message ?: parseError(res)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(NetworkErrors.message(e)))
        }
    }
    
    suspend fun loadAbastecimentos(): Result<AbastecimentosResponse> {
        val result = safeApiCall { Api.service.abastecimentos(limite = 50) }
        result.onSuccess { localCache?.saveAbastecimentos(it.abastecimentos ?: emptyList()) }
        if (result.isFailure && localCache != null) {
            localCache.loadAbastecimentosOfflineMerged()?.let { list -> return Result.success(AbastecimentosResponse(list)) }
        }
        return result
    }
    
    suspend fun loadChecklists(): Result<ChecklistsResponse> {
        val result = safeApiCall { Api.service.checklists(limite = 50) }
        result.onSuccess { localCache?.saveChecklists(it.checklists ?: emptyList()) }
        if (result.isFailure && localCache != null) {
            localCache.loadChecklistsOfflineMerged()?.let { list -> return Result.success(ChecklistsResponse(list)) }
        }
        return result
    }

    suspend fun loadVeiculos(): Result<VeiculosResponse> = safeApiCall { Api.service.veiculos() }

    suspend fun loadEstados(): Result<EstadosResponse> = safeApiCall { Api.service.estados() }

    suspend fun loadCidades(uf: String): Result<CidadesResponse> = safeApiCall { Api.service.cidades(uf) }

    private suspend fun safeCreateCall(call: suspend () -> retrofit2.Response<ApiResponse<IdResponse>>): Result<Int> {
        return try {
            if (Api.getToken().isNullOrBlank()) restoreToken()
            val res = call()
            val body = res.body()
            when {
                res.isSuccessful && body != null && body.success && body.data != null ->
                    Result.success(body.data.id)
                res.code() == 401 -> {
                    val msg = body?.message ?: parseError(res)
                    if (!msg.contains("não informado", ignoreCase = true)) clearTokenLocally()
                    Result.failure(Exception(msg))
                }
                else ->
                    Result.failure(Exception(body?.message ?: parseError(res)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(NetworkErrors.message(e)))
        }
    }

    suspend fun criarRota(body: Map<String, Any?>): Result<Int> {
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null) {
            val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_ROTA, body)
            return Result.success(OfflineId.fromPendingRow(rowId))
        }
        return safeCreateCall { Api.service.criarRota(body) }
    }

    suspend fun criarAbastecimento(
        body: Map<String, Any?>,
        context: Context? = null,
        comprovanteUri: Uri? = null,
    ): Result<Int> {
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null && context != null) {
            return if (comprovanteUri != null) {
                val dir = File(context.filesDir, "outbox_comprovantes").apply { mkdirs() }
                val dest = File(dir, "cmp_${System.currentTimeMillis()}.jpg")
                context.contentResolver.openInputStream(comprovanteUri)?.use { input ->
                    dest.outputStream().use { out -> input.copyTo(out) }
                } ?: return Result.failure(Exception("Não foi possível ler o comprovante."))
                if (!dest.exists() || dest.length() == 0L) {
                    return Result.failure(Exception("Comprovante vazio."))
                }
                val payload = body.toMutableMap()
                payload["comprovante_abs_path"] = dest.absolutePath
                val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_ABASTECIMENTO_MULTIPART, payload)
                Result.success(OfflineId.fromPendingRow(rowId))
            } else {
                val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_ABASTECIMENTO_JSON, body)
                Result.success(OfflineId.fromPendingRow(rowId))
            }
        }
        if (comprovanteUri != null && context != null) {
            val filePart = comprovanteMultipartPart(context, comprovanteUri)
            if (filePart != null) {
                return criarAbastecimentoMultipart(body, filePart)
            }
        }
        return safeCreateCall { Api.service.criarAbastecimento(body) }
    }

    private suspend fun criarAbastecimentoMultipart(
        body: Map<String, Any?>,
        filePart: MultipartBody.Part,
    ): Result<Int> {
        return try {
            if (Api.getToken().isNullOrBlank()) restoreToken()
            val parts = abastecimentoFieldMap(body)
            val res = Api.service.criarAbastecimentoMultipart(parts, filePart)
            val resBody = res.body()
            when {
                res.isSuccessful && resBody != null && resBody.success && resBody.data != null ->
                    Result.success(resBody.data.id)
                res.code() == 401 -> {
                    val msg = resBody?.message ?: parseError(res)
                    if (!msg.contains("não informado", ignoreCase = true)) clearTokenLocally()
                    Result.failure(Exception(msg))
                }
                else ->
                    Result.failure(Exception(resBody?.message ?: parseError(res)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(NetworkErrors.message(e)))
        }
    }

    private fun abastecimentoFieldMap(body: Map<String, Any?>): Map<String, okhttp3.RequestBody> {
        val text = "text/plain; charset=utf-8".toMediaType()
        val map = mutableMapOf<String, okhttp3.RequestBody>()
        for ((k, v) in body) {
            if (v == null) continue
            val s = when (v) {
                is Number -> v.toString()
                is Boolean -> if (v) "1" else "0"
                else -> v.toString()
            }
            map[k] = s.toRequestBody(text)
        }
        return map
    }

    private fun comprovanteMultipartPart(context: Context, uri: Uri): MultipartBody.Part? {
        val cr = context.contentResolver
        val mime = cr.getType(uri) ?: "image/jpeg"
        val ext = when {
            mime.contains("png") -> "png"
            mime.contains("webp") -> "webp"
            mime.contains("pdf") -> "pdf"
            else -> "jpg"
        }
        val stream = cr.openInputStream(uri) ?: return null
        val bytes = stream.use { it.readBytes() }
        if (bytes.isEmpty()) return null
        val body = bytes.toRequestBody(mime.toMediaType())
        return MultipartBody.Part.createFormData("comprovante", "comprovante.$ext", body)
    }

    suspend fun criarChecklist(body: Map<String, Any?>): Result<Int> {
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null) {
            val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_CHECKLIST, body)
            return Result.success(OfflineId.fromPendingRow(rowId))
        }
        return safeCreateCall { Api.service.criarChecklist(body) }
    }

    suspend fun salvarDespesas(body: Map<String, Any?>): Result<Unit> {
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null) {
            localCache.enqueuePending(PendingSyncOperation.SAVE_DESPESAS, body)
            return Result.success(Unit)
        }
        return try {
            if (Api.getToken().isNullOrBlank()) restoreToken()
            val res = Api.service.salvarDespesas(body)
            val b = res.body()
            when {
                res.isSuccessful && b != null && b.success -> Result.success(Unit)
                res.code() == 401 -> {
                    val msg = b?.message ?: parseError(res)
                    if (!msg.contains("não informado", ignoreCase = true)) clearTokenLocally()
                    Result.failure(Exception(msg))
                }
                else -> Result.failure(Exception(b?.message ?: parseError(res)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(NetworkErrors.message(e)))
        }
    }

    suspend fun loadDespesas(rotaId: Int): Result<DespesasWrapper> = safeApiCall { Api.service.despesas(rotaId) }

    private fun parseError(res: retrofit2.Response<*>): String {
        val code = res.code()
        val fromJson = try {
            val json = res.errorBody()?.string()
            if (json != null && json.startsWith("{")) {
                JSONObject(json).optString("message", "")
            } else ""
        } catch (_: Exception) {
            ""
        }
        if (fromJson.isNotBlank()) return fromJson
        return NetworkErrors.httpHint(code, null)
    }
}
