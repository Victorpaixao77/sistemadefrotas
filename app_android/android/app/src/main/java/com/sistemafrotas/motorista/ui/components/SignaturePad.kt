package com.sistemafrotas.motorista.ui.components

import android.graphics.Bitmap
import android.graphics.Canvas as AndroidCanvas
import android.graphics.Color as AndroidColor
import android.graphics.Paint
import android.net.Uri
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectDragGestures
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import java.io.File

@Composable
fun SignaturePad(
    modifier: Modifier = Modifier,
    label: String = "Assinatura",
    onCleared: () -> Unit = {},
) {
    val tracos = remember { mutableStateListOf<MutableList<Offset>>() }
    var canvasW by remember { mutableStateOf(0) }
    var canvasH by remember { mutableStateOf(0) }

    Column(modifier, verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(label, style = MaterialTheme.typography.labelLarge)
        Box(
            Modifier
                .fillMaxWidth()
                .height(140.dp)
                .border(1.dp, MaterialTheme.colorScheme.outline, RoundedCornerShape(8.dp)),
        ) {
            Canvas(
                Modifier
                    .fillMaxSize()
                    .pointerInput(Unit) {
                        detectDragGestures(
                            onDragStart = { offset ->
                                tracos.add(mutableListOf(offset))
                            },
                            onDrag = { change, _ ->
                                tracos.lastOrNull()?.add(change.position)
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
                            strokeWidth = 4f,
                            cap = StrokeCap.Round,
                        )
                    }
                }
            }
        }
        TextButton(onClick = { tracos.clear(); onCleared() }) { Text("Limpar assinatura") }
    }

    // Expor via CompositionLocal ou callback — usamos remember com holder
    DisposableEffect(Unit) {
        onDispose { }
    }
}

@Composable
fun rememberSignatureUri(): Pair<() -> Uri?, @Composable () -> Unit> {
    val context = LocalContext.current
    val tracos = remember { mutableStateListOf<MutableList<Offset>>() }
    var canvasW by remember { mutableStateOf(0) }
    var canvasH by remember { mutableStateOf(0) }

    val toUri: () -> Uri? = {
        if (tracos.isEmpty() || canvasW <= 0 || canvasH <= 0) null
        else {
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
            Uri.fromFile(f)
        }
    }

    val ui: @Composable () -> Unit = {
        Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text("Assinatura", style = MaterialTheme.typography.labelLarge)
            Box(
                Modifier
                    .fillMaxWidth()
                    .height(140.dp)
                    .border(1.dp, MaterialTheme.colorScheme.outline, RoundedCornerShape(8.dp)),
            ) {
                Canvas(
                    Modifier
                        .fillMaxSize()
                        .pointerInput(Unit) {
                            detectDragGestures(
                                onDragStart = { tracos.add(mutableListOf(it)) },
                                onDrag = { change, _ -> tracos.lastOrNull()?.add(change.position) },
                            )
                        },
                ) {
                    canvasW = size.width.toInt()
                    canvasH = size.height.toInt()
                    tracos.forEach { traco ->
                        for (i in 1 until traco.size) {
                            drawLine(Color.Black, traco[i - 1], traco[i], 4f, cap = StrokeCap.Round)
                        }
                    }
                }
            }
            TextButton(onClick = { tracos.clear() }) { Text("Limpar assinatura") }
        }
    }
    return toUri to ui
}
