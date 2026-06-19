package com.sistemafrotas.motorista.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.JornadaEvento
import com.sistemafrotas.motorista.data.api.JornadaResumoHoje
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

/** Estado da jornada do motorista (eventos + resumo do dia). Sobrevive a rotações. */
class JornadaViewModel(private val authRepository: AuthRepository) : ViewModel() {

    private val _eventos = MutableStateFlow<List<JornadaEvento>>(emptyList())
    val eventos: StateFlow<List<JornadaEvento>> = _eventos.asStateFlow()

    private val _resumo = MutableStateFlow<JornadaResumoHoje?>(null)
    val resumo: StateFlow<JornadaResumoHoje?> = _resumo.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    private val _registrando = MutableStateFlow(false)
    val registrando: StateFlow<Boolean> = _registrando.asStateFlow()

    private var carregouUmaVez = false

    fun carregarSeNecessario() {
        if (carregouUmaVez) return
        carregouUmaVez = true
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            _refreshing.value = true
            _error.value = null
            try {
                authRepository.loadJornada()
                    .onSuccess {
                        _eventos.value = it.eventos ?: emptyList()
                        _resumo.value = it.resumoHoje
                    }
                    .onFailure { _error.value = it.message }
            } finally {
                _refreshing.value = false
            }
        }
    }

    /** Registra um evento (inicio/parada/descanso/refeicao/fim). */
    fun registrar(tipo: String, latitude: Double?, longitude: Double?, dataHora: String, onDone: (Boolean) -> Unit) {
        viewModelScope.launch {
            _registrando.value = true
            _error.value = null
            val body = mapOf<String, Any?>(
                "tipo" to tipo,
                "data_hora" to dataHora,
                "latitude" to latitude,
                "longitude" to longitude,
            )
            val ok = authRepository.registrarJornada(body)
                .onSuccess { refresh() }
                .onFailure { _error.value = it.message }
                .isSuccess
            _registrando.value = false
            onDone(ok)
        }
    }

    fun setError(message: String?) {
        _error.value = message
    }
}
