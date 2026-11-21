package com.sistemafrotas.motorista.data

import android.content.Context
import android.net.Uri
import com.sistemafrotas.motorista.data.api.*
import com.sistemafrotas.motorista.data.local.LocalCache
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
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

    enum class LoginOutcome { SUCCESS, NEEDS_2FA }

    suspend fun login(nome: String, senha: String, totpCode: String? = null): Result<LoginOutcome> {
        return try {
            val res = Api.service.login(LoginRequest(nome.trim(), senha, totpCode = totpCode))
            val body = res.body()
            if (res.isSuccessful && body != null && body.success && body.data != null) {
                val d = body.data
                if (d.requires2fa == true && d.token.isNullOrBlank()) {
                    return Result.success(LoginOutcome.NEEDS_2FA)
                }
                val token = d.token ?: return Result.failure(Exception(body.message ?: "Token não recebido."))
                dataStore.saveLogin(token, d.motoristaId ?: 0, d.empresaId ?: 0, d.nome ?: nome, d.refreshToken)
                Api.setToken(token)
                Result.success(LoginOutcome.SUCCESS)
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
        } catch (e: Exception) {
            android.util.Log.w("AuthRepository", "logout remoto falhou (ignorado)", e)
        }
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
                val token = d.token ?: return Result.failure(Exception("Token não recebido."))
                dataStore.saveLogin(token, d.motoristaId ?: 0, d.empresaId ?: 0, d.nome ?: "", d.refreshToken)
                Api.setToken(token)
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

    // Cache offline do painel (JSON em arquivo — sem alterar o schema Room).
    private val dashboardCacheFile: File get() = File(appContext.filesDir, "dashboard_cache.json")
    private val cacheGson by lazy { com.google.gson.Gson() }

    private suspend fun saveDashboardCache(data: DashboardData) {
        withContext(Dispatchers.IO) {
            runCatching { dashboardCacheFile.writeText(cacheGson.toJson(data)) }
        }
    }

    private suspend fun loadDashboardCache(): DashboardData? = withContext(Dispatchers.IO) {
        runCatching {
            val f = dashboardCacheFile
            if (f.exists()) cacheGson.fromJson(f.readText(), DashboardData::class.java) else null
        }.getOrNull()
    }

    suspend fun loadDashboardResult(): Result<DashboardData> {
        val result = safeApiCall { Api.service.dashboard() }
        result.onSuccess { saveDashboardCache(it) }
        // Sem internet? Mostra o último painel salvo (leitura), como já é feito em Rotas.
        if (result.isFailure) {
            loadDashboardCache()?.let { return Result.success(it) }
        }
        return result
    }

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

    suspend fun loadOcorrencias(): Result<OcorrenciasResponse> =
        safeApiCall { Api.service.ocorrencias(limite = 50) }

    suspend fun criarOcorrencia(
        body: Map<String, Any?>,
        context: Context? = null,
        fotoUri: Uri? = null,
        videoUri: Uri? = null,
        assinaturaUri: Uri? = null,
    ): Result<Int> {
        val payload = body.toMutableMap()
        assinaturaUri?.let { /* assinatura salva como foto anexa */ }
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null && context != null) {
            val payload = body.toMutableMap()
            fun stash(uri: Uri?, key: String, defaultExt: String) {
                if (uri == null) return
                val dir = File(context.filesDir, "outbox_ocorrencias").apply { mkdirs() }
                val dest = File(dir, "oc_${key}_${System.currentTimeMillis()}.$defaultExt")
                context.contentResolver.openInputStream(uri)?.use { input ->
                    dest.outputStream().use { out -> input.copyTo(out) }
                }
                if (dest.exists() && dest.length() > 0L) payload[key] = dest.absolutePath
            }
            stash(fotoUri, "foto_abs_path", "jpg")
            stash(videoUri, "video_abs_path", "mp4")
            stash(assinaturaUri, "assinatura_abs_path", "png")
            if (payload.containsKey("foto_abs_path") || payload.containsKey("video_abs_path") || payload.containsKey("assinatura_abs_path")) {
                val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_OCORRENCIA_MULTIPART, payload)
                return Result.success(OfflineId.fromPendingRow(rowId))
            }
            val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_OCORRENCIA_JSON, body)
            return Result.success(OfflineId.fromPendingRow(rowId))
        }
        if ((fotoUri != null || videoUri != null || assinaturaUri != null) && context != null) {
            val fotoPart = fotoUri?.let { fotoMultipartPart(context, it, "foto") }
                ?: assinaturaUri?.let { fotoMultipartPart(context, it, "foto") }
            val videoPart = videoUri?.let { videoMultipartPart(context, it, "video") }
            if (fotoPart != null || videoPart != null) {
                return try {
                    if (Api.getToken().isNullOrBlank()) restoreToken()
                    val text = "text/plain; charset=utf-8".toMediaType()
                    val parts = payload.mapNotNull { (k, v) ->
                        if (v == null) null else k to (
                            when (v) {
                                is Number -> v.toString()
                                is Boolean -> if (v) "1" else "0"
                                else -> v.toString()
                            }
                        ).toRequestBody(text)
                    }.toMap()
                    val res = Api.service.criarOcorrenciaMultipart(parts, fotoPart, videoPart)
                    val resBody = res.body()
                    when {
                        res.isSuccessful && resBody != null && resBody.success && resBody.data != null ->
                            Result.success(resBody.data.id)
                        res.code() == 401 -> {
                            val msg = resBody?.message ?: parseError(res)
                            if (!msg.contains("não informado", ignoreCase = true)) clearTokenLocally()
                            Result.failure(Exception(msg))
                        }
                        else -> Result.failure(Exception(resBody?.message ?: parseError(res)))
                    }
                } catch (e: Exception) {
                    Result.failure(Exception(NetworkErrors.message(e)))
                }
            }
        }
        return safeCreateCall { Api.service.criarOcorrencia(payload) }
    }

    suspend fun loadViagemContext(rotaId: Int): Result<ViagemContextData> =
        safeApiCall { Api.service.viagemContext(rotaId) }

    suspend fun syncTripTimeline(events: List<com.sistemafrotas.motorista.feature.viagem.domain.TripEvent>): Result<Int> {
        if (events.isEmpty()) return Result.success(0)
        val fmt = java.text.SimpleDateFormat("yyyy-MM-dd HH:mm:ss", java.util.Locale.US)
        val payload = mapOf(
            "eventos" to events.map { ev ->
                mapOf(
                    "event_uuid" to ev.id,
                    "rota_id" to ev.rotaId,
                    "tipo" to ev.tipo,
                    "titulo" to ev.titulo,
                    "detalhe" to ev.detalhe,
                    "latitude" to ev.lat,
                    "longitude" to ev.lng,
                    "evento_em" to fmt.format(java.util.Date(ev.timestampMs)),
                )
            },
        )
        return try {
            if (Api.getToken().isNullOrBlank()) restoreToken()
            val res = Api.service.syncTripTimeline(payload)
            val body = res.body()
            when {
                res.isSuccessful && body != null && body.success ->
                    Result.success(body.data?.sincronizados ?: 0)
                else -> Result.failure(Exception(body?.message ?: parseError(res)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(NetworkErrors.message(e)))
        }
    }

    suspend fun askMotoristaAssistente(pergunta: String, rotaId: Int? = null): Result<String> =
        safeApiCall {
            val body = mutableMapOf<String, Any?>("pergunta" to pergunta)
            rotaId?.let { body["rota_id"] = it }
            Api.service.motoristaAssistente(body)
        }.map { it.resposta ?: "Sem resposta." }

    suspend fun loadMotoristaDocumentos(): Result<MotoristaDocumentosData> =
        safeApiCall { Api.service.motoristaDocumentos() }

    suspend fun loadGpsTrajeto(horas: Int = 24): Result<GpsTrajetoResponse> =
        safeApiCall { Api.service.gpsTrajeto(horas = horas) }

    private fun videoMultipartPart(context: Context, uri: Uri, field: String): MultipartBody.Part? {
        val cr = context.contentResolver
        val mime = cr.getType(uri) ?: "video/mp4"
        val ext = when {
            mime.contains("3gp") -> "3gp"
            mime.contains("webm") -> "webm"
            else -> "mp4"
        }
        val stream = cr.openInputStream(uri) ?: return null
        val bytes = stream.use { it.readBytes() }
        if (bytes.isEmpty()) return null
        val body = bytes.toRequestBody(mime.toMediaType())
        return MultipartBody.Part.createFormData(field, "video.$ext", body)
    }

    suspend fun loadAbastecimentoInsights(dias: Int = 90): Result<AbastecimentoInsightsResponse> =
        safeApiCall { Api.service.abastecimentoInsights(dias = dias) }

    suspend fun loadAvisos(): Result<AvisosResponse> =
        safeApiCall { Api.service.avisos() }

    suspend fun loadConferencias(): Result<ConferenciasResponse> =
        safeApiCall { Api.service.conferencias(limite = 50) }

    suspend fun criarConferencia(
        body: Map<String, Any?>,
        context: Context? = null,
        fotoUri: Uri? = null,
    ): Result<Int> {
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null && context != null) {
            return if (fotoUri != null) {
                val dir = File(context.filesDir, "outbox_conferencia").apply { mkdirs() }
                val dest = File(dir, "conf_${System.currentTimeMillis()}.jpg")
                context.contentResolver.openInputStream(fotoUri)?.use { input ->
                    dest.outputStream().use { out -> input.copyTo(out) }
                } ?: return Result.failure(Exception("Não foi possível ler a foto."))
                if (!dest.exists() || dest.length() == 0L) {
                    return Result.failure(Exception("Foto vazia."))
                }
                val payload = body.toMutableMap()
                payload["foto_abs_path"] = dest.absolutePath
                val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_CONFERENCIA_MULTIPART, payload)
                Result.success(OfflineId.fromPendingRow(rowId))
            } else {
                val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_CONFERENCIA_JSON, body)
                Result.success(OfflineId.fromPendingRow(rowId))
            }
        }
        if (fotoUri != null && context != null) {
            val filePart = fotoMultipartPart(context, fotoUri, "foto")
            if (filePart != null) {
                return try {
                    if (Api.getToken().isNullOrBlank()) restoreToken()
                    val text = "text/plain; charset=utf-8".toMediaType()
                    val parts = body.mapNotNull { (k, v) ->
                        if (v == null) null else k to (
                            when (v) {
                                is Number -> v.toString()
                                is Boolean -> if (v) "1" else "0"
                                else -> v.toString()
                            }
                        ).toRequestBody(text)
                    }.toMap()
                    val res = Api.service.criarConferenciaMultipart(parts, filePart)
                    val resBody = res.body()
                    when {
                        res.isSuccessful && resBody != null && resBody.success && resBody.data != null ->
                            Result.success(resBody.data.id)
                        res.code() == 401 -> {
                            val msg = resBody?.message ?: parseError(res)
                            if (!msg.contains("não informado", ignoreCase = true)) clearTokenLocally()
                            Result.failure(Exception(msg))
                        }
                        else -> Result.failure(Exception(resBody?.message ?: parseError(res)))
                    }
                } catch (e: Exception) {
                    Result.failure(Exception(NetworkErrors.message(e)))
                }
            }
        }
        return safeCreateCall { Api.service.criarConferencia(body) }
    }

    suspend fun loadVelocidade(dias: Int = 7, limite: Int = 90): Result<VelocidadeResponse> =
        safeApiCall { Api.service.velocidade(dias = dias, limite = limite) }

    suspend fun loadDesempenho(dias: Int = 30): Result<DesempenhoResponse> =
        safeApiCall { Api.service.desempenho(dias = dias) }

    suspend fun loadPedagios(): Result<PedagiosResponse> =
        safeApiCall { Api.service.pedagios(limite = 50) }

    suspend fun criarPedagio(
        body: Map<String, Any?>,
        context: Context? = null,
        fotoUri: Uri? = null,
    ): Result<Int> {
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null && context != null) {
            return if (fotoUri != null) {
                val dir = File(context.filesDir, "outbox_pedagios").apply { mkdirs() }
                val dest = File(dir, "ped_${System.currentTimeMillis()}.jpg")
                context.contentResolver.openInputStream(fotoUri)?.use { input ->
                    dest.outputStream().use { out -> input.copyTo(out) }
                } ?: return Result.failure(Exception("Não foi possível ler a foto."))
                if (!dest.exists() || dest.length() == 0L) {
                    return Result.failure(Exception("Foto vazia."))
                }
                val payload = body.toMutableMap()
                payload["foto_abs_path"] = dest.absolutePath
                val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_PEDAGIO_MULTIPART, payload)
                Result.success(OfflineId.fromPendingRow(rowId))
            } else {
                val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_PEDAGIO_JSON, body)
                Result.success(OfflineId.fromPendingRow(rowId))
            }
        }
        if (fotoUri != null && context != null) {
            val filePart = fotoMultipartPart(context, fotoUri, "foto")
            if (filePart != null) {
                return try {
                    if (Api.getToken().isNullOrBlank()) restoreToken()
                    val text = "text/plain; charset=utf-8".toMediaType()
                    val parts = body.mapNotNull { (k, v) ->
                        if (v == null) null else k to (
                            when (v) {
                                is Number -> v.toString()
                                is Boolean -> if (v) "1" else "0"
                                else -> v.toString()
                            }
                        ).toRequestBody(text)
                    }.toMap()
                    val res = Api.service.criarPedagioMultipart(parts, filePart)
                    val resBody = res.body()
                    when {
                        res.isSuccessful && resBody != null && resBody.success && resBody.data != null ->
                            Result.success(resBody.data.id)
                        res.code() == 401 -> {
                            val msg = resBody?.message ?: parseError(res)
                            if (!msg.contains("não informado", ignoreCase = true)) clearTokenLocally()
                            Result.failure(Exception(msg))
                        }
                        else -> Result.failure(Exception(resBody?.message ?: parseError(res)))
                    }
                } catch (e: Exception) {
                    Result.failure(Exception(NetworkErrors.message(e)))
                }
            }
        }
        return safeCreateCall { Api.service.criarPedagio(body) }
    }

    /**
     * Lê um documento por IA de visão (tipo = abastecimento|painel|pedagio) e devolve os
     * campos para o app pré-preencher o formulário. Exige internet; o motorista revisa antes de salvar.
     */
    suspend fun lerDocumentoOcr(context: Context, fotoUri: Uri, tipo: String): Result<OcrCampos> =
        lerDocumentoOcrCompleto(context, fotoUri, tipo).map { it.campos }

    /** OCR com tipo automático (servidor classifica: abastecimento, pedágio, nota, etc.). */
    suspend fun lerDocumentoOcrCompleto(
        context: Context,
        fotoUri: Uri,
        tipo: String = "auto",
    ): Result<OcrLeituraResult> {
        if (!NetworkConnectivity.isOnline(appContext)) {
            return Result.failure(Exception("Leitura por IA precisa de internet. Preencha manualmente."))
        }
        val filePart = fotoMultipartPart(context, fotoUri, "image")
            ?: return Result.failure(Exception("Não foi possível ler a foto."))
        return try {
            if (Api.getToken().isNullOrBlank()) restoreToken()
            val tipoBody = tipo.toRequestBody("text/plain; charset=utf-8".toMediaType())
            val res = Api.service.ocrDocumento(tipoBody, filePart)
            val resBody = res.body()
            when {
                res.isSuccessful && resBody != null && resBody.success && resBody.data != null -> {
                    val t = resBody.data.tipo ?: tipo
                    val campos = resBody.data.campos ?: OcrCampos()
                    Result.success(OcrLeituraResult(tipo = t, campos = campos))
                }
                res.code() == 401 -> {
                    val msg = resBody?.message ?: parseError(res)
                    if (!msg.contains("não informado", ignoreCase = true)) clearTokenLocally()
                    Result.failure(Exception(msg))
                }
                else -> Result.failure(Exception(resBody?.message ?: parseError(res)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(NetworkErrors.message(e)))
        }
    }

    suspend fun loadJornada(): Result<JornadaResponse> =
        safeApiCall { Api.service.jornada(limite = 50) }

    suspend fun registrarJornada(body: Map<String, Any?>): Result<Int> {
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null) {
            val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_JORNADA, body)
            return Result.success(OfflineId.fromPendingRow(rowId))
        }
        return safeCreateCall { Api.service.registrarJornada(body) }
    }

    suspend fun loadPanicoAlertas(): Result<PanicoResponse> =
        safeApiCall { Api.service.panico(limite = 20) }

    suspend fun acionarPanico(body: Map<String, Any?>): Result<Int> {
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null) {
            val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_PANICO, body)
            return Result.success(OfflineId.fromPendingRow(rowId))
        }
        return safeCreateCall { Api.service.acionarPanico(body) }
    }

    suspend fun loadComprovantes(): Result<ComprovantesResponse> =
        safeApiCall { Api.service.comprovantesEntrega(limite = 50) }

    suspend fun loadDiario(): Result<DiarioResponse> =
        safeApiCall { Api.service.diario(dias = 7, limite = 80) }

    suspend fun criarComprovante(
        body: Map<String, Any?>,
        context: Context,
        fotoNf: Uri?,
        fotoCarga: Uri?,
        assinatura: Uri?,
    ): Result<Int> {
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null) {
            val payload = body.toMutableMap()
            fun stash(uri: Uri?, key: String, prefix: String) {
                if (uri == null) return
                val dir = File(context.filesDir, "outbox_pod").apply { mkdirs() }
                val dest = File(dir, "${prefix}_${System.currentTimeMillis()}.jpg")
                context.contentResolver.openInputStream(uri)?.use { input ->
                    dest.outputStream().use { out -> input.copyTo(out) }
                }
                if (dest.exists() && dest.length() > 0L) payload[key] = dest.absolutePath
            }
            stash(fotoNf, "foto_nf_abs_path", "nf")
            stash(fotoCarga, "foto_carga_abs_path", "carga")
            stash(assinatura, "assinatura_abs_path", "assin")
            val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_POD, payload)
            return Result.success(OfflineId.fromPendingRow(rowId))
        }
        return try {
            if (Api.getToken().isNullOrBlank()) restoreToken()
            val text = "text/plain; charset=utf-8".toMediaType()
            val parts = body.mapNotNull { (k, v) ->
                if (v == null) null else k to (
                    when (v) {
                        is Number -> v.toString()
                        is Boolean -> if (v) "1" else "0"
                        else -> v.toString()
                    }
                ).toRequestBody(text)
            }.toMap()
            val pNf = fotoNf?.let { fotoMultipartPart(context, it, "foto_nf") }
            val pCarga = fotoCarga?.let { fotoMultipartPart(context, it, "foto_carga") }
            val pAssin = assinatura?.let { fotoMultipartPart(context, it, "assinatura") }
            val res = Api.service.criarComprovanteMultipart(parts, pNf, pCarga, pAssin)
            val resBody = res.body()
            when {
                res.isSuccessful && resBody != null && resBody.success && resBody.data != null ->
                    Result.success(resBody.data.id)
                res.code() == 401 -> {
                    val msg = resBody?.message ?: parseError(res)
                    if (!msg.contains("não informado", ignoreCase = true)) clearTokenLocally()
                    Result.failure(Exception(msg))
                }
                else -> Result.failure(Exception(resBody?.message ?: parseError(res)))
            }
        } catch (e: Exception) {
            Result.failure(Exception(NetworkErrors.message(e)))
        }
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

    suspend fun criarChecklist(
        body: Map<String, Any?>,
        context: Context? = null,
        assinaturaUri: Uri? = null,
    ): Result<Int> {
        if (!NetworkConnectivity.isOnline(appContext) && localCache != null) {
            val payload = body.toMutableMap()
            if (context != null && assinaturaUri != null) {
                val dir = File(context.filesDir, "outbox_checklists").apply { mkdirs() }
                val dest = File(dir, "assin_${System.currentTimeMillis()}.png")
                context.contentResolver.openInputStream(assinaturaUri)?.use { input ->
                    dest.outputStream().use { out -> input.copyTo(out) }
                }
                if (dest.exists() && dest.length() > 0L) {
                    payload["assinatura_abs_path"] = dest.absolutePath
                    val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_CHECKLIST_MULTIPART, payload)
                    return Result.success(OfflineId.fromPendingRow(rowId))
                }
            }
            val rowId = localCache.enqueuePending(PendingSyncOperation.CREATE_CHECKLIST, body)
            return Result.success(OfflineId.fromPendingRow(rowId))
        }
        if (assinaturaUri != null && context != null) {
            return try {
                if (Api.getToken().isNullOrBlank()) restoreToken()
                val text = "text/plain; charset=utf-8".toMediaType()
                val parts = body.mapNotNull { (k, v) ->
                    if (v == null) null else k to (
                        when (v) {
                            is Number -> v.toString()
                            is Boolean -> if (v) "1" else "0"
                            else -> v.toString()
                        }
                    ).toRequestBody(text)
                }.toMap()
                val assinPart = fotoMultipartPart(context, assinaturaUri, "assinatura")
                val res = Api.service.criarChecklistMultipart(parts, assinPart)
                val resBody = res.body()
                when {
                    res.isSuccessful && resBody != null && resBody.success && resBody.data != null ->
                        Result.success(resBody.data.id)
                    res.code() == 401 -> {
                        val msg = resBody?.message ?: parseError(res)
                        if (!msg.contains("não informado", ignoreCase = true)) clearTokenLocally()
                        Result.failure(Exception(msg))
                    }
                    else -> Result.failure(Exception(resBody?.message ?: parseError(res)))
                }
            } catch (e: Exception) {
                Result.failure(Exception(NetworkErrors.message(e)))
            }
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

    suspend fun buscarPneuQr(qr: String): Result<PneuBuscaData> =
        safeApiCall { Api.service.buscarPneuQr(qr = qr.trim()) }

    suspend fun loadPneusVeiculos(): Result<VeiculosResponse> =
        safeApiCall { Api.service.pneusVeiculosMotorista() }

    suspend fun registrarInspecaoPneu(body: Map<String, Any?>): Result<Int> =
        safeCreateCall { Api.service.registrarInspecaoPneu(body = body) }

    suspend fun uploadFotoPneu(
        pneuId: Int,
        tipo: String,
        context: Context,
        uri: Uri,
    ): Result<Int> {
        val filePart = fotoMultipartPart(context, uri, "foto") ?: return Result.failure(Exception("Não foi possível ler a foto."))
        val text = "text/plain; charset=utf-8".toMediaType()
        val parts = mapOf(
            "pneu_id" to pneuId.toString().toRequestBody(text),
            "tipo" to tipo.toRequestBody(text),
        )
        return try {
            if (Api.getToken().isNullOrBlank()) restoreToken()
            val res = Api.service.uploadFotoPneu(parts = parts, foto = filePart)
            val body = res.body()
            when {
                res.isSuccessful && body != null && body.success && body.data != null ->
                    Result.success(body.data.id)
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

    private fun fotoMultipartPart(context: Context, uri: Uri, field: String): MultipartBody.Part? {
        val cr = context.contentResolver
        val mime = cr.getType(uri) ?: "image/jpeg"
        val ext = when {
            mime.contains("png") -> "png"
            mime.contains("webp") -> "webp"
            else -> "jpg"
        }
        val stream = cr.openInputStream(uri) ?: return null
        val bytes = stream.use { it.readBytes() }
        if (bytes.isEmpty()) return null
        val body = bytes.toRequestBody(mime.toMediaType())
        return MultipartBody.Part.createFormData(field, "foto.$ext", body)
    }

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
