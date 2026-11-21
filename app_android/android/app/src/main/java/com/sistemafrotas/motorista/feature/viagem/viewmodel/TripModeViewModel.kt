package com.sistemafrotas.motorista.feature.viagem.viewmodel

import android.Manifest
import android.app.Application
import android.content.pm.PackageManager
import android.location.Geocoder
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.RotaDetalheItem
import com.sistemafrotas.motorista.data.api.RotaItem
import com.sistemafrotas.motorista.feature.viagem.data.TripSessionStore
import com.sistemafrotas.motorista.feature.viagem.data.TripTimelineStore
import com.sistemafrotas.motorista.GpsForegroundService
import com.sistemafrotas.motorista.data.api.ViagemContextData
import com.sistemafrotas.motorista.feature.viagem.TripGeoHelper
import com.sistemafrotas.motorista.feature.viagem.TripModeSignals
import com.sistemafrotas.motorista.feature.viagem.detectors.TripDetectorCoordinator
import com.sistemafrotas.motorista.feature.viagem.domain.TripEvent
import com.sistemafrotas.motorista.feature.viagem.domain.TripPhase
import com.sistemafrotas.motorista.feature.viagem.domain.TripPrompt
import com.sistemafrotas.motorista.feature.viagem.domain.TripSession
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.concurrent.TimeUnit

data class TripModeUiState(
    val loading: Boolean = true,
    val starting: Boolean = false,
    val error: String? = null,
    val session: TripSession? = null,
    val proximaRota: RotaItem? = null,
    val rotaDetalhe: RotaDetalheItem? = null,
    val motoristaNome: String? = null,
    val events: List<TripEvent> = emptyList(),
    val elapsedLabel: String = "00:00",
    val prompt: TripPrompt? = null,
    val showAiSheet: Boolean = false,
    val showReport: Boolean = false,
    val abastecimentosCount: Int = 0,
    val jornadaDirigindoMin: Int = 0,
    val jornadaParadoMin: Int = 0,
)

class TripModeViewModel(
    private val app: Application,
    private val authRepository: AuthRepository,
) : ViewModel() {

    private val sessionStore = TripSessionStore(app)
    private val timelineStore = TripTimelineStore(app)
    private val detectorCoordinator = TripDetectorCoordinator()

    private val _ui = MutableStateFlow(TripModeUiState())
    val ui: StateFlow<TripModeUiState> = _ui.asStateFlow()

    private val _pendingFluxo = MutableStateFlow<String?>(null)
    val pendingFluxo: StateFlow<String?> = _pendingFluxo.asStateFlow()

    private var clockJob: Job? = null
    private var idleSignalsJob: Job? = null
    private var gpsSignalsJob: Job? = null
    private var remindersJob: Job? = null
    private var tripSideEffectsJob: Job? = null
    private var arrivalFired = false
    private val reminderFired = mutableSetOf<String>()
    private val poiFired = mutableSetOf<String>()
    private val cercaInside = mutableSetOf<Int>()
    private var viagemContext: ViagemContextData? = null

    init {
        viewModelScope.launch {
            authRepository.nome.collect { nome ->
                _ui.update { it.copy(motoristaNome = nome) }
            }
        }
        viewModelScope.launch {
            sessionStore.sessionFlow.collect { session ->
                when {
                    session?.phase == TripPhase.CONCLUIDA -> {
                        _ui.update { it.copy(session = session, showReport = true, loading = false, starting = false) }
                        stopClock()
                        stopDetectors()
                    }
                    session != null -> {
                        if (session.phase != TripPhase.EM_VIAGEM) return@collect
                        _ui.update { it.copy(session = session, loading = false, starting = false) }
                        startClock(session.startedAtMs)
                        startDetectors()
                        loadDetalhe(session.rotaId)
                    }
                    else -> {
                        _ui.update { it.copy(session = null) }
                        stopClock()
                        stopDetectors()
                    }
                }
            }
        }
        viewModelScope.launch {
            timelineStore.eventsFlow.collect { events ->
                _ui.update { it.copy(events = events) }
            }
        }
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            _ui.update { it.copy(loading = true, error = null) }
            try {
                val dash = authRepository.loadDashboardResult()
                val rotas = authRepository.loadRotas()
                val jornada = authRepository.loadJornada()
                dash.onFailure { e -> _ui.update { s -> s.copy(error = e.message) } }
                rotas.onFailure { e -> _ui.update { s -> s.copy(error = e.message) } }
                val hoje = dash.getOrNull()?.rotasHoje?.firstOrNull()
                    ?: rotas.getOrNull()?.rotas?.firstOrNull()
                val jr = jornada.getOrNull()?.resumoHoje
                _ui.update {
                    it.copy(
                        proximaRota = hoje,
                        jornadaDirigindoMin = jr?.dirigindoMin ?: 0,
                        jornadaParadoMin = jr?.descansoMin ?: 0,
                    )
                }
                _ui.value.session?.let { loadDetalhe(it.rotaId) }
            } finally {
                _ui.update { it.copy(loading = false) }
            }
        }
    }

    fun iniciarViagem() {
        val rota = _ui.value.proximaRota ?: return
        viewModelScope.launch {
            _ui.update { it.copy(starting = true, error = null) }
            try {
                val session = TripSession(
                    rotaId = rota.id,
                    phase = TripPhase.EM_VIAGEM,
                    startedAtMs = System.currentTimeMillis(),
                    origem = rota.cidadeOrigemNome,
                    destino = rota.cidadeDestinoNome,
                    placa = rota.placa,
                    motorista = _ui.value.motoristaNome,
                )
                sessionStore.save(session)
                timelineStore.clear()
                reminderFired.clear()
                arrivalFired = false
                poiFired.clear()
                cercaInside.clear()
                viagemContext = null
                timelineStore.addEvent(rota.id, "inicio", "Viagem iniciada", session.origem)
                _ui.update {
                    it.copy(
                        session = session,
                        starting = false,
                        loading = false,
                    )
                }
                // Rede e GPS em background — não bloqueiam a tela.
                tripSideEffectsJob?.cancel()
                tripSideEffectsJob = launch {
                    authRepository.loadRotaDetalhe(rota.id).onSuccess { det ->
                        if (_ui.value.session?.phase != TripPhase.EM_VIAGEM) return@onSuccess
                        val enriched = session.copy(
                            origem = session.origem ?: det.cidadeOrigemNome,
                            destino = session.destino ?: det.cidadeDestinoNome,
                            carga = det.descricaoCarga,
                            placa = session.placa ?: det.placa,
                            kmInicio = det.kmSaida,
                        )
                        sessionStore.save(enriched)
                        _ui.update { it.copy(rotaDetalhe = det, session = enriched) }
                        tryStartGps(rota.veiculoId ?: det.veiculoId)
                    }.onFailure {
                        if (_ui.value.session?.phase == TripPhase.EM_VIAGEM) {
                            tryStartGps(rota.veiculoId)
                        }
                    }
                }
                launch {
                    runCatching {
                        authRepository.registrarJornada(mapOf("tipo" to "inicio", "rota_id" to rota.id))
                    }
                }
            } catch (e: Exception) {
                _ui.update {
                    it.copy(
                        starting = false,
                        loading = false,
                        error = e.message ?: "Não foi possível iniciar a viagem.",
                    )
                }
            }
        }
    }

    fun encerrarViagem() {
        val session = _ui.value.session ?: return
        if (session.phase != TripPhase.EM_VIAGEM) return
        val ended = session.copy(
            phase = TripPhase.CONCLUIDA,
            endedAtMs = System.currentTimeMillis(),
        )
        tripSideEffectsJob?.cancel()
        stopDetectors()
        stopClock()
        // UI primeiro — evita desmontar LazyColumn/Scaffold de forma abrupta.
        _ui.update {
            it.copy(
                session = ended,
                showReport = true,
                starting = false,
                loading = false,
            )
        }
        viewModelScope.launch {
            try {
                timelineStore.addEvent(session.rotaId, "fim", "Viagem encerrada", session.destino)
                sessionStore.save(ended)
                scheduleTimelineSync()
            } catch (_: Exception) {
                // Relatório já está na tela; persistência falhou mas não derruba o app.
            }
            launch {
                runCatching {
                    authRepository.registrarJornada(mapOf("tipo" to "fim", "rota_id" to session.rotaId))
                }
            }
        }
    }

    fun fecharRelatorio(onDone: (() -> Unit)? = null) {
        tripSideEffectsJob?.cancel()
        stopDetectors()
        stopClock()
        viewModelScope.launch {
            try {
                sessionStore.clear()
                timelineStore.clear()
            } catch (_: Exception) {
            }
            _ui.update { it.copy(showReport = false, session = null, rotaDetalhe = null) }
            onDone?.invoke()
        }
    }

    fun dismissPrompt() {
        _ui.update { it.copy(prompt = null) }
    }

    fun dismissError() {
        _ui.update { it.copy(error = null) }
    }

    fun confirmPromptAndOpen() {
        val prompt = _ui.value.prompt ?: return
        dismissPrompt()
        onPromptConfirmed(prompt.fluxo)
    }

    fun consumeFluxo(): String? {
        val f = _pendingFluxo.value
        _pendingFluxo.value = null
        return f
    }

    fun onPromptConfirmed(fluxo: String) {
        val session = _ui.value.session ?: return
        viewModelScope.launch {
            when (fluxo) {
                "parada" -> {
                    timelineStore.addEvent(session.rotaId, "parada", "Parada registrada")
                    authRepository.registrarJornada(mapOf("tipo" to "parada", "rota_id" to session.rotaId))
                }
                "abastecimento" -> timelineStore.addEvent(session.rotaId, "abastecimento", "Abastecimento")
                "pedagio" -> timelineStore.addEvent(session.rotaId, "pedagio", "Pedágio")
                "pod" -> timelineStore.addEvent(session.rotaId, "entrega", "Entrega — POD")
            }
            fluxoToFeatureKey(fluxo)?.let { _pendingFluxo.value = it }
            scheduleTimelineSync()
        }
    }

    private fun fluxoToFeatureKey(fluxo: String): String? = when (fluxo) {
        "abastecimento" -> "abastecimentos"
        "pedagio" -> "pedagios"
        "pod" -> "pod"
        else -> null
    }

    fun toggleAiSheet(open: Boolean) {
        _ui.update { it.copy(showAiSheet = open) }
    }

    fun responderAssistente(pergunta: String, onResult: (String) -> Unit) {
        val local = respostaLocalRapida(pergunta)
        if (local != null) {
            onResult(local)
            return
        }
        viewModelScope.launch {
            authRepository.askMotoristaAssistente(pergunta, _ui.value.session?.rotaId)
                .onSuccess { onResult(it) }
                .onFailure { onResult(it.message ?: "Não foi possível consultar o assistente.") }
        }
    }

    private fun respostaLocalRapida(pergunta: String): String? {
        val d = _ui.value.rotaDetalhe
        val p = pergunta.lowercase(Locale.getDefault())
        return when {
            p.contains("falta") && p.contains("km") -> {
                val total = d?.distanciaKm ?: d?.totalKm
                if (total != null) "Distância prevista: ${"%.0f".format(total)} km."
                else "KM previsto não informado na rota."
            }
            p.contains("gastei") || p.contains("despesa") -> {
                val total = d?.despesas?.sumOf { it.totalDespviagem ?: 0.0 } ?: 0.0
                "Despesas na rota: R$ ${"%.2f".format(total)}."
            }
            p.contains("consumo") -> "Abastecimentos: ${d?.abastecimentos?.size ?: 0}."
            p.contains("descanso") || p.contains("jornada") ->
                "Dirigindo: ${_ui.value.jornadaDirigindoMin} min · Parado: ${_ui.value.jornadaParadoMin} min."
            p.contains("pendente") && p.contains("abastec") ->
                if ((d?.abastecimentos?.size ?: 0) == 0) "Sem abastecimento registrado."
                else "Abastecimentos OK nesta viagem."
            else -> null
        }
    }

    fun formatTime(ms: Long): String =
        SimpleDateFormat("HH:mm", Locale("pt", "BR")).format(Date(ms))

    fun buildReportText(state: TripModeUiState = _ui.value): String {
        val s = state.session
        val lines = buildList {
            add("Relatório da viagem — Frotec")
            add("Origem: ${s?.origem ?: "—"}")
            add("Destino: ${s?.destino ?: "—"}")
            add("Placa: ${s?.placa ?: "—"}")
            add("Duração: ${state.elapsedLabel}")
            add("Abastecimentos: ${state.abastecimentosCount}")
            add("Dirigindo: ${state.jornadaDirigindoMin} min · Parado: ${state.jornadaParadoMin} min")
            add("")
            add("Linha do tempo:")
            state.events.forEach { ev ->
                add("• ${formatTime(ev.timestampMs)} — ${ev.titulo}${ev.detalhe?.let { ": $it" } ?: ""}")
            }
        }
        return lines.joinToString("\n")
    }

    private fun loadDetalhe(rotaId: Int) {
        viewModelScope.launch {
            authRepository.loadRotaDetalhe(rotaId).onSuccess { det ->
                if (_ui.value.session?.phase != TripPhase.EM_VIAGEM) return@onSuccess
                _ui.update {
                    it.copy(rotaDetalhe = det, abastecimentosCount = det.abastecimentos?.size ?: 0)
                }
            }
        }
    }

    private fun startClock(startedAtMs: Long) {
        clockJob?.cancel()
        clockJob = viewModelScope.launch {
            while (true) {
                val diff = System.currentTimeMillis() - startedAtMs
                val h = TimeUnit.MILLISECONDS.toHours(diff)
                val m = TimeUnit.MILLISECONDS.toMinutes(diff) % 60
                val sec = TimeUnit.MILLISECONDS.toSeconds(diff) % 60
                val label = if (h > 0) "%d:%02d".format(h, m) else "%02d:%02d".format(m, sec)
                _ui.update { it.copy(elapsedLabel = label) }
                delay(1000)
            }
        }
    }

    private fun stopClock() {
        clockJob?.cancel()
        clockJob = null
    }

    private fun startDetectors() {
        stopDetectors()
        arrivalFired = false
        detectorCoordinator.start { prompt ->
            _ui.update { state ->
                if (state.prompt == null) state.copy(prompt = prompt) else state
            }
        }
        idleSignalsJob = viewModelScope.launch {
            TripModeSignals.idleMinutes.collect { min ->
                detectorCoordinator.onIdleMinutes(min)
            }
        }
        gpsSignalsJob = viewModelScope.launch {
            TripModeSignals.gpsPoints.collect { point ->
                onGpsPoint(point)
            }
        }
        remindersJob = viewModelScope.launch {
            while (true) {
                delay(60_000)
                checkReminders()
            }
        }
        viewModelScope.launch {
            val rotaId = _ui.value.session?.rotaId ?: return@launch
            authRepository.loadViagemContext(rotaId).onSuccess { viagemContext = it }
        }
    }

    private fun scheduleTimelineSync() {
        viewModelScope.launch {
            val events = _ui.value.events
            if (events.isEmpty()) return@launch
            authRepository.syncTripTimeline(events)
        }
    }

    private suspend fun onGpsPoint(point: TripModeSignals.GpsPoint) {
        val ctx = viagemContext

        if (point.speedKmh < 30) {
            ctx?.postos?.let { postos ->
                TripGeoHelper.findNearbyPoi(point.lat, point.lng, postos)?.let { poi ->
                    val key = "posto_${poi.nome}_${"%.3f".format(poi.lat)}"
                    if (poiFired.add(key)) {
                        detectorCoordinator.onEnterKnownStation(poi.nome)
                    }
                }
            }
            ctx?.pedagios?.let { pedagios ->
                TripGeoHelper.findNearbyPoi(point.lat, point.lng, pedagios, 180.0)?.let { poi ->
                    val key = "ped_${poi.nome}_${"%.3f".format(poi.lat)}"
                    if (poiFired.add(key)) {
                        detectorCoordinator.onNearTollPlaza(poi.nome)
                    }
                }
            }
        }

        ctx?.cercas?.forEach { cerca ->
            val inside = TripGeoHelper.isInsideCerca(point.lat, point.lng, cerca)
            when {
                inside && cercaInside.add(cerca.id) -> {
                    val session = _ui.value.session ?: return@forEach
                    timelineStore.addEvent(session.rotaId, "geofence", "Entrou em ${cerca.nome}", cerca.nome)
                    scheduleTimelineSync()
                }
                !inside -> cercaInside.remove(cerca.id)
            }
        }

        if (arrivalFired) return
        val destLat = ctx?.destinoLat
        val destLng = ctx?.destinoLng
        if (destLat != null && destLng != null && point.speedKmh <= 25) {
            if (TripGeoHelper.distanceMeters(point.lat, point.lng, destLat, destLng) < 1500) {
                arrivalFired = true
                detectorCoordinator.onArrivalAtDestination(ctx?.destino ?: "destino")
                return
            }
        }

        if (point.speedKmh > 20) return
        val dest = _ui.value.session?.destino?.trim().orEmpty()
        if (dest.isBlank()) return
        val city = withContext(Dispatchers.IO) { resolveCity(point.lat, point.lng) } ?: return
        if (TripGeoHelper.cityMatchesDestination(dest, city)) {
            arrivalFired = true
            detectorCoordinator.onArrivalAtDestination(city)
        }
    }

    private fun resolveCity(lat: Double, lng: Double): String? {
        return try {
            val geocoder = Geocoder(app, Locale("pt", "BR"))
            @Suppress("DEPRECATION")
            val addr = geocoder.getFromLocation(lat, lng, 1)?.firstOrNull() ?: return null
            addr.locality?.takeIf { it.isNotBlank() }
                ?: addr.subAdminArea?.takeIf { it.isNotBlank() }
                ?: addr.adminArea?.takeIf { it.isNotBlank() }
        } catch (_: Exception) {
            null
        }
    }

    private fun checkReminders() {
        val session = _ui.value.session ?: return
        if (session.phase != TripPhase.EM_VIAGEM) return
        val elapsedMin = ((System.currentTimeMillis() - session.startedAtMs) / 60_000).toInt()
        if (_ui.value.jornadaDirigindoMin >= 240 && reminderFired.add("descanso")) {
            offerReminder(
                titulo = "Hora do descanso",
                mensagem = "Você já dirigiu ${_ui.value.jornadaDirigindoMin} min hoje.\nConsidere uma pausa de descanso.",
                fluxo = "parada",
                acaoConfirmar = "Registrar pausa",
            )
        }
        if (_ui.value.abastecimentosCount == 0 && elapsedMin >= 120 && reminderFired.add("abastec")) {
            offerReminder(
                titulo = "Abastecimento pendente",
                mensagem = "Nenhum abastecimento registrado nesta viagem.\nDeseja registrar agora?",
                fluxo = "abastecimento",
                acaoConfirmar = "Sim, abrir",
            )
        }
    }

    private fun offerReminder(titulo: String, mensagem: String, fluxo: String, acaoConfirmar: String) {
        _ui.update { state ->
            if (state.prompt != null) state
            else state.copy(
                prompt = TripPrompt(
                    id = "reminder_${fluxo}_${System.currentTimeMillis()}",
                    titulo = titulo,
                    mensagem = mensagem,
                    acaoConfirmar = acaoConfirmar,
                    fluxo = fluxo,
                ),
            )
        }
    }

    private fun stopDetectors() {
        idleSignalsJob?.cancel()
        idleSignalsJob = null
        gpsSignalsJob?.cancel()
        gpsSignalsJob = null
        remindersJob?.cancel()
        remindersJob = null
        detectorCoordinator.stop()
    }

    private fun tryStartGps(veiculoId: Int?) {
        val vid = veiculoId ?: return
        if (vid <= 0) return
        val hasLoc = ContextCompat.checkSelfPermission(app, Manifest.permission.ACCESS_FINE_LOCATION) ==
            PackageManager.PERMISSION_GRANTED
        if (!hasLoc) return
        GpsForegroundService.start(app, vid)
    }

    override fun onCleared() {
        tripSideEffectsJob?.cancel()
        stopDetectors()
        stopClock()
        super.onCleared()
    }
}

class TripModeViewModelFactory(
    private val app: Application,
    private val authRepository: AuthRepository,
) : androidx.lifecycle.ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T =
        TripModeViewModel(app, authRepository) as T
}
