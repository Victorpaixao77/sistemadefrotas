package com.sistemafrotas.motorista.ui.screens

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.AccessTime
import androidx.compose.material.icons.filled.AssignmentTurnedIn
import androidx.compose.material.icons.filled.Campaign
import androidx.compose.material.icons.filled.Badge
import androidx.compose.material.icons.filled.Map
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.EventNote
import androidx.compose.material.icons.filled.LocalGasStation
import androidx.compose.material.icons.filled.LocalShipping
import androidx.compose.material.icons.filled.Psychology
import androidx.compose.material.icons.filled.SmartToy
import androidx.compose.material.icons.filled.NotificationsActive
import androidx.compose.material.icons.filled.TireRepair
import androidx.compose.material.icons.outlined.Checklist
import androidx.compose.material.icons.filled.Speed
import androidx.compose.material.icons.filled.TrendingUp
import androidx.compose.material.icons.filled.Toll
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.data.AuthRepository

private data class MaisItem(val key: String, val titulo: String, val descricao: String, val icone: ImageVector)

private val MAIS_ITENS = listOf(
    MaisItem("modo_viagem", "Modo Viagem", "Copiloto digital: GPS, OCR, IA e linha do tempo", Icons.Filled.SmartToy),
    MaisItem("abastecimentos", "Abastecimentos", "Registre litros, valor e comprovante", Icons.Filled.LocalGasStation),
    MaisItem("checklists", "Checklists", "Conferência do veículo antes de rodar", Icons.Outlined.Checklist),
    MaisItem("pneus", "Pneus", "Sulco, calibragem e troca dos pneus", Icons.Filled.TireRepair),
    MaisItem("avisos", "Avisos", "Novas rotas, checklist pendente e documentos vencendo", Icons.Filled.NotificationsActive),
    MaisItem("panico", "Botão de pânico", "Emergência: envia sua localização à central", Icons.Filled.Campaign),
    MaisItem("pod", "Comprovante de entrega", "Foto da NF, da carga e assinatura do cliente", Icons.Filled.AssignmentTurnedIn),
    MaisItem("ocorrencias", "Ocorrências de viagem", "Acidente, multa, roubo, problema mecânico…", Icons.Filled.Warning),
    MaisItem("jornada", "Jornada do motorista", "Início, parada, descanso, refeição e fim", Icons.Filled.AccessTime),
    MaisItem("pedagios", "Controle de pedágios", "Registre cada praça, valor e foto do comprovante", Icons.Filled.Toll),
    MaisItem("velocidade", "Controle de velocidade", "Sua pontuação de direção e excessos", Icons.Filled.Speed),
    MaisItem("consumo", "Meu consumo", "Consumo, tendência e alertas de abastecimento", Icons.Filled.TrendingUp),
    MaisItem("conferencia", "Conferência de carga", "Confira NF, volumes e lacre com foto", Icons.Filled.LocalShipping),
    MaisItem("desempenho", "Minha avaliação", "Pontuação e ranking na empresa", Icons.Filled.EmojiEvents),
    MaisItem("diario", "Diário de bordo", "Linha do tempo das suas atividades", Icons.Filled.EventNote),
    MaisItem("mapa_viagem", "Mapa da viagem", "Trajeto GPS das últimas 24 horas", Icons.Filled.Map),
    MaisItem("documentos", "Meus documentos", "CNH, validade e alertas", Icons.Filled.Badge),
)

/**
 * Hub "Mais": reúne recursos secundários do app sem lotar a barra inferior.
 * Cada item abre a tela correspondente com um botão "Voltar" para o hub.
 */
@Composable
fun MaisScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
    onSubScreenChange: (title: String?, onBack: (() -> Unit)?) -> Unit = { _, _ -> },
    inicial: String? = null,
    onInicialConsumed: () -> Unit = {},
    onExitToHome: () -> Unit = {},
    onOpenTripMode: () -> Unit = {},
) {
    var aberta by remember { mutableStateOf<String?>(null) }
    // Foi aberta por um atalho do Início? Então "voltar" retorna ao Início,
    // não à lista do "Mais".
    var viaDeepLink by remember { mutableStateOf(false) }

    // Deep-link vindo dos atalhos do Início (abre direto a feature pedida).
    LaunchedEffect(inicial) {
        if (inicial != null) {
            if (inicial == "modo_viagem") {
                onOpenTripMode()
                onInicialConsumed()
            } else {
                aberta = inicial
                viaDeepLink = true
                onInicialConsumed()
            }
        }
    }

    // Ação de voltar da feature: Início (se veio de atalho) ou lista do Mais.
    val voltarFeature: () -> Unit = {
        if (viaDeepLink) {
            viaDeepLink = false
            aberta = null
            onExitToHome()
        } else {
            aberta = null
        }
    }

    // Voltar ÚNICO: reporta a feature aberta para a barra superior do app
    // (título + seta de voltar). Assim não há dois "voltar" (barra + breadcrumb).
    val tituloAberta = tituloFeature(aberta)
    LaunchedEffect(tituloAberta) {
        if (tituloAberta != null) onSubScreenChange(tituloAberta, voltarFeature)
        else onSubScreenChange(null, null)
    }
    // Ao sair da aba "Mais", limpa a barra para não vazar o título para outras abas.
    DisposableEffect(Unit) {
        onDispose { onSubScreenChange(null, null) }
    }

    when (aberta) {
        "abastecimentos" -> FeatureWrapper(titulo = "Abastecimentos", onVoltar = { aberta = null }) {
            AbastecimentosScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize(),
                onSubScreenChange = { t, b -> if (t == null) onSubScreenChange("Abastecimentos", voltarFeature) else onSubScreenChange(t, b) })
        }
        "checklists" -> FeatureWrapper(titulo = "Checklists", onVoltar = { aberta = null }) {
            ChecklistsScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize(),
                onSubScreenChange = { t, b -> if (t == null) onSubScreenChange("Checklists", voltarFeature) else onSubScreenChange(t, b) })
        }
        "pneus" -> FeatureWrapper(titulo = "Pneus", onVoltar = { aberta = null }) {
            PneusScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "avisos" -> FeatureWrapper(titulo = "Avisos", onVoltar = { aberta = null }) {
            AvisosScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "panico" -> FeatureWrapper(titulo = "Botão de pânico", onVoltar = { aberta = null }) {
            PanicoScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "pod" -> FeatureWrapper(titulo = "Comprovante de entrega", onVoltar = { aberta = null }) {
            PodScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "ocorrencias" -> FeatureWrapper(titulo = "Ocorrências", onVoltar = { aberta = null }) {
            OcorrenciasScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize(),
                onSubScreenChange = { t, b -> if (t == null) onSubScreenChange("Ocorrências", voltarFeature) else onSubScreenChange(t, b) })
        }
        "jornada" -> FeatureWrapper(titulo = "Jornada do motorista", onVoltar = { aberta = null }) {
            JornadaScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "pedagios" -> FeatureWrapper(titulo = "Controle de pedágios", onVoltar = { aberta = null }) {
            PedagiosScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize(),
                onSubScreenChange = { t, b -> if (t == null) onSubScreenChange("Controle de pedágios", voltarFeature) else onSubScreenChange(t, b) })
        }
        "velocidade" -> FeatureWrapper(titulo = "Controle de velocidade", onVoltar = { aberta = null }) {
            VelocidadeScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "consumo" -> FeatureWrapper(titulo = "Meu consumo", onVoltar = { aberta = null }) {
            ConsumoScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "conferencia" -> FeatureWrapper(titulo = "Conferência de carga", onVoltar = { aberta = null }) {
            ConferenciaCargaScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize(),
                onSubScreenChange = { t, b -> if (t == null) onSubScreenChange("Conferência de carga", voltarFeature) else onSubScreenChange(t, b) })
        }
        "desempenho" -> FeatureWrapper(titulo = "Minha avaliação", onVoltar = { aberta = null }) {
            DesempenhoScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "diario" -> FeatureWrapper(titulo = "Diário de bordo", onVoltar = { aberta = null }) {
            DiarioScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "mapa_viagem" -> FeatureWrapper(titulo = "Mapa da viagem", onVoltar = { aberta = null }) {
            MapaViagemScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "documentos" -> FeatureWrapper(titulo = "Meus documentos", onVoltar = { aberta = null }) {
            DocumentosScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        else -> LazyColumn(
            modifier.fillMaxSize(),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item {
                Text("Mais recursos", style = MaterialTheme.typography.titleMedium)
                Spacer(Modifier.height(4.dp))
            }
            items(MAIS_ITENS, key = { it.key }) { it ->
                Card(
                    Modifier.fillMaxWidth().clickable {
                        if (it.key == "modo_viagem") {
                            onOpenTripMode()
                        } else {
                            viaDeepLink = false
                            aberta = it.key
                        }
                    },
                    shape = RoundedCornerShape(12.dp),
                ) {
                    Row(
                        Modifier.fillMaxWidth().padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Icon(it.icone, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                        Spacer(Modifier.width(14.dp))
                        Column(Modifier.weight(1f)) {
                            Text(it.titulo, style = MaterialTheme.typography.titleSmall, fontWeight = androidx.compose.ui.text.font.FontWeight.SemiBold)
                            Text(it.descricao, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                        Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
        }
    }
}

/** Título exibido na barra superior do app para cada feature do "Mais". */
private fun tituloFeature(key: String?): String? = when (key) {
    "abastecimentos" -> "Abastecimentos"
    "checklists" -> "Checklists"
    "pneus" -> "Pneus"
    "avisos" -> "Avisos"
    "panico" -> "Botão de pânico"
    "pod" -> "Comprovante de entrega"
    "ocorrencias" -> "Ocorrências"
    "jornada" -> "Jornada do motorista"
    "pedagios" -> "Controle de pedágios"
    "velocidade" -> "Controle de velocidade"
    "consumo" -> "Meu consumo"
    "conferencia" -> "Conferência de carga"
    "desempenho" -> "Minha avaliação"
    "diario" -> "Diário de bordo"
    "mapa_viagem" -> "Mapa da viagem"
    "documentos" -> "Meus documentos"
    else -> null
}

/**
 * Antes desenhava um breadcrumb "← Mais {título}" próprio, o que gerava DOIS
 * "voltar" (barra do app + breadcrumb). Agora o voltar/título fica só na barra
 * superior do app (via onSubScreenChange); aqui é apenas o container do conteúdo.
 */
@Composable
private fun FeatureWrapper(titulo: String, onVoltar: () -> Unit, content: @Composable () -> Unit) {
    Box(Modifier.fillMaxSize()) { content() }
}
