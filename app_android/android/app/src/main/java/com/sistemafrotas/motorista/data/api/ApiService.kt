package com.sistemafrotas.motorista.data.api

import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.PartMap
import retrofit2.http.Query

interface ApiService {

    @POST("auth.php")
    suspend fun login(@Body body: LoginRequest): Response<LoginResponse>

    @GET("auth.php")
    suspend fun me(): Response<ApiResponse<MeData>>

    @POST("auth.php")
    suspend fun logout(@Body body: Map<String, String>): Response<ApiResponse<Unit>>

    /** Refresh token (body: action=refresh, refresh_token=xxx). Se a API não suportar, retorna erro. */
    @POST("auth.php")
    suspend fun refreshToken(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<LoginResponse>

    @GET("dashboard.php")
    suspend fun dashboard(): Response<ApiResponse<DashboardData>>

    @GET("rotas.php")
    suspend fun rotas(
        @Query("status") status: String? = null,
        @Query("data_inicio") dataInicio: String? = null,
        @Query("data_fim") dataFim: String? = null,
        @Query("limite") limite: Int? = null,
    ): Response<ApiResponse<RotasResponse>>

    /** Uma rota com despesas e abastecimentos (GET rotas.php?id=X) */
    @GET("rotas.php")
    suspend fun rotaDetalhe(@Query("id") id: Int): Response<ApiResponse<RotaDetalheResponse>>

    @POST("rotas.php")
    suspend fun criarRota(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<IdResponse>>

    @retrofit2.http.HTTP(method = "PUT", path = "rotas.php", hasBody = true)
    suspend fun atualizarRota(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<IdResponse>>

    @DELETE("rotas.php")
    suspend fun excluirRota(@Query("id") id: Int): Response<ApiResponse<Unit>>

    @GET("abastecimentos.php")
    suspend fun abastecimentos(@Query("limite") limite: Int? = null): Response<ApiResponse<AbastecimentosResponse>>

    @POST("abastecimentos.php")
    suspend fun criarAbastecimento(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<IdResponse>>

    @GET("checklists.php")
    suspend fun checklists(@Query("limite") limite: Int? = null): Response<ApiResponse<ChecklistsResponse>>

    @POST("checklists.php")
    suspend fun criarChecklist(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<IdResponse>>

    @GET("despesas.php")
    suspend fun despesas(@Query("rota_id") rotaId: Int): Response<ApiResponse<DespesasWrapper>>

    @POST("despesas.php")
    suspend fun salvarDespesas(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<Any>>

    @GET("veiculos.php")
    suspend fun veiculos(): Response<ApiResponse<VeiculosResponse>>

    @GET("estados.php")
    suspend fun estados(): Response<ApiResponse<EstadosResponse>>

    @GET("cidades.php")
    suspend fun cidades(@Query("uf") uf: String): Response<ApiResponse<CidadesResponse>>

    /** POST JSON: veiculo_id, latitude, longitude, opcional velocidade (km/h), data_hora */
    @POST("gps_salvar.php")
    suspend fun salvarGps(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<IdResponse>>

    /** POST JSON: { "pontos": [ { ...campos iguais ao gps_salvar... }, ... ] } até 25 itens */
    @POST("gps_salvar_lote.php")
    suspend fun salvarGpsLote(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<GpsLoteResult>>

    @Multipart
    @POST("abastecimentos.php")
    suspend fun criarAbastecimentoMultipart(
        @PartMap parts: Map<String, @JvmSuppressWildcards RequestBody>,
        @Part comprovante: MultipartBody.Part,
    ): Response<ApiResponse<IdResponse>>
}

data class GpsLoteResult(
    val ids: List<Int>?,
    val salvos: Int?,
    val erros: Int?,
    @com.google.gson.annotations.SerializedName("indices_ok") val indicesOk: List<Int>?,
)

data class DespesasWrapper(val despesas: List<DespesaItem>)
data class DespesaItem(
    val id: Int,
    @com.google.gson.annotations.SerializedName("rota_id") val rotaId: Int,
    val descarga: Double?, val pedagios: Double?, val caixinha: Double?,
    val estacionamento: Double?, val lavagem: Double?, val borracharia: Double?,
    @com.google.gson.annotations.SerializedName("eletrica_mecanica") val eletricaMecanica: Double?,
    val adiantamento: Double?,
    @com.google.gson.annotations.SerializedName("total_despviagem") val totalDespviagem: Double?,
)
