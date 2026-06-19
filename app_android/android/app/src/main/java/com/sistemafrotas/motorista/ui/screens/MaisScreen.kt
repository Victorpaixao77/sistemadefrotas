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
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.EventNote
import androidx.compose.material.icons.filled.LocalShipping
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
) {
    var aberta by remember { mutableStateOf<String?>(null) }

    // Deep-link vindo dos atalhos do Início (abre direto a feature pedida).
    LaunchedEffect(inicial) {
        if (inicial != null) {
            aberta = inicial
            onInicialConsumed()
        }
    }

    when (aberta) {
        "panico" -> FeatureWrapper(titulo = "Botão de pânico", onVoltar = { aberta = null }) {
            PanicoScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "pod" -> FeatureWrapper(titulo = "Comprovante de entrega", onVoltar = { aberta = null }) {
            PodScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "ocorrencias" -> FeatureWrapper(titulo = "Ocorrências", onVoltar = { aberta = null }) {
            OcorrenciasScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize(), onSubScreenChange = onSubScreenChange)
        }
        "jornada" -> FeatureWrapper(titulo = "Jornada do motorista", onVoltar = { aberta = null }) {
            JornadaScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "pedagios" -> FeatureWrapper(titulo = "Controle de pedágios", onVoltar = { aberta = null }) {
            PedagiosScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize(), onSubScreenChange = onSubScreenChange)
        }
        "velocidade" -> FeatureWrapper(titulo = "Controle de velocidade", onVoltar = { aberta = null }) {
            VelocidadeScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "consumo" -> FeatureWrapper(titulo = "Meu consumo", onVoltar = { aberta = null }) {
            ConsumoScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "conferencia" -> FeatureWrapper(titulo = "Conferência de carga", onVoltar = { aberta = null }) {
            ConferenciaCargaScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize(), onSubScreenChange = onSubScreenChange)
        }
        "desempenho" -> FeatureWrapper(titulo = "Minha avaliação", onVoltar = { aberta = null }) {
            DesempenhoScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
        }
        "diario" -> FeatureWrapper(titulo = "Diário de bordo", onVoltar = { aberta = null }) {
            DiarioScreen(authRepository = authRepository, modifier = Modifier.fillMaxSize())
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
            items(MAIS_ITENS) { it ->
                Card(
                    Modifier.fillMaxWidth().clickable { aberta = it.key },
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

@Composable
private fun FeatureWrapper(titulo: String, onVoltar: () -> Unit, content: @Composable () -> Unit) {
    Column(Modifier.fillMaxSize()) {
        Surface(tonalElevation = 1.dp) {
            Row(
                Modifier.fillMaxWidth().padding(horizontal = 4.dp, vertical = 2.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                TextButton(onClick = onVoltar) {
                    Icon(Icons.Filled.ArrowBack, contentDescription = "Voltar")
                    Spacer(Modifier.width(4.dp))
                    Text("Mais")
                }
                Text(titulo, style = MaterialTheme.typography.titleSmall)
            }
        }
        Box(Modifier.weight(1f)) { content() }
    }
}
