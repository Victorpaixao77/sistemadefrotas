package com.sistemafrotas.motorista.ui.screens

import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.compose.foundation.background
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.ExperimentalMaterialApi
import androidx.compose.material.pullrefresh.PullRefreshIndicator
import androidx.compose.material.pullrefresh.pullRefresh
import androidx.compose.material.pullrefresh.rememberPullRefreshState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccessTime
import androidx.compose.material.icons.filled.AssignmentTurnedIn
import androidx.compose.material.icons.filled.AttachMoney
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.LocalGasStation
import androidx.compose.material.icons.filled.LocalShipping
import androidx.compose.material.icons.filled.Psychology
import androidx.compose.material.icons.filled.Logout
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Receipt
import androidx.compose.material.icons.filled.Route
import androidx.compose.material.icons.filled.Settings
import androidx.compose.material.icons.filled.TrendingUp
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material.icons.outlined.Checklist
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.DashboardData
import com.sistemafrotas.motorista.data.api.JornadaResumoHoje
import com.sistemafrotas.motorista.feature.viagem.data.TripSessionStore
import com.sistemafrotas.motorista.feature.viagem.domain.TripPhase
import com.sistemafrotas.motorista.feature.viagem.domain.TripSession
import com.sistemafrotas.motorista.ui.components.DashboardHomeSkeleton
import com.sistemafrotas.motorista.ui.components.GpsDeviceStatusCard
import com.sistemafrotas.motorista.ui.viewmodel.AuthViewModelFactory
import com.sistemafrotas.motorista.ui.viewmodel.DashboardViewModel
import java.util.Locale

/* ---- Paleta semântica (acentos). Superfícies usam o tema (dark/light). ---- */
// Azul de acento = accent-primary do sistema web (#3b82f6). Topo = navy dos menus web.
private val Blue = Color(0xFF3B82F6)
private val BlueLight = Color(0xFF3B82F6)
// Navy da identidade do sistema (bg-secondary -> bg-sidebar do web).
private val NavyTop = Color(0xFF1A2332)
private val NavyTopDeep = Color(0xFF0F172A)
private val Red = Color(0xFFDC2626)
private val Green = Color(0xFF16A34A)
private val Amber = Color(0xFFF59E0B)
private val Cyan = Color(0xFF0891B2)

private fun formatMoney(value: Double): String =
    java.text.NumberFormat.getCurrencyInstance(Locale("pt", "BR")).format(value)

private fun saudacao(): String {
    val h = java.util.Calendar.getInstance().get(java.util.Calendar.HOUR_OF_DAY)
    return when {
        h < 12 -> "Bom dia,"
        h < 18 -> "Boa tarde,"
        else -> "Boa noite,"
    }
}

private fun primeiroNome(nome: String?): String =
    nome?.trim()?.split(" ")?.firstOrNull()?.takeIf { it.isNotBlank() } ?: "Motorista"

@OptIn(ExperimentalMaterial3Api::class, ExperimentalMaterialApi::class)
@Composable
fun DashboardScreen(
    authRepository: AuthRepository,
    onSessionExpired: () -> Unit = {},
    onNavigateToTab: ((Int) -> Unit)? = null,
    onAbrirMais: ((String) -> Unit)? = null,
    onOpenTripMode: (() -> Unit)? = null,
    onOpenConfig: (() -> Unit)? = null,
    onLogout: (() -> Unit)? = null,
    modifier: Modifier = Modifier,
) {
    val vm: DashboardViewModel = viewModel(
        factory = AuthViewModelFactory(authRepository) { DashboardViewModel(it) }
    )
    val data by vm.data.collectAsStateWithLifecycle()
    val loading by vm.loading.collectAsStateWithLifecycle()
    val refreshing by vm.refreshing.collectAsStateWithLifecycle()
    val error by vm.error.collectAsStateWithLifecycle()
    val nome by authRepository.nome.collectAsStateWithLifecycle(initialValue = null)
    var jornadaResumo by remember { mutableStateOf<JornadaResumoHoje?>(null) }
    val context = LocalContext.current
    val tripSessionStore = remember { TripSessionStore(context) }
    val activeTrip by tripSessionStore.sessionFlow.collectAsStateWithLifecycle(initialValue = null)

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

    Box(modifier = modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        if (loading && data == null) {
            DashboardHomeSkeleton(Modifier.fillMaxSize())
        } else if (error != null && data == null) {
            ErrorState(error ?: "") { vm.setError(null); vm.refresh(false) }
        } else {
            val resumo = data?.resumoMes
            Box(Modifier.fillMaxSize().pullRefresh(pullRefreshState)) {
                Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState())) {

                    // 1) Saudação (header azul)
                    Header(
                        nome = nome,
                        onBell = { onAbrirMais?.invoke("avisos") },
                        onOpenConfig = { onOpenConfig?.invoke() },
                        onLogout = { onLogout?.invoke() },
                    )

                    Column(Modifier.padding(horizontal = 20.dp, vertical = 18.dp)) {

                        // 2) Modo Viagem — copiloto digital
                        if (activeTrip?.phase == TripPhase.EM_VIAGEM) {
                            ActiveTripBanner(session = activeTrip!!, onContinue = { onOpenTripMode?.invoke() })
                        } else {
                            TripModeHeroCard(onClick = { onOpenTripMode?.invoke() })
                        }
                        Spacer(Modifier.height(24.dp))

                        // 3) Emergência
                        EmergencyCard { onAbrirMais?.invoke("panico") }
                        Spacer(Modifier.height(24.dp))

                        // 4) Ações rápidas (lista agrupada, cada linha com seta)
                        SectionTitle("Ações rápidas")
                        Spacer(Modifier.height(10.dp))
                        QuickActions(
                            onAbastecer = { onAbrirMais?.invoke("abastecimentos") },
                            onJornada = { onAbrirMais?.invoke("jornada") },
                            onPod = { onAbrirMais?.invoke("pod") },
                            onOcorrencia = { onAbrirMais?.invoke("ocorrencias") },
                            onChecklist = { onAbrirMais?.invoke("checklists") },
                        )
                        Spacer(Modifier.height(24.dp))

                        // 4) Status da jornada (grande)
                        JourneyStatus(jornadaResumo) { onAbrirMais?.invoke("jornada") }
                        Spacer(Modifier.height(24.dp))

                        // 5) Resumo financeiro (valores reais)
                        if (resumo != null) {
                            SectionTitle("Resumo do mês")
                            Spacer(Modifier.height(10.dp))
                            FinanceGrid(
                                fretes = resumo.totalFreteMes,
                                comissao = resumo.totalComissaoMes,
                                despesas = resumo.totalDespesasMes,
                                lucro = resumo.lucroMes,
                            )
                            Spacer(Modifier.height(24.dp))
                        }

                        // 6) Atividades recentes (timeline, dados reais)
                        SectionTitle("Atividades recentes", action = "Ver todas") { onAbrirMais?.invoke("diario") }
                        Spacer(Modifier.height(10.dp))
                        ActivitiesCard(buildAtividades(data))
                        Spacer(Modifier.height(24.dp))

                        // 7) Avisos (pendências reais)
                        SectionTitle("Avisos")
                        Spacer(Modifier.height(10.dp))
                        AvisosCard(
                            data,
                            onRotas = { onNavigateToTab?.invoke(1) },
                            onAbast = { onAbrirMais?.invoke("abastecimentos") },
                            onCheck = { onAbrirMais?.invoke("checklists") },
                        )
                        Spacer(Modifier.height(24.dp))

                        // GPS (funcional — discreto)
                        GpsDeviceStatusCard()
                        Spacer(Modifier.height(24.dp))
                    }
                }
                PullRefreshIndicator(refreshing, pullRefreshState, Modifier.align(Alignment.TopCenter))
            }
        }
    }
}

/* ============================ Header ============================ */

@Composable
private fun Header(nome: String?, onBell: () -> Unit, onOpenConfig: () -> Unit, onLogout: () -> Unit) {
    val inicial = primeiroNome(nome).firstOrNull()?.uppercaseChar()?.toString() ?: "M"
    Box(
        Modifier
            .fillMaxWidth()
            .background(Brush.verticalGradient(listOf(NavyTop, NavyTopDeep)))
            .padding(start = 20.dp, end = 12.dp, top = 22.dp, bottom = 26.dp),
    ) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(saudacao(), style = MaterialTheme.typography.bodyMedium, color = Color.White.copy(alpha = 0.85f))
                Text(
                    "${primeiroNome(nome)} 👋",
                    fontSize = 28.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White,
                )
                Spacer(Modifier.height(2.dp))
                Text("Aqui está o seu resumo de hoje", style = MaterialTheme.typography.bodyMedium, color = Color.White.copy(alpha = 0.85f))
            }
            IconButton(onClick = onBell) {
                Icon(Icons.Filled.Notifications, contentDescription = "Avisos", tint = Color.White)
            }
            var menu by remember { mutableStateOf(false) }
            Box {
                Surface(
                    shape = CircleShape,
                    color = Color.White.copy(alpha = 0.22f),
                    modifier = Modifier.size(44.dp).clickable { menu = true },
                ) {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text(inicial, color = Color.White, fontWeight = FontWeight.Bold, fontSize = 18.sp)
                    }
                }
                DropdownMenu(expanded = menu, onDismissRequest = { menu = false }) {
                    DropdownMenuItem(
                        text = { Row(verticalAlignment = Alignment.CenterVertically) { Icon(Icons.Filled.Settings, null, Modifier.padding(end = 8.dp)); Text("Configurações") } },
                        onClick = { menu = false; onOpenConfig() },
                    )
                    DropdownMenuItem(
                        text = { Row(verticalAlignment = Alignment.CenterVertically) { Icon(Icons.Filled.Logout, null, Modifier.padding(end = 8.dp)); Text("Sair") } },
                        onClick = { menu = false; onLogout() },
                    )
                }
            }
        }
    }
}

/* ============================ Modo Viagem ============================ */

@Composable
private fun ActiveTripBanner(session: TripSession, onContinue: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth().clickable { onContinue() },
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Green.copy(alpha = 0.15f)),
        border = BorderStroke(1.dp, Green.copy(alpha = 0.5f)),
    ) {
        Row(
            Modifier.fillMaxWidth().padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(
                Modifier
                    .size(10.dp)
                    .background(Green, CircleShape),
            )
            Spacer(Modifier.width(10.dp))
            Column(Modifier.weight(1f)) {
                Text("Viagem em andamento", fontWeight = FontWeight.Bold, color = Green)
                Text(
                    "${session.origem ?: "?"} → ${session.destino ?: "?"}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Icon(Icons.Filled.PlayArrow, null, tint = Green)
        }
    }
}

@Composable
private fun TripModeHeroCard(onClick: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth().clickable { onClick() },
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = NavyTopDeep),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
    ) {
        Column(Modifier.fillMaxWidth().padding(20.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Text("🚛", fontSize = 36.sp)
                Spacer(Modifier.width(12.dp))
                Column(Modifier.weight(1f)) {
                    Text(
                        "Modo Viagem",
                        color = Color.White,
                        fontWeight = FontWeight.Bold,
                        fontSize = 20.sp,
                    )
                    Text(
                        "Copiloto digital com IA, GPS e OCR",
                        color = Color.White.copy(alpha = 0.85f),
                        style = MaterialTheme.typography.bodySmall,
                    )
                }
                Icon(Icons.Filled.Psychology, null, tint = BlueLight, modifier = Modifier.size(28.dp))
            }
            Spacer(Modifier.height(16.dp))
            Button(
                onClick = onClick,
                modifier = Modifier.fillMaxWidth().height(52.dp),
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.buttonColors(containerColor = Blue),
            ) {
                Icon(Icons.Filled.LocalShipping, null, Modifier.size(22.dp))
                Spacer(Modifier.width(8.dp))
                Text("INICIAR VIAGEM", fontWeight = FontWeight.Bold, fontSize = 16.sp)
            }
        }
    }
}

/* ============================ Emergência ============================ */

@Composable
private fun EmergencyCard(onClick: () -> Unit) {
    Card(
        modifier = Modifier.fillMaxWidth().clickable { onClick() },
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Red),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
    ) {
        Row(Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 12.dp), verticalAlignment = Alignment.CenterVertically) {
            Surface(shape = CircleShape, color = Color.White.copy(alpha = 0.22f)) {
                Icon(Icons.Filled.Warning, null, tint = Color.White, modifier = Modifier.padding(10.dp).size(22.dp))
            }
            Spacer(Modifier.width(14.dp))
            Column(Modifier.weight(1f)) {
                Text("Emergência", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 17.sp)
                Text("Toque para acionar o alerta", color = Color.White.copy(alpha = 0.9f), style = MaterialTheme.typography.bodySmall)
            }
            Icon(Icons.Filled.KeyboardArrowRight, null, tint = Color.White)
        }
    }
}

/* ============================ Ações rápidas ============================ */

@Composable
private fun QuickActions(
    onAbastecer: () -> Unit,
    onJornada: () -> Unit,
    onPod: () -> Unit,
    onOcorrencia: () -> Unit,
    onChecklist: () -> Unit,
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Column {
            ActionRow(Icons.Filled.LocalGasStation, Blue, "Abastecer", "Registrar abastecimento", onAbastecer)
            ThinDivider()
            ActionRow(Icons.Filled.AccessTime, Cyan, "Jornada", "Iniciar ou encerrar", onJornada)
            ThinDivider()
            ActionRow(Icons.Filled.AssignmentTurnedIn, Green, "Entrega (POD)", "Comprovar entrega", onPod)
            ThinDivider()
            ActionRow(Icons.Filled.Warning, Amber, "Ocorrência", "Acidente, multa, problema", onOcorrencia)
            ThinDivider()
            ActionRow(Icons.Outlined.Checklist, Blue, "Checklist", "Conferir o veículo", onChecklist)
        }
    }
}

@Composable
private fun ActionRow(icon: ImageVector, tint: Color, title: String, desc: String, onClick: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().heightIn(min = 64.dp).clickable { onClick() }.padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Surface(shape = RoundedCornerShape(12.dp), color = tint.copy(alpha = 0.14f)) {
            Icon(icon, null, tint = tint, modifier = Modifier.padding(9.dp).size(22.dp))
        }
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Text(title, fontSize = 16.sp, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
            Text(desc, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        Icon(Icons.Filled.KeyboardArrowRight, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

@Composable
private fun ThinDivider() {
    Divider(Modifier.padding(start = 62.dp), thickness = 0.7.dp, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.08f))
}

/* ============================ Status da jornada ============================ */

@Composable
private fun JourneyStatus(jr: JornadaResumoHoje?, onAbrir: () -> Unit) {
    val estado = jr?.estadoAtual
    val iniciada = estado != null && estado != "fim"
    val cor = if (iniciada) Green else Red
    Card(
        modifier = Modifier.fillMaxWidth().clickable { onAbrir() },
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Column(Modifier.fillMaxWidth().padding(18.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(10.dp).background(cor, CircleShape))
                Spacer(Modifier.width(8.dp))
                Text(
                    if (iniciada) "Jornada em andamento" else "Jornada não iniciada",
                    fontSize = 15.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Spacer(Modifier.height(10.dp))
            if (iniciada && jr != null) {
                Text(jornadaEstadoLabel(estado), fontSize = 26.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface)
                Spacer(Modifier.height(4.dp))
                Text(
                    "${jornadaMin(jr.dirigindoMin)} dirigindo · ${jornadaMin(jr.descansoMin)} parado",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(14.dp))
                OutlinedButton(onClick = onAbrir, modifier = Modifier.fillMaxWidth().height(52.dp), shape = RoundedCornerShape(16.dp)) {
                    Text("Ver jornada", fontSize = 16.sp, fontWeight = FontWeight.SemiBold)
                }
            } else {
                Text("Comece o seu dia", fontSize = 26.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface)
                Spacer(Modifier.height(14.dp))
                Button(onClick = onAbrir, modifier = Modifier.fillMaxWidth().height(56.dp), shape = RoundedCornerShape(16.dp)) {
                    Icon(Icons.Filled.PlayArrow, null)
                    Spacer(Modifier.width(8.dp))
                    Text("Iniciar Jornada", fontSize = 17.sp, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

/* ============================ Financeiro ============================ */

@Composable
private fun FinanceGrid(fretes: Double, comissao: Double, despesas: Double, lucro: Double) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        FinanceCard("Fretes", fretes, Icons.Filled.Route, Blue, Modifier.weight(1f))
        FinanceCard("Comissão", comissao, Icons.Filled.AttachMoney, Cyan, Modifier.weight(1f))
    }
    Spacer(Modifier.height(12.dp))
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
        FinanceCard("Despesas", despesas, Icons.Filled.Receipt, Amber, Modifier.weight(1f))
        FinanceCard("Lucro líquido", lucro, Icons.Filled.TrendingUp, if (lucro >= 0) Green else Red, Modifier.weight(1f), highlight = true)
    }
}

@Composable
private fun FinanceCard(label: String, value: Double, icon: ImageVector, accent: Color, modifier: Modifier = Modifier, highlight: Boolean = false) {
    Card(
        modifier = modifier,
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        Column(Modifier.padding(16.dp)) {
            Surface(shape = RoundedCornerShape(10.dp), color = accent.copy(alpha = 0.14f)) {
                Icon(icon, null, tint = accent, modifier = Modifier.padding(7.dp).size(18.dp))
            }
            Spacer(Modifier.height(12.dp))
            Text(label, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Spacer(Modifier.height(2.dp))
            Text(
                formatMoney(value),
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold,
                color = if (highlight) accent else MaterialTheme.colorScheme.onSurface,
                maxLines = 1,
            )
        }
    }
}

/* ============================ Atividades ============================ */

private data class Atividade(val icon: ImageVector, val tint: Color, val titulo: String, val sub: String, val quando: String)

private fun buildAtividades(data: DashboardData?): List<Atividade> {
    if (data == null) return emptyList()
    val out = mutableListOf<Atividade>()
    data.ultimosAbastecimentos?.take(2)?.forEach {
        out.add(Atividade(Icons.Filled.LocalGasStation, Green, "Abastecimento", "${it.placa ?: "—"} · ${formatMoney(it.valorTotal ?: 0.0)}", it.dataAbastecimento ?: ""))
    }
    data.ultimasRotas?.take(2)?.forEach {
        val trecho = listOfNotNull(it.cidadeOrigemNome, it.cidadeDestinoNome).joinToString(" → ").ifBlank { it.placa ?: "—" }
        out.add(Atividade(Icons.Filled.Route, Blue, "Rota", trecho, it.dataRota ?: it.dataSaida ?: ""))
    }
    data.ultimosChecklists?.take(1)?.forEach {
        out.add(Atividade(Icons.Outlined.Checklist, Amber, "Checklist", it.placa ?: "—", it.dataChecklist ?: ""))
    }
    return out.take(4)
}

@Composable
private fun ActivitiesCard(itens: List<Atividade>) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        if (itens.isEmpty()) {
            Text(
                "Nenhuma atividade recente ainda.",
                Modifier.padding(20.dp),
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        } else {
            Column(Modifier.padding(vertical = 4.dp)) {
                itens.forEachIndexed { i, a ->
                    Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 12.dp), verticalAlignment = Alignment.CenterVertically) {
                        Surface(shape = CircleShape, color = a.tint.copy(alpha = 0.14f)) {
                            Icon(a.icon, null, tint = a.tint, modifier = Modifier.padding(9.dp).size(20.dp))
                        }
                        Spacer(Modifier.width(14.dp))
                        Column(Modifier.weight(1f)) {
                            Text(a.titulo, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface, maxLines = 1)
                            if (a.sub.isNotBlank()) Text(a.sub, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 1)
                        }
                        if (a.quando.isNotBlank()) Text(a.quando, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                    if (i < itens.lastIndex) ThinDivider()
                }
            }
        }
    }
}

/* ============================ Avisos ============================ */

@Composable
private fun AvisosCard(data: DashboardData?, onRotas: () -> Unit, onAbast: () -> Unit, onCheck: () -> Unit) {
    val c = data?.contadores
    val rot = c?.rotasPendentes ?: 0
    val ab = c?.abastecimentosPendentes ?: 0
    val ch = c?.checklistsPendentes ?: 0
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
    ) {
        if (rot == 0 && ab == 0 && ch == 0) {
            Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Filled.CheckCircle, null, tint = Green)
                Spacer(Modifier.width(12.dp))
                Text("Tudo em dia — nenhuma pendência.", fontSize = 15.sp, color = MaterialTheme.colorScheme.onSurface)
            }
        } else {
            Column(Modifier.padding(vertical = 4.dp)) {
                var first = true
                if (rot > 0) { AvisoRow(Icons.Filled.Route, Amber, "$rot rota(s) pendente(s)", "Toque para revisar", onRotas); first = false }
                if (ch > 0) { if (!first) ThinDivider(); AvisoRow(Icons.Outlined.Checklist, Amber, "$ch checklist(s) pendente(s)", "Conferência do veículo", onCheck); first = false }
                if (ab > 0) { if (!first) ThinDivider(); AvisoRow(Icons.Filled.LocalGasStation, Amber, "$ab abastecimento(s) pendente(s)", "Registro incompleto", onAbast) }
            }
        }
    }
}

@Composable
private fun AvisoRow(icon: ImageVector, tint: Color, title: String, sub: String, onClick: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().heightIn(min = 60.dp).clickable { onClick() }.padding(horizontal = 16.dp, vertical = 10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Surface(shape = RoundedCornerShape(12.dp), color = tint.copy(alpha = 0.14f)) {
            Icon(icon, null, tint = tint, modifier = Modifier.padding(9.dp).size(20.dp))
        }
        Spacer(Modifier.width(14.dp))
        Column(Modifier.weight(1f)) {
            Text(title, fontSize = 15.sp, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSurface)
            Text(sub, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        Icon(Icons.Filled.KeyboardArrowRight, null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

/* ============================ Comuns ============================ */

@Composable
private fun SectionTitle(title: String, action: String? = null, onAction: (() -> Unit)? = null) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
        Text(title, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = MaterialTheme.colorScheme.onSurface)
        if (action != null) {
            Text(
                action,
                fontSize = 14.sp,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.primary,
                modifier = Modifier.clickable { onAction?.invoke() },
            )
        }
    }
}

@Composable
private fun ErrorState(msg: String, onRetry: () -> Unit) {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        Card(
            modifier = Modifier.padding(16.dp).fillMaxWidth(),
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer),
        ) {
            Column(Modifier.padding(16.dp)) {
                Text(msg, color = MaterialTheme.colorScheme.onErrorContainer)
                Spacer(Modifier.height(12.dp))
                Text(
                    "Dica: no celular, altere a URL em Configurações para o IP do seu PC. No emulador use 10.0.2.2.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onErrorContainer,
                )
                Spacer(Modifier.height(16.dp))
                Button(onClick = onRetry) { Text("Tentar novamente") }
            }
        }
    }
}

/* ============================ Helpers ============================ */

private fun jornadaEstadoLabel(estado: String?): String = when (estado) {
    "inicio" -> "Dirigindo"
    "parada" -> "Parado"
    "descanso" -> "Em descanso"
    "refeicao" -> "Em refeição"
    "fim" -> "Encerrada"
    else -> "Não iniciada"
}

private fun jornadaMin(min: Int): String {
    val h = min / 60
    val m = min % 60
    return if (h > 0) "${h}h${if (m > 0) " ${m}min" else ""}" else "${m}min"
}
