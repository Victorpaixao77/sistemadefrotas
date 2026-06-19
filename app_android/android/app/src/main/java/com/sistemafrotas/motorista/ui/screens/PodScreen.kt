package com.sistemafrotas.motorista.ui.screens

import android.graphics.Bitmap
import android.graphics.Canvas as AndroidCanvas
import android.graphics.Color as AndroidColor
import android.graphics.Paint
import android.net.Uri
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.LocationHelper
import com.sistemafrotas.motorista.ui.components.FotoPicker
import kotlinx.coroutines.launch
import java.io.File
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun PodScreen(
    authRepository: AuthRepository,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val snackbar = remember { SnackbarHostState() }

    var recebedorNome by remember { mutableStateOf("") }
    var recebedorDoc by remember { mutableStateOf("") }
    var observacoes by remember { mutableStateOf("") }
    var fotoNf by remember { mutableStateOf<Uri?>(null) }
    var fotoCarga by remember { mutableStateOf<Uri?>(null) }
    var gps by remember { mutableStateOf<Pair<Double, Double>?>(null) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    // Assinatura: lista de traços (cada traço = lista de pontos em px) + tamanho do canvas.
    val tracos = remember { mutableStateListOf<MutableList<Offset>>() }
    var canvasW by remember { mutableStateOf(0) }
    var canvasH by remember { mutableStateOf(0) }

    LaunchedEffect(Unit) {
        LocationHelper(context).takeIf { it.hasLocationPermission() }?.getLastLocation()?.let { gps = it }
    }

    fun assinaturaParaUri(): Uri? {
        if (tracos.isEmpty() || canvasW <= 0 || canvasH <= 0) return null
        val bmp = Bitmap.createBitmap(canvasW, canvasH, Bitmap.Config.ARGB_8888)
        val c = AndroidCanvas(bmp)
        c.drawColor(AndroidColor.WHITE)
        val paint = Paint().apply {
            color = AndroidColor.BLACK
            strokeWidth = 5f
            style = Paint.Style.STROKE
            strokeCap = Paint.Cap.ROUND
            isAntiAlias = true
        }
        tracos.forEach { traco ->
            for (i in 1 until traco.size) {
                c.drawLine(traco[i - 1].x, traco[i - 1].y, traco[i].x, traco[i].y, paint)
            }
        }
        val dir = File(context.cacheDir, "assinaturas").apply { mkdirs() }
        val f = File(dir, "assin_${System.currentTimeMillis()}.png")
        f.outputStream().use { bmp.compress(Bitmap.CompressFormat.PNG, 90, it) }
        return Uri.fromFile(f)
    }

    Scaffold(modifier = modifier, snackbarHost = { SnackbarHost(snackbar) }) { pad ->
        Column(
            Modifier.fillMaxSize().padding(pad).verticalScroll(rememberScrollState()).padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text("Comprovante de entrega", style = MaterialTheme.typography.titleMedium)

            OutlinedTextField(
                value = recebedorNome, onValueChange = { recebedorNome = it },
                label = { Text("Nome de quem recebeu") }, modifier = Modifier.fillMaxWidth(),
            )
            OutlinedTextField(
                value = recebedorDoc, onValueChange = { recebedorDoc = it },
                label = { Text("Documento do recebedor (opcional)") }, modifier = Modifier.fillMaxWidth(),
            )

            FotoPicker(label = "Foto da nota fiscal", uri = fotoNf, onUri = { fotoNf = it }, modifier = Modifier.fillMaxWidth())
            FotoPicker(label = "Foto da carga entregue", uri = fotoCarga, onUri = { fotoCarga = it }, modifier = Modifier.fillMaxWidth())

            Text("Assinatura do recebedor", style = MaterialTheme.typography.labelLarge)
            Canvas(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(180.dp)
                    .border(1.dp, MaterialTheme.colorScheme.outline, RoundedCornerShape(8.dp))
                    .pointerInput(Unit) {
                        detectDragGestures(
                            onDragStart = { offset -> tracos.add(mutableListOf(offset)) },
                            onDrag = { change, _ ->
                                if (tracos.isNotEmpty()) tracos.last().add(change.position)
                            },
                        )
                    },
            ) {
                canvasW = size.width.toInt()
                canvasH = size.height.toInt()
                tracos.forEach { traco ->
                    for (i in 1 until traco.size) {
                        drawLine(
                            color = Color.Black,
                            start = traco[i - 1],
                            end = traco[i],
                            strokeWidth = 5f,
                            cap = StrokeCap.Round,
                        )
                    }
                }
            }
            TextButton(onClick = { tracos.clear() }) { Text("Limpar assinatura") }

            OutlinedTextField(
                value = observacoes, onValueChange = { observacoes = it },
                label = { Text("Observações (opcional)") }, modifier = Modifier.fillMaxWidth(),
            )

            Text(
                gps?.let { "Local da entrega: %.5f, %.5f".format(Locale.US, it.first, it.second) }
                    ?: "Localização não disponível.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )

            if (error != null) Text(error!!, color = MaterialTheme.colorScheme.error)

            Button(
                onClick = {
                    if (recebedorNome.isBlank() && fotoNf == null && fotoCarga == null && tracos.isEmpty()) {
                        error = "Preencha ao menos o recebedor, uma foto ou a assinatura."
                        return@Button
                    }
                    loading = true
                    error = null
                    val dataHora = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(Date())
                    val assinaturaUri = assinaturaParaUri()
                    val body = mapOf<String, Any?>(
                        "recebedor_nome" to recebedorNome.ifBlank { null },
                        "recebedor_doc" to recebedorDoc.ifBlank { null },
                        "observacoes" to observacoes.ifBlank { null },
                        "data_hora" to dataHora,
                        "latitude" to gps?.first,
                        "longitude" to gps?.second,
                    )
                    scope.launch {
                        val r = authRepository.criarComprovante(body, context, fotoNf, fotoCarga, assinaturaUri)
                        loading = false
                        if (r.isSuccess) {
                            recebedorNome = ""; recebedorDoc = ""; observacoes = ""
                            fotoNf = null; fotoCarga = null; tracos.clear()
                            snackbar.showSnackbar("Comprovante de entrega registrado.")
                        } else {
                            error = r.exceptionOrNull()?.message ?: "Falha ao registrar."
                        }
                    }
                },
                enabled = !loading,
                modifier = Modifier.fillMaxWidth().height(54.dp),
            ) {
                if (loading) CircularProgressIndicator(Modifier.size(22.dp), color = MaterialTheme.colorScheme.onPrimary)
                else Text("Registrar entrega", style = MaterialTheme.typography.titleMedium)
            }
        }
    }
}
