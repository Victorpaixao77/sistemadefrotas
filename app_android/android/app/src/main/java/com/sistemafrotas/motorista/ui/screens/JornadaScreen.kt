package com.sistemafrotas.motorista.ui.screens

import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.LocationHelper
import com.sistemafrotas.motorista.ui.viewmodel.AuthViewModelFactory
import com.sistemafrotas.motorista.ui.viewmodel.JornadaViewModel
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

private val EVENTOS = listOf(
    "inicio" to "Iniciar jornada",
    "parada" to "Parada",
    "descanso" to "Descanso",
    "refeicao" to "Refeição",
    "fim" to "Fim da jornada",
)

private fun eventoLabel(t: String?): String = EVENTOS.firstOrNull { it.first == t }?.second ?: (t ?: "—")

private fun minToHm(min: Int): String {
    val h = min / 60
    val m = min % 60
    return if (h > 0) "${h}h${if (m > 0) " ${m}min" else ""}" else "${m}min"
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun JornadaScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
) {
    val vm: JornadaViewModel = viewModel(
        factory = AuthViewModelFactory(authRepository) { JornadaViewModel(it) }
    )
    val eventos by vm.eventos.collectAsStateWithLifecycle()
    val resumo by vm.resumo.collectAsStateWithLifecycle()
    val error by vm.error.collectAsStateWithLifecycle()
    val registrando by vm.registrando.collectAsStateWithLifecycle()
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val snackbar = remember { SnackbarHostState() }

    LaunchedEffect(Unit) { vm.carregarSeNecessario() }

    fun registrar(tipo: String) {
        scope.launch {
            val loc = LocationHelper(context).takeIf { it.hasLocationPermission() }?.getLastLocation()
            val dataHora = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(Date())
            vm.registrar(tipo, loc?.first, loc?.second, dataHora) { ok ->
                scope.launch {
                    snackbar.showMessage(if (ok) "Evento registrado." else (error ?: "Falha ao registrar."))
                }
            }
        }
    }

    Scaffold(modifier = modifier, snackbarHost = { SnackbarHost(snackbar) }) { pad ->
        LazyColumn(
            Modifier.fillMaxSize().padding(pad),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            item {
                Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(12.dp)) {
                    Column(Modifier.padding(16.dp)) {
                        Text("Resumo de hoje", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.SemiBold)
                        Spacer(Modifier.height(8.dp))
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("Dirigindo", color = MaterialTheme.colorScheme.onSurfaceVariant)
                            Text(minToHm(resumo?.dirigindoMin ?: 0), fontWeight = FontWeight.Medium)
                        }
                        Spacer(Modifier.height(4.dp))
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text("Descanso/paradas", color = MaterialTheme.colorScheme.onSurfaceVariant)
                            Text(minToHm(resumo?.descansoMin ?: 0), fontWeight = FontWeight.Medium)
                        }
                        resumo?.estadoAtual?.let {
                            Spacer(Modifier.height(8.dp))
                            AssistChip(onClick = { }, label = { Text("Estado atual: ${eventoLabel(it)}") })
                        }
                    }
                }
            }

            item {
                Text("Registrar evento", style = MaterialTheme.typography.labelLarge)
            }
            item {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    EVENTOS.forEach { (value, label) ->
                        Button(
                            onClick = { registrar(value) },
                            enabled = !registrando,
                            modifier = Modifier.fillMaxWidth().height(52.dp),
                        ) {
                            Icon(Icons.Filled.PlayArrow, contentDescription = null)
                            Spacer(Modifier.width(8.dp))
                            Text(label, style = MaterialTheme.typography.titleMedium)
                        }
                    }
                }
            }

            if (error != null && eventos.isEmpty()) {
                item { Text(error ?: "", color = MaterialTheme.colorScheme.error) }
            }

            item {
                Spacer(Modifier.height(4.dp))
                Text("Histórico", style = MaterialTheme.typography.labelLarge)
            }
            if (eventos.isEmpty()) {
                item {
                    Text("Nenhum evento registrado ainda.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            } else {
                items(eventos, key = { it.id }) { ev ->
                    Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(10.dp)) {
                        Row(
                            Modifier.fillMaxWidth().padding(14.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text(eventoLabel(ev.tipo), fontWeight = FontWeight.Medium)
                            Text(ev.dataHora ?: "", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                }
            }
        }
    }
}

private suspend fun SnackbarHostState.showMessage(msg: String) {
    this.showSnackbar(msg)
}
