package com.sistemafrotas.motorista.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.DesempenhoResponse
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

/** Estado da avaliação/ranking do motorista. */
class DesempenhoViewModel(private val authRepository: AuthRepository) : ViewModel() {

    private val _data = MutableStateFlow<DesempenhoResponse?>(null)
    val data: StateFlow<DesempenhoResponse?> = _data.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    private var carregouUmaVez = false

    fun carregarSeNecessario() {
        if (carregouUmaVez) return
        carregouUmaVez = true
        refresh()
    }

    fun refresh(dias: Int = 30) {
        viewModelScope.launch {
            _refreshing.value = true
            _error.value = null
            try {
                authRepository.loadDesempenho(dias = dias)
                    .onSuccess { _data.value = it }
                    .onFailure { _error.value = it.message }
            } finally {
                _refreshing.value = false
            }
        }
    }

    fun setError(message: String?) {
        _error.value = message
    }
}
