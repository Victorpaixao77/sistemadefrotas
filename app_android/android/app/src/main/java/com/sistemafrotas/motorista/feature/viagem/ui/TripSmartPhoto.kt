package com.sistemafrotas.motorista.feature.viagem.ui

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.*
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.FileProvider
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.feature.viagem.TripFeatureBridge
import kotlinx.coroutines.launch
import java.io.File

/** Estado do fluxo Foto → OCR automático → abrir tela correta. */
class TripSmartPhotoState internal constructor(
    val processing: Boolean,
    val error: String?,
    val launchCamera: () -> Unit,
    val dismissError: () -> Unit,
)

@Composable
fun rememberTripSmartPhoto(
    authRepository: AuthRepository,
    onOpenFeature: (String) -> Unit,
): TripSmartPhotoState {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var processing by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var cameraUri by remember { mutableStateOf<Uri?>(null) }

    fun processar(uri: Uri?) {
        if (uri == null) return
        processing = true
        error = null
        scope.launch {
            authRepository.lerDocumentoOcrCompleto(context, uri, "auto")
                .onSuccess { result ->
                    TripFeatureBridge.setSmartOcr(result.tipo, result.campos)
                    onOpenFeature(TripFeatureBridge.featureParaOcrTipo(result.tipo))
                    processing = false
                }
                .onFailure { e ->
                    error = e.message ?: "Falha na leitura do documento."
                    processing = false
                }
        }
    }

    val cameraLauncher = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { ok ->
        if (ok) processar(cameraUri)
    }

    val launchCamera: () -> Unit = {
        error = null
        val file = File(context.cacheDir, "trip_ocr_${System.currentTimeMillis()}.jpg")
        val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
        cameraUri = uri
        cameraLauncher.launch(uri)
    }

    return TripSmartPhotoState(
        processing = processing,
        error = error,
        launchCamera = launchCamera,
        dismissError = { error = null },
    )
}
