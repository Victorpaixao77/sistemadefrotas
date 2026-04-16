package com.sistemafrotas.motorista.ui

import androidx.compose.material3.SnackbarHostState
import androidx.compose.runtime.compositionLocalOf

/** Snackbar global (ex.: confirmações). Fornecido em [MainScaffold]; fora dele é null. */
val LocalAppSnackbar = compositionLocalOf<SnackbarHostState?> { null }
