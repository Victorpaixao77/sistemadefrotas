package com.sistemafrotas.motorista.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.DashboardData
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

/** Estado do dashboard. Sobrevive a rotações e evita recarregar a cada recomposição. */
class DashboardViewModel(private val authRepository: AuthRepository) : ViewModel() {

    private val _data = MutableStateFlow<DashboardData?>(null)
    val data: StateFlow<DashboardData?> = _data.asStateFlow()

    private val _loading = MutableStateFlow(true)
    val loading: StateFlow<Boolean> = _loading.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    private var carregouUmaVez = false

    private fun isSessionMsg(msg: String) = msg.contains("expirad", ignoreCase = true) ||
        msg.contains("inválido", ignoreCase = true) ||
        msg.contains("Sessão expirada", ignoreCase = true)

    fun carregarSeNecessario() {
        if (carregouUmaVez) return
        carregouUmaVez = true
        refresh(false)
    }

    fun refresh(isPull: Boolean = false) {
        viewModelScope.launch {
            if (isPull) _refreshing.value = true else _loading.value = true
            try {
                authRepository.loadDashboardResult()
                    .onSuccess {
                        _data.value = it
                        _error.value = null
                    }
                    .onFailure { e ->
                        val msg = e.message ?: ""
                        when {
                            _data.value == null -> _error.value = msg
                            isSessionMsg(msg) -> _error.value = msg
                        }
                    }
            } finally {
                _loading.value = false
                _refreshing.value = false
            }
        }
    }

    fun setError(message: String?) {
        _error.value = message
    }
}
