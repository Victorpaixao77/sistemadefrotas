package com.sistemafrotas.motorista.feature.viagem.detectors

import com.sistemafrotas.motorista.feature.viagem.domain.TripPrompt

/** Módulo independente de detecção durante o Modo Viagem. */
interface TripDetector {
    val id: String
    fun start(onPrompt: (TripPrompt) -> Unit)
    fun stop()
}
