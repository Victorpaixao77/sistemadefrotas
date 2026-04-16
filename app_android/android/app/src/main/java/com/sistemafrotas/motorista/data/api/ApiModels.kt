package com.sistemafrotas.motorista.data.api

import com.google.gson.annotations.SerializedName

// Auth
/** action=login explícito para o PHP (corpo JSON). */
data class LoginRequest(
    val nome: String,
    val senha: String,
    val action: String = "login",
)
data class LoginResponse(
    val success: Boolean,
    val message: String?,
    val data: LoginData?,
)
data class LoginData(
    val token: String,
    @SerializedName("motorista_id") val motoristaId: Int,
    @SerializedName("empresa_id") val empresaId: Int,
    val nome: String,
    @SerializedName("expira_em") val expiraEm: String?,
    @SerializedName("refresh_token") val refreshToken: String?,
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
data class ChecklistsResponse(val checklists: List<ChecklistItem>)
data class VeiculosResponse(val veiculos: List<VeiculoItem>)
data class VeiculoItem(val id: Int, val placa: String, val modelo: String?)
data class CidadesResponse(val cidades: List<CidadeItem>)
data class CidadeItem(val id: Int, val nome: String, val uf: String?)
data class EstadosResponse(val estados: List<EstadoItem>)
data class EstadoItem(val id: Int, val uf: String, val nome: String)

// Create responses
data class IdResponse(val id: Int)
