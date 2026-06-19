package com.sistemafrotas.motorista.ui.screens

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CameraAlt
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material.icons.filled.TireRepair
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import coil.compose.AsyncImage
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.PneuBuscaData
import com.sistemafrotas.motorista.data.api.VeiculoItem
import com.sistemafrotas.motorista.ui.components.PneusQrScannerDialog
import com.sistemafrotas.motorista.ui.viewmodel.AuthViewModelFactory
import com.sistemafrotas.motorista.ui.viewmodel.PneusViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PneusScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val vm: PneusViewModel = viewModel(
        factory = AuthViewModelFactory(authRepository) { PneusViewModel(it) }
    )
    val loading by vm.loading.collectAsState()
    val saving by vm.saving.collectAsState()
    val error by vm.error.collectAsState()
    val success by vm.success.collectAsState()
    val pneuData by vm.pneuData.collectAsState()
    val veiculos by vm.veiculos.collectAsState()

    // Campos do formulário: estado local da tela.
    var qrInput by remember { mutableStateOf("") }
    var showScanner by remember { mutableStateOf(false) }
    var veiculoId by remember { mutableStateOf<Int?>(null) }
    var pressao by remember { mutableStateOf("") }
    var sulco by remember { mutableStateOf("") }
    var temCorte by remember { mutableStateOf(false) }
    var temBolha by remember { mutableStateOf(false) }
    var obs by remember { mutableStateOf("") }
    var fotoUri by remember { mutableStateOf<Uri?>(null) }

    LaunchedEffect(Unit) { vm.carregarVeiculosSeNecessario() }

    val pickFoto = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        fotoUri = uri
    }
    val takeFoto = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { ok ->
        if (!ok) fotoUri = null
    }

    fun buscar(ref: String) = vm.buscar(ref) { qrInput = it }

    fun salvar() = vm.salvar(
        pressao = pressao,
        sulco = sulco,
        temCorte = temCorte,
        temBolha = temBolha,
        obs = obs,
        veiculoId = veiculoId,
        fotoUri = fotoUri,
        context = context,
    ) {
        pressao = ""
        sulco = ""
        temCorte = false
        temBolha = false
        obs = ""
        fotoUri = null
    }

    if (showScanner) {
        PneusQrScannerDialog(
            onDismiss = { showScanner = false },
            onScanned = { code ->
                showScanner = false
                buscar(code)
            },
        )
    }

    Column(
        modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
    ) {
        Text("Pneus", style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.SemiBold)
        Text(
            "Escaneie o QR ou informe o código para registrar inspeção.",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Spacer(Modifier.height(16.dp))

        OutlinedTextField(
            value = qrInput,
            onValueChange = { qrInput = it },
            label = { Text("QR / token / ID") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )
        Spacer(Modifier.height(8.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Button(
                onClick = { buscar(qrInput) },
                enabled = !loading && qrInput.isNotBlank(),
                modifier = Modifier.weight(1f),
            ) { Text(if (loading) "Buscando…" else "Buscar") }
            FilledTonalButton(onClick = { showScanner = true }) {
                Icon(Icons.Filled.QrCodeScanner, contentDescription = null)
                Spacer(Modifier.width(4.dp))
                Text("QR")
            }
        }

        error?.let {
            Spacer(Modifier.height(8.dp))
            Text(it, color = MaterialTheme.colorScheme.error)
        }
        success?.let {
            Spacer(Modifier.height(8.dp))
            Text(it, color = MaterialTheme.colorScheme.primary)
        }

        pneuData?.let { data ->
            val p = data.pneu
            val c = data.carcaca
            Spacer(Modifier.height(16.dp))
            Card(shape = RoundedCornerShape(12.dp)) {
                Column(Modifier.padding(16.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.TireRepair, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                        Spacer(Modifier.width(8.dp))
                        Text(p.numeroSerie ?: "—", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                    }
                    Text("${p.marca.orEmpty()} ${p.modelo.orEmpty()} · ${p.medida.orEmpty()}")
                    if (c != null) {
                        Spacer(Modifier.height(8.dp))
                        Text("Recapagens: ${c.recapagens ?: 0} · KM: ${c.kmAcumulados ?: "—"}")
                        c.statusCarcaca?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
                    }
                }
            }

            Spacer(Modifier.height(16.dp))
            Text("Checklist de inspeção", fontWeight = FontWeight.SemiBold)

            if (veiculos.isNotEmpty()) {
                var expanded by remember { mutableStateOf(false) }
                ExposedDropdownMenuBox(expanded = expanded, onExpandedChange = { expanded = it }) {
                    OutlinedTextField(
                        value = veiculos.find { it.id == veiculoId }?.let { "${it.placa} — ${it.modelo.orEmpty()}" } ?: "Veículo (opcional)",
                        onValueChange = {},
                        readOnly = true,
                        label = { Text("Veículo") },
                        trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded) },
                        modifier = Modifier.menuAnchor().fillMaxWidth(),
                    )
                    ExposedDropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
                        DropdownMenuItem(
                            text = { Text("— Nenhum —") },
                            onClick = { veiculoId = null; expanded = false },
                        )
                        veiculos.forEach { v ->
                            DropdownMenuItem(
                                text = { Text("${v.placa} — ${v.modelo.orEmpty()}") },
                                onClick = { veiculoId = v.id; expanded = false },
                            )
                        }
                    }
                }
                Spacer(Modifier.height(8.dp))
            }

            OutlinedTextField(
                value = pressao,
                onValueChange = { pressao = it.filter { ch -> ch.isDigit() || ch == '.' } },
                label = { Text("Pressão (PSI)") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = sulco,
                onValueChange = { sulco = it.filter { ch -> ch.isDigit() || ch == '.' } },
                label = { Text("Sulco (mm)") },
                modifier = Modifier.fillMaxWidth(),
            )
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Checkbox(checked = temCorte, onCheckedChange = { temCorte = it })
                Text("Corte visível")
            }
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                Checkbox(checked = temBolha, onCheckedChange = { temBolha = it })
                Text("Bolha")
            }
            OutlinedTextField(
                value = obs,
                onValueChange = { obs = it },
                label = { Text("Observações") },
                modifier = Modifier.fillMaxWidth(),
                minLines = 2,
            )

            Spacer(Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedButton(onClick = { pickFoto.launch("image/*") }) {
                    Icon(Icons.Filled.PhotoCamera, null)
                    Spacer(Modifier.width(4.dp))
                    Text("Galeria")
                }
                OutlinedButton(onClick = {
                    val file = java.io.File(context.cacheDir, "pneu_${System.currentTimeMillis()}.jpg")
                    val uri = androidx.core.content.FileProvider.getUriForFile(
                        context,
                        "${context.packageName}.fileprovider",
                        file,
                    )
                    fotoUri = uri
                    takeFoto.launch(uri)
                }) {
                    Icon(Icons.Filled.CameraAlt, null)
                    Spacer(Modifier.width(4.dp))
                    Text("Câmera")
                }
            }
            fotoUri?.let { uri ->
                Spacer(Modifier.height(8.dp))
                AsyncImage(
                    model = uri,
                    contentDescription = "Foto inspeção",
                    modifier = Modifier.fillMaxWidth().height(160.dp),
                    contentScale = ContentScale.Crop,
                )
            }

            Spacer(Modifier.height(16.dp))
            Button(
                onClick = { salvar() },
                enabled = !saving,
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(if (saving) "Salvando…" else "Salvar inspeção")
            }
        }
    }
}
