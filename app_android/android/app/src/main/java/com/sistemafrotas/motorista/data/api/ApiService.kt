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

    @GET("ocorrencias.php")
    suspend fun ocorrencias(@Query("limite") limite: Int? = null): Response<ApiResponse<OcorrenciasResponse>>

    @POST("ocorrencias.php")
    suspend fun criarOcorrencia(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<IdResponse>>

    @Multipart
    @POST("ocorrencias.php")
    suspend fun criarOcorrenciaMultipart(
        @PartMap parts: Map<String, @JvmSuppressWildcards RequestBody>,
        @Part foto: MultipartBody.Part,
    ): Response<ApiResponse<IdResponse>>

    @POST("evento_auto.php")
    suspend fun eventoAuto(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<IdResponse>>

    @GET("abastecimento_insights.php")
    suspend fun abastecimentoInsights(@Query("dias") dias: Int? = null): Response<ApiResponse<AbastecimentoInsightsResponse>>

    @GET("conferencia_carga.php")
    suspend fun conferencias(@Query("limite") limite: Int? = null): Response<ApiResponse<ConferenciasResponse>>

    @POST("conferencia_carga.php")
    suspend fun criarConferencia(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<IdResponse>>

    @Multipart
    @POST("conferencia_carga.php")
    suspend fun criarConferenciaMultipart(
        @PartMap parts: Map<String, @JvmSuppressWildcards RequestBody>,
        @Part foto: MultipartBody.Part,
    ): Response<ApiResponse<IdResponse>>

    @GET("velocidade.php")
    suspend fun velocidade(
        @Query("dias") dias: Int? = null,
        @Query("limite") limite: Int? = null,
    ): Response<ApiResponse<VelocidadeResponse>>

    @GET("desempenho.php")
    suspend fun desempenho(@Query("dias") dias: Int? = null): Response<ApiResponse<DesempenhoResponse>>

    @Multipart
    @POST("ocr_documento.php")
    suspend fun ocrDocumento(
        @Part("tipo") tipo: RequestBody,
        @Part image: MultipartBody.Part,
    ): Response<ApiResponse<OcrDocumentoResponse>>

    @GET("pedagios.php")
    suspend fun pedagios(@Query("limite") limite: Int? = null): Response<ApiResponse<PedagiosResponse>>

    @POST("pedagios.php")
    suspend fun criarPedagio(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<IdResponse>>

    @Multipart
    @POST("pedagios.php")
    suspend fun criarPedagioMultipart(
        @PartMap parts: Map<String, @JvmSuppressWildcards RequestBody>,
        @Part foto: MultipartBody.Part,
    ): Response<ApiResponse<IdResponse>>

    @GET("jornada.php")
    suspend fun jornada(@Query("limite") limite: Int? = null): Response<ApiResponse<JornadaResponse>>

    @POST("jornada.php")
    suspend fun registrarJornada(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<IdResponse>>

    @GET("panico.php")
    suspend fun panico(@Query("limite") limite: Int? = null): Response<ApiResponse<PanicoResponse>>

    @POST("panico.php")
    suspend fun acionarPanico(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<IdResponse>>

    @GET("diario.php")
    suspend fun diario(
        @Query("dias") dias: Int? = null,
        @Query("limite") limite: Int? = null,
    ): Response<ApiResponse<DiarioResponse>>

    @GET("comprovantes_entrega.php")
    suspend fun comprovantesEntrega(@Query("limite") limite: Int? = null): Response<ApiResponse<ComprovantesResponse>>

    @POST("comprovantes_entrega.php")
    suspend fun criarComprovanteJson(@Body body: Map<String, @JvmSuppressWildcards Any?>): Response<ApiResponse<IdResponse>>

    @Multipart
    @POST("comprovantes_entrega.php")
    suspend fun criarComprovanteMultipart(
        @PartMap parts: Map<String, @JvmSuppressWildcards RequestBody>,
        @Part fotoNf: MultipartBody.Part?,
        @Part fotoCarga: MultipartBody.Part?,
        @Part assinatura: MultipartBody.Part?,
    ): Response<ApiResponse<IdResponse>>

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

    @GET("pneus.php")
    suspend fun buscarPneuQr(
        @Query("action") action: String = "buscar_qr",
        @Query("qr") qr: String,
    ): Response<ApiResponse<PneuBuscaData>>

    @GET("pneus.php")
    suspend fun pneusVeiculosMotorista(
        @Query("action") action: String = "veiculos",
    ): Response<ApiResponse<VeiculosResponse>>

    @POST("pneus.php")
    suspend fun registrarInspecaoPneu(
        @Query("action") action: String = "inspecao",
        @Body body: Map<String, @JvmSuppressWildcards Any?>,
    ): Response<ApiResponse<IdResponse>>

    @Multipart
    @POST("pneus.php")
    suspend fun uploadFotoPneu(
        @Query("action") action: String = "upload_foto",
        @PartMap parts: Map<String, @JvmSuppressWildcards RequestBody>,
        @Part foto: MultipartBody.Part,
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
    val outros: Double?,
    @com.google.gson.annotations.SerializedName("total_despviagem") val totalDespviagem: Double?,
)
