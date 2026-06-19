package com.sistemafrotas.motorista.ui.components

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.PhotoLibrary
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.core.content.FileProvider
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.OcrCampos
import kotlinx.coroutines.launch
import java.io.File

/**
 * Botão "preencher por foto (IA)": tira foto (câmera/galeria) do documento, chama o
 * OCR de visão no servidor e devolve os campos lidos para o formulário pré-preencher.
 * O motorista sempre revisa antes de salvar.
 *
 * @param tipo "abastecimento" | "painel" | "pedagio"
 */
@Composable
fun OcrLeitorButton(
    authRepository: AuthRepository,
    tipo: String,
    titulo: String,
    descricao: String,
    onResultado: (OcrCampos) -> Unit,
    onImagem: ((Uri) -> Unit)? = null,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var loading by remember { mutableStateOf(false) }
    var erro by remember { mutableStateOf<String?>(null) }
    var cameraUri by remember { mutableStateOf<Uri?>(null) }

    fun processar(uri: Uri?) {
        if (uri == null) return
        onImagem?.invoke(uri)
        loading = true
        erro = null
        scope.launch {
            authRepository.lerDocumentoOcr(context, uri, tipo)
                .onSuccess { campos -> onResultado(campos); loading = false }
                .onFailure { e -> erro = e.message ?: "Falha na leitura."; loading = false }
        }
    }

    val gallery = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { picked ->
        processar(picked)
    }
    val camera = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { ok ->
        if (ok) processar(cameraUri)
    }

    fun abrirCamera() {
        val f = File(context.cacheDir, "ocr_${System.currentTimeMillis()}.jpg")
        val u = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", f)
        cameraUri = u
        camera.launch(u)
    }

    Card(
        modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.secondaryContainer),
    ) {
        Column(Modifier.padding(14.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Filled.AutoAwesome, contentDescription = null, tint = MaterialTheme.colorScheme.onSecondaryContainer)
                Spacer(Modifier.width(8.dp))
                Column(Modifier.weight(1f)) {
                    Text(titulo, fontWeight = FontWeight.SemiBold, color = MaterialTheme.colorScheme.onSecondaryContainer)
                    Text(descricao, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSecondaryContainer)
                }
            }
            Spacer(Modifier.height(10.dp))
            if (loading) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onSecondaryContainer)
                    Spacer(Modifier.width(10.dp))
                    Text("Lendo documento com IA…", color = MaterialTheme.colorScheme.onSecondaryContainer)
                }
            } else {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Button(onClick = { abrirCamera() }, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Filled.PhotoCamera, contentDescription = null)
                        Spacer(Modifier.width(6.dp))
                        Text("Câmera")
                    }
                    OutlinedButton(onClick = { gallery.launch("image/*") }, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Filled.PhotoLibrary, contentDescription = null)
                        Spacer(Modifier.width(6.dp))
                        Text("Galeria")
                    }
                }
            }
            if (erro != null) {
                Spacer(Modifier.height(6.dp))
                Text(erro!!, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
            }
        }
    }
}
