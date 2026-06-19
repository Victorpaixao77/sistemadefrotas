package com.sistemafrotas.motorista.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.clickable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.ExperimentalMaterialApi
import androidx.compose.material.pullrefresh.PullRefreshIndicator
import androidx.compose.material.pullrefresh.pullRefresh
import androidx.compose.material.pullrefresh.rememberPullRefreshState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowForward
import androidx.compose.material.icons.filled.AccessTime
import androidx.compose.material.icons.filled.AssignmentTurnedIn
import androidx.compose.material.icons.filled.Campaign
import androidx.compose.material.icons.filled.LocalGasStation
import androidx.compose.material.icons.filled.Receipt
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material.icons.filled.Route
import androidx.compose.material.icons.filled.TireRepair
import androidx.compose.material.icons.outlined.Checklist
import androidx.compose.material.icons.outlined.Place
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.lifecycle.viewmodel.compose.viewModel
import com.sistemafrotas.motorista.ui.viewmodel.AuthViewModelFactory
import com.sistemafrotas.motorista.ui.viewmodel.DashboardViewModel
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.JornadaResumoHoje
import com.sistemafrotas.motorista.data.api.DashboardData
import com.sistemafrotas.motorista.ui.components.DashboardHomeSkeleton
import com.sistemafrotas.motorista.ui.components.GpsDeviceStatusCard
import kotlinx.coroutines.launch
import java.util.Locale

private fun formatMoney(value: Double) = "R$ %.2f".format(Locale("pt", "BR"), value)

@OptIn(ExperimentalMaterial3Api::class, ExperimentalMaterialApi::class)
@Composable
fun DashboardScreen(
    authRepository: AuthRepository,
    onSessionExpired: () -> Unit = {},
    onNavigateToTab: ((Int) -> Unit)? = null,
    onAbrirMais: ((String) -> Unit)? = null,
    onLogout: (() -> Unit)? = null,
    modifier: Modifier = Modifier,
) {
    val vm: DashboardViewModel = viewModel(
        factory = AuthViewModelFactory(authRepository) { DashboardViewModel(it) }
    )
    val data by vm.data.collectAsState()
    val loading by vm.loading.collectAsState()
    val refreshing by vm.refreshing.collectAsState()
    val error by vm.error.collectAsState()
    val nome by authRepository.nome.collectAsState(initial = null)
    var jornadaResumo by remember { mutableStateOf<JornadaResumoHoje?>(null) }

    LaunchedEffect(Unit) {
        authRepository.loadJornada().onSuccess { jornadaResumo = it.resumoHoje }
    }

    fun isSessionMsg(msg: String) = msg.contains("expirad", ignoreCase = true) ||
        msg.contains("inválido", ignoreCase = true) ||
        msg.contains("Sessão expirada", ignoreCase = true)

    LaunchedEffect(Unit) { vm.carregarSeNecessario() }

    val pullRefreshState = rememberPullRefreshState(refreshing, { vm.refresh(true) })

    LaunchedEffect(error) {
        val msg = error ?: return@LaunchedEffect
        if (isSessionMsg(msg)) onSessionExpired()
    }

    Box(modifier = modifier.fillMaxSize()) {
        if (loading && data == null) {
            DashboardHomeSkeleton(Modifier.fillMaxSize())
        } else if (error != null && data == null) {
            Card(
                modifier = Modifier.padding(16.dp).fillMaxWidth().align(Alignment.Center),
                shape = RoundedCornerShape(12.dp),
                colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer),
            ) {
                Column(Modifier.padding(16.dp)) {
                    Text(error ?: "", color = MaterialTheme.colorScheme.onErrorContainer)
                    Spacer(Modifier.height(12.dp))
                    Text(
                        "Dica: No celular, altere a URL em Configurações para o IP do seu PC. No emulador use 10.0.2.2.",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onErrorContainer,
                    )
                    Spacer(Modifier.height(16.dp))
                    Button(onClick = { vm.setError(null); vm.refresh(false) }) {
                        Text("Tentar novamente")
                    }
                }
            }
        } else {
            val resumo = data?.resumoMes
            Box(Modifier.fillMaxSize().pullRefresh(pullRefreshState)) {
            Column(Modifier.verticalScroll(rememberScrollState()).padding(20.dp)) {
                Row(
                    Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Column {
                        Text(
                            "Olá, ${nome ?: "Motorista"}",
                            style = MaterialTheme.typography.headlineMedium,
                            fontWeight = FontWeight.SemiBold,
                            color = MaterialTheme.colorScheme.onBackground,
                        )
                        Text(
                            "Toque em uma opção para acessar",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
                Spacer(Modifier.height(20.dp))

                // ----- Ações rápidas (atalhos grandes para o uso em campo) -----
                Text("Ações rápidas", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                Spacer(Modifier.height(8.dp))
                // Pânico em destaque (1 toque a partir do Início)
                Card(
                    modifier = Modifier.fillMaxWidth().clickable { onAbrirMais?.invoke("panico") },
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = androidx.compose.ui.graphics.Color(0xFFDC2626)),
                ) {
                    Row(Modifier.fillMaxWidth().padding(18.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.Campaign, contentDescription = null, tint = androidx.compose.ui.graphics.Color.White)
                        Spacer(Modifier.width(12.dp))
                        Column {
                            Text("Pânico / Emergência", color = androidx.compose.ui.graphics.Color.White, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.titleMedium)
                            Text("Envia sua localização à central", color = androidx.compose.ui.graphics.Color.White.copy(alpha = 0.9f), style = MaterialTheme.typography.bodySmall)
                        }
                    }
                }
                Spacer(Modifier.height(10.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    DashAcao("Abastecer", Icons.Filled.LocalGasStation, Modifier.weight(1f)) { onNavigateToTab?.invoke(2) }
                    DashAcao("Jornada", Icons.Filled.AccessTime, Modifier.weight(1f)) { onAbrirMais?.invoke("jornada") }
                }
                Spacer(Modifier.height(10.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    DashAcao("Ocorrência", Icons.Filled.Warning, Modifier.weight(1f)) { onAbrirMais?.invoke("ocorrencias") }
                    DashAcao("Entrega (POD)", Icons.Filled.AssignmentTurnedIn, Modifier.weight(1f)) { onAbrirMais?.invoke("pod") }
                }
                Spacer(Modifier.height(12.dp))

                // ----- Card de Jornada (estado atual + horas do dia) -----
                jornadaResumo?.let { jr ->
                    Card(
                        modifier = Modifier.fillMaxWidth().clickable { onAbrirMais?.invoke("jornada") },
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                    ) {
                        Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Filled.AccessTime, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                            Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) {
                                Text(jornadaEstadoLabel(jr.estadoAtual), fontWeight = FontWeight.SemiBold)
                                Text(
                                    "Hoje: ${jornadaMin(jr.dirigindoMin)} dirigindo · ${jornadaMin(jr.descansoMin)} parado",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                                )
                            }
                            Icon(Icons.Filled.ArrowForward, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                    Spacer(Modifier.height(20.dp))
                }

                if (resumo != null) {
                    Text("Resumo do mês", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(8.dp))
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
                    ) {
                        Column(Modifier.padding(16.dp)) {
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("Total fretes", color = MaterialTheme.colorScheme.onSurfaceVariant)
                                Text(formatMoney(resumo.totalFreteMes), fontWeight = FontWeight.Medium)
                            }
                            Spacer(Modifier.height(6.dp))
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("Comissão", color = MaterialTheme.colorScheme.onSurfaceVariant)
                                Text(formatMoney(resumo.totalComissaoMes), fontWeight = FontWeight.Medium)
                            }
                            Spacer(Modifier.height(6.dp))
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("Despesas", color = MaterialTheme.colorScheme.onSurfaceVariant)
                                Text(formatMoney(resumo.totalDespesasMes), fontWeight = FontWeight.Medium)
                            }
                            Divider(Modifier.padding(vertical = 8.dp))
                            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                                Text("Lucro líquido", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                                Text(
                                    formatMoney(resumo.lucroMes),
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.Bold,
                                    color = if (resumo.lucroMes >= 0) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.error,
                                )
                            }
                        }
                    }
                    Spacer(Modifier.height(24.dp))
                }

                val gps = data?.gpsResumo
                if (gps != null) {
                    Text("GPS", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(8.dp))
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.secondaryContainer.copy(alpha = 0.45f)),
                    ) {
                        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Outlined.Place, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                            Spacer(Modifier.width(12.dp))
                            Column(Modifier.weight(1f)) {
                                Text(
                                    "${gps.pontosUltimas24h ?: 0} pontos nas últimas 24 h",
                                    style = MaterialTheme.typography.bodyLarge,
                                    fontWeight = FontWeight.Medium,
                                )
                                val u = gps.ultimoRegistro
                                if (u != null) {
                                    Text(
                                        "Último: ${u.placa ?: "—"} · ${u.dataHora ?: ""}",
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                } else {
                                    Text(
                                        "Nenhum registro recente neste período.",
                                        style = MaterialTheme.typography.bodySmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                                    )
                                }
                            }
                        }
                    }
                    Spacer(Modifier.height(16.dp))
                }

                GpsDeviceStatusCard()
                Spacer(Modifier.height(24.dp))

                val c = data?.contadores
                val rotasCount = c?.rotasPendentes ?: 0
                val abastCount = c?.abastecimentosPendentes ?: 0
                val checkCount = c?.checklistsPendentes ?: 0

                DashboardButton(
                    icon = Icons.Filled.Route,
                    title = "Rotas",
                    count = rotasCount,
                    onClick = { onNavigateToTab?.invoke(1) },
                )
                Spacer(Modifier.height(12.dp))
                DashboardButton(
                    icon = Icons.Filled.LocalGasStation,
                    title = "Abastecimentos",
                    count = abastCount,
                    onClick = { onNavigateToTab?.invoke(2) },
                )
                Spacer(Modifier.height(12.dp))
                DashboardButton(
                    icon = Icons.Outlined.Checklist,
                    title = "Checklists",
                    count = checkCount,
                    onClick = { onNavigateToTab?.invoke(3) },
                )
                Spacer(Modifier.height(12.dp))
                DashboardButton(
                    icon = Icons.Filled.Receipt,
                    title = "Despesas",
                    count = null,
                    onClick = { onNavigateToTab?.invoke(4) },
                )
                Spacer(Modifier.height(12.dp))
                DashboardButton(
                    icon = Icons.Filled.TireRepair,
                    title = "Pneus",
                    count = null,
                    onClick = { onNavigateToTab?.invoke(5) },
                )
            }
            PullRefreshIndicator(refreshing, pullRefreshState, Modifier.align(Alignment.TopCenter))
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DashboardButton(
    icon: ImageVector,
    title: String,
    count: Int?,
    onClick: () -> Unit,
) {
    Card(
        onClick = onClick,
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
    ) {
        Row(
            Modifier
                .fillMaxWidth()
                .padding(20.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Surface(
                shape = RoundedCornerShape(12.dp),
                color = MaterialTheme.colorScheme.primaryContainer,
            ) {
                Icon(
                    icon,
                    contentDescription = null,
                    modifier = Modifier.padding(14.dp),
                    tint = MaterialTheme.colorScheme.primary,
                )
            }
            Spacer(Modifier.width(16.dp))
            Column(Modifier.weight(1f)) {
                Text(
                    title,
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Medium,
                    color = MaterialTheme.colorScheme.onSurface,
                )
                if (count != null) {
                    Text(
                        "$count pendente(s)",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            if (count != null) {
                Surface(
                    shape = RoundedCornerShape(20.dp),
                    color = MaterialTheme.colorScheme.primary,
                ) {
                    Text(
                        "$count",
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                        style = MaterialTheme.typography.labelLarge,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onPrimary,
                    )
                }
            }
            Icon(
                Icons.Filled.ArrowForward,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

private fun jornadaEstadoLabel(estado: String?): String = when (estado) {
    "inicio" -> "Em viagem (dirigindo)"
    "parada" -> "Parado"
    "descanso" -> "Em descanso"
    "refeicao" -> "Em refeição"
    "fim" -> "Jornada encerrada"
    else -> "Jornada não iniciada hoje"
}

private fun jornadaMin(min: Int): String {
    val h = min / 60
    val m = min % 60
    return if (h > 0) "${h}h${if (m > 0) " ${m}min" else ""}" else "${m}min"
}

/** Atalho quadrado de ação rápida no Início (ícone + rótulo, alvo grande para uso em campo). */
@Composable
private fun DashAcao(
    label: String,
    icon: ImageVector,
    modifier: Modifier = Modifier,
    onClick: () -> Unit,
) {
    Card(
        modifier = modifier.clickable { onClick() },
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surfaceVariant),
    ) {
        Column(
            Modifier.fillMaxWidth().padding(vertical = 18.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Icon(icon, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.height(8.dp))
            Text(label, style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Medium)
        }
    }
}
