package com.sistemafrotas.motorista.data.api

import com.google.gson.annotations.SerializedName

// Auth
/** action=login explícito para o PHP (corpo JSON). */
data class LoginRequest(
    val nome: String,
    val senha: String,
    val action: String = "login",
    @SerializedName("totp_code") val totpCode: String? = null,
)
data class LoginResponse(
    val success: Boolean,
    val message: String?,
    val data: LoginData?,
)
data class LoginData(
    val token: String? = null,
    @SerializedName("motorista_id") val motoristaId: Int? = null,
    @SerializedName("empresa_id") val empresaId: Int? = null,
    val nome: String? = null,
    @SerializedName("expira_em") val expiraEm: String? = null,
    @SerializedName("refresh_token") val refreshToken: String? = null,
    @SerializedName("requires_2fa") val requires2fa: Boolean? = null,
)

data class ApiResponse<T>(
    val success: Boolean,
    val message: String?,
    val data: T?,
)

data class MeData(
    @SerializedName("motorista_id") val motoristaId: Int,
    @SerializedName("empresa_id") val empresaId: Int,
    val nome: String,
    @SerializedName("porcentagem_comissao") val porcentagemComissao: Double? = null,
)

// Dashboard
data class DashboardData(
    val contadores: Contadores?,
    @SerializedName("gps_resumo") val gpsResumo: GpsResumoMotorista? = null,
    @SerializedName("resumo_mes") val resumoMes: ResumoMes?,
    @SerializedName("rotas_hoje") val rotasHoje: List<RotaItem>?,
    @SerializedName("ultimas_rotas") val ultimasRotas: List<RotaItem>?,
    @SerializedName("ultimos_abastecimentos") val ultimosAbastecimentos: List<AbastecimentoItem>?,
    @SerializedName("ultimos_checklists") val ultimosChecklists: List<ChecklistItem>?,
)
data class ResumoMes(
    @SerializedName("total_frete_mes") val totalFreteMes: Double,
    @SerializedName("total_comissao_mes") val totalComissaoMes: Double,
    @SerializedName("total_despesas_mes") val totalDespesasMes: Double,
    @SerializedName("lucro_mes") val lucroMes: Double,
)
data class Contadores(
    @SerializedName("rotas_pendentes") val rotasPendentes: Int,
    @SerializedName("abastecimentos_pendentes") val abastecimentosPendentes: Int,
    @SerializedName("checklists_pendentes") val checklistsPendentes: Int,
)

data class GpsResumoMotorista(
    @SerializedName("pontos_ultimas_24h") val pontosUltimas24h: Int? = null,
    @SerializedName("ultimo_registro") val ultimoRegistro: GpsUltimoRegistro? = null,
)

data class GpsUltimoRegistro(
    val latitude: Double?,
    val longitude: Double?,
    @SerializedName("data_hora") val dataHora: String?,
    @SerializedName("veiculo_id") val veiculoId: Int?,
    val placa: String?,
)

data class RotaItem(
    val id: Int,
    @SerializedName("veiculo_id") val veiculoId: Int?,
    @SerializedName("data_rota") val dataRota: String?,
    @SerializedName("data_saida") val dataSaida: String?,
    @SerializedName("cidade_origem_nome") val cidadeOrigemNome: String?,
    @SerializedName("cidade_destino_nome") val cidadeDestinoNome: String?,
    val placa: String?,
    val status: String?,
    @SerializedName("distancia_km") val distanciaKm: Double?,
)

data class AbastecimentoItem(
    val id: Int,
    @SerializedName("veiculo_id") val veiculoId: Int?,
    @SerializedName("data_abastecimento") val dataAbastecimento: String?,
    val placa: String?,
    val litros: Double?,
    @SerializedName("valor_total") val valorTotal: Double?,
    val status: String?,
)

data class ChecklistItem(
    val id: Int,
    @SerializedName("rota_id") val rotaId: Int?,
    @SerializedName("veiculo_id") val veiculoId: Int?,
    @SerializedName("data_checklist") val dataChecklist: String?,
    val placa: String?,
    @SerializedName("cidade_origem_nome") val cidadeOrigemNome: String?,
    @SerializedName("cidade_destino_nome") val cidadeDestinoNome: String?,
)

// Lists
data class RotasResponse(val rotas: List<RotaItem>)

/** Resposta de GET rotas.php?id=X: uma rota com despesas e abastecimentos */
data class RotaDetalheResponse(
    val rota: RotaDetalheItem,
)
data class RotaDetalheItem(
    val id: Int,
    @SerializedName("veiculo_id") val veiculoId: Int?,
    @SerializedName("cidade_origem_id") val cidadeOrigemId: Int?,
    @SerializedName("cidade_destino_id") val cidadeDestinoId: Int?,
    @SerializedName("estado_origem") val estadoOrigem: String?,
    @SerializedName("estado_destino") val estadoDestino: String?,
    @SerializedName("data_rota") val dataRota: String?,
    @SerializedName("data_saida") val dataSaida: String?,
    @SerializedName("data_chegada") val dataChegada: String?,
    @SerializedName("cidade_origem_nome") val cidadeOrigemNome: String?,
    @SerializedName("cidade_destino_nome") val cidadeDestinoNome: String?,
    val placa: String?,
    val modelo: String?,
    val status: String?,
    @SerializedName("distancia_km") val distanciaKm: Double?,
    @SerializedName("km_saida") val kmSaida: Double?,
    @SerializedName("km_chegada") val kmChegada: Double?,
    @SerializedName("km_vazio") val kmVazio: Double?,
    @SerializedName("total_km") val totalKm: Double?,
    @SerializedName("percentual_vazio") val percentualVazio: Double?,
    @SerializedName("eficiencia_viagem") val eficienciaViagem: Double?,
    val frete: Double?,
    val comissao: Double?,
    @SerializedName("no_prazo") val noPrazo: Int?,
    @SerializedName("peso_carga") val pesoCarga: Double?,
    @SerializedName("descricao_carga") val descricaoCarga: String?,
    val observacoes: String?,
    val despesas: List<DespesaItem>? = null,
    val abastecimentos: List<AbastecimentoDetalheItem>? = null,
)
data class AbastecimentoDetalheItem(
    val id: Int,
    @SerializedName("rota_id") val rotaId: Int?,
    @SerializedName("data_abastecimento") val dataAbastecimento: String?,
    val litros: Double?,
    @SerializedName("valor_total") val valorTotal: Double?,
    val posto: String?,
    val placa: String?,
)
data class AbastecimentosResponse(val abastecimentos: List<AbastecimentoItem>)

// Ocorrências de viagem
data class OcorrenciaItem(
    val id: Int,
    val tipo: String?,
    val descricao: String?,
    @SerializedName("data_hora") val dataHora: String?,
    val placa: String?,
    @SerializedName("rota_id") val rotaId: Int?,
    @SerializedName("veiculo_id") val veiculoId: Int?,
    val latitude: Double?,
    val longitude: Double?,
    @SerializedName("foto_path") val fotoPath: String?,
    val status: String?,
)
data class OcorrenciasResponse(val ocorrencias: List<OcorrenciaItem>)

// Jornada do motorista (Lei do Motorista)
data class JornadaEvento(
    val id: Int,
    val tipo: String?,
    @SerializedName("data_hora") val dataHora: String?,
    @SerializedName("rota_id") val rotaId: Int?,
    val observacoes: String?,
)
data class JornadaResumoHoje(
    @SerializedName("dirigindo_min") val dirigindoMin: Int = 0,
    @SerializedName("descanso_min") val descansoMin: Int = 0,
    @SerializedName("estado_atual") val estadoAtual: String? = null,
)
data class JornadaResponse(
    val eventos: List<JornadaEvento>,
    @SerializedName("resumo_hoje") val resumoHoje: JornadaResumoHoje?,
)

// Botão de pânico
data class PanicoAlerta(
    val id: Int,
    val latitude: Double?,
    val longitude: Double?,
    @SerializedName("data_hora") val dataHora: String?,
    val status: String?,
)
data class PanicoResponse(val alertas: List<PanicoAlerta>)

// Comprovante de entrega (POD)
data class ComprovanteEntrega(
    val id: Int,
    @SerializedName("rota_id") val rotaId: Int?,
    val placa: String?,
    @SerializedName("recebedor_nome") val recebedorNome: String?,
    @SerializedName("data_hora") val dataHora: String?,
    val status: String?,
)
data class ComprovantesResponse(val comprovantes: List<ComprovanteEntrega>)

// Diário de bordo (linha do tempo)
data class DiarioEvento(
    val tipo: String?,
    val titulo: String?,
    val descricao: String?,
    @SerializedName("data_hora") val dataHora: String?,
)
data class DiarioResponse(val eventos: List<DiarioEvento>)

// Controle de pedágios
data class PedagioItem(
    val id: Int,
    @SerializedName("rota_id") val rotaId: Int?,
    @SerializedName("veiculo_id") val veiculoId: Int?,
    val placa: String?,
    val praca: String?,
    val valor: String?,
    @SerializedName("data_hora") val dataHora: String?,
    @SerializedName("foto_path") val fotoPath: String?,
)
data class PedagiosResponse(
    val pedagios: List<PedagioItem>,
    @SerializedName("total_30d") val total30d: Double = 0.0,
)

// Avisos do motorista (polling)
data class AvisoItem(
    val chave: String,
    val tipo: String?,
    val titulo: String?,
    val descricao: String?,
    val data: String?,
)
data class AvisosResponse(
    val avisos: List<AvisoItem> = emptyList(),
    val total: Int = 0,
)

// Abastecimento inteligente (consumo + antifraude)
data class AbastecimentoAlerta(
    val data: String?,
    val posto: String?,
    val placa: String?,
    val motivo: String?,
    val severidade: String?,
)
data class AbastecimentoInsightsResponse(
    val dias: Int = 90,
    @SerializedName("consumo_medio") val consumoMedio: Double? = null,
    @SerializedName("consumo_ultimo") val consumoUltimo: Double? = null,
    @SerializedName("tendencia_pct") val tendenciaPct: Double? = null,
    val amostras: Int = 0,
    @SerializedName("total_abastecimentos") val totalAbastecimentos: Int = 0,
    val insight: String? = null,
    val alertas: List<AbastecimentoAlerta> = emptyList(),
)

// Controle de velocidade
data class VelocidadeEvento(
    @SerializedName("data_hora") val dataHora: String?,
    val velocidade: Double?,
    val latitude: Double?,
    val longitude: Double?,
)
data class VelocidadeResponse(
    val dias: Int = 7,
    @SerializedName("limite_kmh") val limiteKmh: Double = 90.0,
    @SerializedName("total_pontos") val totalPontos: Int = 0,
    @SerializedName("velocidade_maxima") val velocidadeMaxima: Double = 0.0,
    @SerializedName("velocidade_media") val velocidadeMedia: Double = 0.0,
    val excessos: Int = 0,
    @SerializedName("pct_excesso") val pctExcesso: Double = 0.0,
    val score: Int = 100,
    val classificacao: String? = null,
    val eventos: List<VelocidadeEvento> = emptyList(),
)

// Avaliação / ranking do motorista
data class DesempenhoMetricas(
    val multas: Int = 0,
    @SerializedName("pontos_cnh") val pontosCnh: Int = 0,
    val ocorrencias: Int = 0,
    @SerializedName("excessos_velocidade") val excessosVelocidade: Int = 0,
    @SerializedName("pct_excesso") val pctExcesso: Double = 0.0,
    @SerializedName("consumo_km_l") val consumoKmL: Double? = null,
    @SerializedName("km_rodados") val kmRodados: Double = 0.0,
    val abastecimentos: Int = 0,
)
data class DesempenhoResponse(
    val dias: Int = 30,
    val score: Int = 100,
    val classificacao: String? = null,
    @SerializedName("ranking_posicao") val rankingPosicao: Int = 0,
    @SerializedName("ranking_total") val rankingTotal: Int = 0,
    val metricas: DesempenhoMetricas? = null,
)

// OCR de documento por IA de visão (abastecimento/painel/pedágio)
data class OcrCampos(
    // abastecimento
    val posto: String? = null,
    val data: String? = null,
    val hora: String? = null,
    val litros: Double? = null,
    @SerializedName("valor_total") val valorTotal: Double? = null,
    @SerializedName("valor_litro") val valorLitro: Double? = null,
    val combustivel: String? = null,
    @SerializedName("numero_nota") val numeroNota: String? = null,
    // painel
    val km: Int? = null,
    @SerializedName("combustivel_pct") val combustivelPct: Int? = null,
    // pedágio
    val praca: String? = null,
    val valor: Double? = null,
    // nota fiscal
    @SerializedName("nf_numero") val nfNumero: String? = null,
    @SerializedName("nf_serie") val nfSerie: String? = null,
    @SerializedName("nf_valor") val nfValor: Double? = null,
    val volumes: Int? = null,
    val peso: Double? = null,
    val emitente: String? = null,
    // lacre
    val lacre: String? = null,
)
data class OcrDocumentoResponse(val tipo: String?, val campos: OcrCampos?)
data class OcrLeituraResult(val tipo: String, val campos: OcrCampos)

// Conferência de carga
data class ConferenciaItem(
    val id: Int,
    @SerializedName("rota_id") val rotaId: Int?,
    val placa: String?,
    val tipo: String?,
    @SerializedName("nf_numero") val nfNumero: String?,
    @SerializedName("nf_valor") val nfValor: String?,
    val volumes: Int?,
    val lacre: String?,
    val conforme: Int?,
    val observacoes: String?,
    @SerializedName("data_hora") val dataHora: String?,
)
data class ConferenciasResponse(val conferencias: List<ConferenciaItem>)

data class ChecklistsResponse(val checklists: List<ChecklistItem>)
data class VeiculosResponse(val veiculos: List<VeiculoItem>)
data class VeiculoItem(val id: Int, val placa: String, val modelo: String?)
data class CidadesResponse(val cidades: List<CidadeItem>)
data class CidadeItem(val id: Int, val nome: String, val uf: String?)
data class EstadosResponse(val estados: List<EstadoItem>)
data class EstadoItem(val id: Int, val uf: String, val nome: String)

// Create responses
data class IdResponse(val id: Int)

// Pneus
data class PneuBuscaData(
    val pneu: PneuItem,
    val carcaca: CarcacaResumo?,
)

data class PneuItem(
    val id: Int,
    @SerializedName("numero_serie") val numeroSerie: String?,
    val marca: String?,
    val modelo: String?,
    val medida: String?,
    val dot: String?,
    @SerializedName("numero_recapagens") val numeroRecapagens: Int?,
)

data class CarcacaResumo(
    val codigo: String?,
    val recapagens: Int?,
    @SerializedName("km_acumulados") val kmAcumulados: Int?,
    @SerializedName("valor_investido") val valorInvestido: Double?,
    @SerializedName("status_carcaca") val statusCarcaca: String?,
)

// Modo Viagem / novos endpoints
data class ViagemPoi(val nome: String, val lat: Double, val lng: Double)
data class ViagemCerca(
    val id: Int,
    val nome: String,
    val lat: Double,
    val lng: Double,
    @SerializedName("raio_metros") val raioMetros: Int,
)
data class ViagemContextData(
    @SerializedName("rota_id") val rotaId: Int,
    val destino: String?,
    @SerializedName("destino_lat") val destinoLat: Double?,
    @SerializedName("destino_lng") val destinoLng: Double?,
    val postos: List<ViagemPoi>?,
    val pedagios: List<ViagemPoi>?,
    val cercas: List<ViagemCerca>?,
)
data class TripTimelineSyncResponse(@SerializedName("sincronizados") val sincronizados: Int?)
data class MotoristaAssistenteResponse(val resposta: String?)
data class MotoristaDocumentosData(
    val nome: String?,
    val cpf: String?,
    val cnh: String?,
    @SerializedName("categoria_cnh") val categoriaCnh: String?,
    @SerializedName("data_validade_cnh") val dataValidadeCnh: String?,
    @SerializedName("dias_para_vencer") val diasParaVencer: Int?,
    @SerializedName("status_cnh") val statusCnh: String?,
)
data class GpsTrajetoPonto(
    val lat: Double?,
    val lng: Double?,
    @SerializedName("velocidade_kmh") val velocidadeKmh: Double?,
    @SerializedName("data_hora") val dataHora: String?,
)
data class GpsTrajetoResponse(val pontos: List<GpsTrajetoPonto>?, val total: Int?)
