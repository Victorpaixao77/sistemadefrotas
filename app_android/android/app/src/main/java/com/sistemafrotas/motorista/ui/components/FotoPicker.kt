package com.sistemafrotas.motorista.ui.components

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.PhotoLibrary
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.core.content.FileProvider
import java.io.File

/**
 * Seletor de foto reutilizável: oferece **tirar foto** (câmera) ou **escolher da galeria**.
 * Em campo, o motorista normalmente quer a câmera. Reaproveita o FileProvider do app.
 */
@Composable
fun FotoPicker(
    label: String,
    uri: Uri?,
    onUri: (Uri?) -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    var cameraUri by remember { mutableStateOf<Uri?>(null) }

    val gallery = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { picked ->
        if (picked != null) onUri(picked)
    }
    val camera = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { ok ->
        if (ok) onUri(cameraUri)
    }

    Column(modifier) {
        Text(label, style = MaterialTheme.typography.labelLarge)
        Spacer(Modifier.height(6.dp))
        if (uri != null) {
            Text("Foto selecionada ✓", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.primary)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                TextButton(onClick = {
                    val f = File(context.cacheDir, "foto_${System.currentTimeMillis()}.jpg")
                    val u = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", f)
                    cameraUri = u
                    camera.launch(u)
                }) { Text("Trocar (câmera)") }
                TextButton(onClick = { gallery.launch("image/*") }) { Text("Galeria") }
                TextButton(onClick = { onUri(null) }) { Text("Remover") }
            }
        } else {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedButton(
                    onClick = {
                        val f = File(context.cacheDir, "foto_${System.currentTimeMillis()}.jpg")
                        val u = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", f)
                        cameraUri = u
                        camera.launch(u)
                    },
                    modifier = Modifier.weight(1f),
                ) {
                    Icon(Icons.Filled.PhotoCamera, contentDescription = null)
                    Spacer(Modifier.width(6.dp))
                    Text("Câmera")
                }
                OutlinedButton(
                    onClick = { gallery.launch("image/*") },
                    modifier = Modifier.weight(1f),
                ) {
                    Icon(Icons.Filled.PhotoLibrary, contentDescription = null)
                    Spacer(Modifier.width(6.dp))
                    Text("Galeria")
                }
            }
        }
    }
}
