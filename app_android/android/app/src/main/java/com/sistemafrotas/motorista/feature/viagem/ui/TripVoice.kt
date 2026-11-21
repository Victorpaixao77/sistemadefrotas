package com.sistemafrotas.motorista.feature.viagem.ui

import android.Manifest
import android.app.Activity
import android.content.Intent
import android.content.pm.PackageManager
import android.speech.RecognizerIntent
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.*
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.ContextCompat
import com.sistemafrotas.motorista.feature.viagem.TripFeatureBridge
import java.util.Locale

class TripVoiceState internal constructor(
    val listening: Boolean,
    val error: String?,
    val launch: () -> Unit,
    val dismissError: () -> Unit,
)

@Composable
fun rememberTripVoice(
    onOpenFeature: (String) -> Unit,
): TripVoiceState {
    val context = LocalContext.current
    var listening by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    val speechLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.StartActivityForResult(),
    ) { result ->
        listening = false
        if (result.resultCode == Activity.RESULT_OK) {
            val text = result.data
                ?.getStringArrayListExtra(RecognizerIntent.EXTRA_RESULTS)
                ?.firstOrNull()
                ?.trim()
            if (!text.isNullOrBlank()) {
                TripFeatureBridge.setVoiceOcorrencia(text)
                onOpenFeature("ocorrencias")
            } else {
                error = "Não foi possível entender o áudio. Tente novamente."
            }
        }
    }

    val permLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission(),
    ) { granted ->
        if (granted) {
            listening = true
            val intent = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
                putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM)
                putExtra(RecognizerIntent.EXTRA_LANGUAGE, Locale("pt", "BR"))
                putExtra(RecognizerIntent.EXTRA_PROMPT, "Descreva a ocorrência")
                putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 1)
            }
            try {
                speechLauncher.launch(intent)
            } catch (_: Exception) {
                listening = false
                error = "Reconhecimento de voz não disponível neste aparelho."
            }
        } else {
            error = "Permissão de microfone necessária para registrar por voz."
        }
    }

    val launch: () -> Unit = {
        error = null
        val intent = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
            putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM)
            putExtra(RecognizerIntent.EXTRA_LANGUAGE, Locale("pt", "BR"))
            putExtra(RecognizerIntent.EXTRA_PROMPT, "Descreva a ocorrência")
            putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 1)
        }
        when {
            ContextCompat.checkSelfPermission(context, Manifest.permission.RECORD_AUDIO) ==
                PackageManager.PERMISSION_GRANTED -> {
                listening = true
                try {
                    speechLauncher.launch(intent)
                } catch (_: Exception) {
                    listening = false
                    error = "Reconhecimento de voz não disponível neste aparelho."
                }
            }
            else -> permLauncher.launch(Manifest.permission.RECORD_AUDIO)
        }
    }

    return TripVoiceState(
        listening = listening,
        error = error,
        launch = launch,
        dismissError = { error = null },
    )
}
