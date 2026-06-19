package com.sistemafrotas.motorista.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.LocationHelper
import com.sistemafrotas.motorista.data.api.PanicoAlerta
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PanicoScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val snackbar = remember { SnackbarHostState() }
    var enviando by remember { mutableStateOf(false) }
    var confirmar by remember { mutableStateOf(false) }
    var historico by remember { mutableStateOf<List<PanicoAlerta>>(emptyList()) }

    suspend fun recarregar() {
        authRepository.loadPanicoAlertas().onSuccess { historico = it.alertas ?: emptyList() }
    }
    LaunchedEffect(Unit) { recarregar() }

    if (confirmar) {
        AlertDialog(
            onDismissRequest = { confirmar = false },
            icon = { Icon(Icons.Filled.Warning, contentDescription = null, tint = Color(0xFFDC2626)) },
            title = { Text("Acionar pânico?") },
            text = { Text("Sua localização será enviada imediatamente à central. Use apenas em emergência real.") },
            confirmButton = {
                Button(
                    onClick = {
                        confirmar = false
                        enviando = true
                        scope.launch {
                            val loc = LocationHelper(context).takeIf { it.hasLocationPermission() }?.getLastLocation()
                            val dataHora = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(Date())
                            val body = mapOf<String, Any?>(
                                "data_hora" to dataHora,
                                "latitude" to loc?.first,
                                "longitude" to loc?.second,
                            )
                            val r = authRepository.acionarPanico(body)
                            enviando = false
                            snackbar.showSnackbar(
                                if (r.isSuccess) "🚨 Alerta enviado. A central foi acionada."
                                else (r.exceptionOrNull()?.message ?: "Falha ao enviar o alerta.")
                            )
                            recarregar()
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFDC2626)),
                ) { Text("Confirmar emergência") }
            },
            dismissButton = { TextButton(onClick = { confirmar = false }) { Text("Cancelar") } },
        )
    }

    Scaffold(modifier = modifier, snackbarHost = { SnackbarHost(snackbar) }) { pad ->
        Column(
            Modifier.fillMaxSize().padding(pad).padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Spacer(Modifier.height(16.dp))
            Surface(
                shape = CircleShape,
                color = if (enviando) Color(0xFF9CA3AF) else Color(0xFFDC2626),
                modifier = Modifier.size(200.dp),
                onClick = { if (!enviando) confirmar = true },
            ) {
                Box(contentAlignment = Alignment.Center) {
                    if (enviando) {
                        CircularProgressIndicator(color = Color.White)
                    } else {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Icon(Icons.Filled.Warning, contentDescription = null, tint = Color.White, modifier = Modifier.size(56.dp))
                            Spacer(Modifier.height(8.dp))
                            Text("PÂNICO", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 22.sp)
                        }
                    }
                }
            }
            Spacer(Modifier.height(16.dp))
            Text(
                "Pressione o botão em caso de emergência (acidente, roubo, ameaça). Sua localização será enviada à central.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                textAlign = TextAlign.Center,
            )

            if (historico.isNotEmpty()) {
                Spacer(Modifier.height(24.dp))
                Text("Acionamentos recentes", style = MaterialTheme.typography.labelLarge, modifier = Modifier.align(Alignment.Start))
                Spacer(Modifier.height(8.dp))
                historico.take(10).forEach { a ->
                    Card(Modifier.fillMaxWidth().padding(vertical = 3.dp), shape = RoundedCornerShape(8.dp)) {
                        Row(
                            Modifier.fillMaxWidth().padding(12.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text(a.dataHora ?: "", style = MaterialTheme.typography.bodySmall)
                            AssistChip(onClick = { }, label = { Text(a.status ?: "aberto") })
                        }
                    }
                }
            }
        }
    }
}
