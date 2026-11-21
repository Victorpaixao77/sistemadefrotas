package com.sistemafrotas.motorista.feature.viagem.detectors

import com.sistemafrotas.motorista.feature.viagem.domain.TripPrompt
import java.util.UUID

/**
 * Coordena detectores desacoplados (GPS, posto, pedágio…).
 * Cada detector emite [TripPrompt] para confirmação do motorista.
 */
class TripDetectorCoordinator(
    private val detectors: List<TripDetector> = defaultDetectors(),
) {
    private var onPrompt: ((TripPrompt) -> Unit)? = null

    fun start(onPrompt: (TripPrompt) -> Unit) {
        this.onPrompt = onPrompt
        detectors.forEach { it.start { prompt -> this.onPrompt?.invoke(prompt) } }
    }

    fun stop() {
        detectors.forEach { it.stop() }
        onPrompt = null
    }

    fun onIdleMinutes(minutes: Int) {
        detectors.filterIsInstance<IdleStopPromptDetector>().firstOrNull()?.onIdleMinutes(minutes)
    }

    fun onArrivalAtDestination(city: String) {
        detectors.filterIsInstance<ArrivalPromptDetector>().firstOrNull()?.onArrivalAtDestination(city)
    }

    fun onEnterKnownStation(stationName: String) {
        detectors.filterIsInstance<FuelStationPromptDetector>().firstOrNull()?.onEnterKnownStation(stationName)
    }

    fun onNearTollPlaza(plazaName: String) {
        detectors.filterIsInstance<TollPromptDetector>().firstOrNull()?.onNearTollPlaza(plazaName)
    }

    companion object {
        fun defaultDetectors(): List<TripDetector> = listOf(
            IdleStopPromptDetector(),
            FuelStationPromptDetector(),
            TollPromptDetector(),
            ArrivalPromptDetector(),
        )
    }
}

/** Parada prolongada: alimentado pelo GPS real via TripModeSignals.onIdleDetected (GpsForegroundService). */
class IdleStopPromptDetector : TripDetector {
    override val id: String = "idle_stop"
    private var callback: ((TripPrompt) -> Unit)? = null
    private var fired = false

    override fun start(onPrompt: (TripPrompt) -> Unit) {
        callback = onPrompt
        fired = false
    }

    override fun stop() {
        callback = null
    }

    /** Chamado quando o GPS acusa parado ≥ 5 min (via TripModeSignals). */
    fun onIdleMinutes(minutes: Int) {
        if (fired || minutes < 5) return
        fired = true
        callback?.invoke(
            TripPrompt(
                id = UUID.randomUUID().toString(),
                titulo = "Parada detectada",
                mensagem = "Você ficou parado por $minutes minutos.\nDeseja registrar uma parada?",
                acaoConfirmar = "Sim, registrar",
                fluxo = "parada",
            ),
        )
    }
}

class FuelStationPromptDetector : TripDetector {
    override val id: String = "fuel_station"
    private var callback: ((TripPrompt) -> Unit)? = null

    override fun start(onPrompt: (TripPrompt) -> Unit) {
        callback = onPrompt
    }

    override fun stop() {
        callback = null
    }

    fun onEnterKnownStation(stationName: String) {
        callback?.invoke(
            TripPrompt(
                id = UUID.randomUUID().toString(),
                titulo = "Posto detectado",
                mensagem = "Detectamos que você entrou em $stationName.\nDeseja registrar um abastecimento?",
                acaoConfirmar = "Sim, abrir câmera",
                fluxo = "abastecimento",
            ),
        )
    }
}

class TollPromptDetector : TripDetector {
    override val id: String = "toll_plaza"
    private var callback: ((TripPrompt) -> Unit)? = null

    override fun start(onPrompt: (TripPrompt) -> Unit) {
        callback = onPrompt
    }

    override fun stop() {
        callback = null
    }

    fun onNearTollPlaza(plazaName: String) {
        callback?.invoke(
            TripPrompt(
                id = UUID.randomUUID().toString(),
                titulo = "Pedágio",
                mensagem = "Praça $plazaName detectada.\nRegistrar pedágio?",
                acaoConfirmar = "Sim, registrar",
                fluxo = "pedagio",
            ),
        )
    }
}

class ArrivalPromptDetector : TripDetector {
    override val id: String = "client_arrival"
    private var callback: ((TripPrompt) -> Unit)? = null

    override fun start(onPrompt: (TripPrompt) -> Unit) {
        callback = onPrompt
    }

    override fun stop() {
        callback = null
    }

    fun onArrivalAtDestination(city: String) {
        callback?.invoke(
            TripPrompt(
                id = UUID.randomUUID().toString(),
                titulo = "Chegada ao cliente",
                mensagem = "Chegada em $city.\nAbrir comprovante de entrega?",
                acaoConfirmar = "Sim, abrir POD",
                fluxo = "pod",
            ),
        )
    }
}
