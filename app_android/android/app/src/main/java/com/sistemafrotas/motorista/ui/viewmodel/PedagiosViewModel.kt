package com.sistemafrotas.motorista.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.PedagioItem
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

/** Estado da lista de pedágios e total dos últimos 30 dias. Sobrevive a rotações. */
class PedagiosViewModel(private val authRepository: AuthRepository) : ViewModel() {

    private val _list = MutableStateFlow<List<PedagioItem>>(emptyList())
    val list: StateFlow<List<PedagioItem>> = _list.asStateFlow()

    private val _total30d = MutableStateFlow(0.0)
    val total30d: StateFlow<Double> = _total30d.asStateFlow()

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

    fun refresh() {
        viewModelScope.launch {
            _refreshing.value = true
            _error.value = null
            try {
                authRepository.loadPedagios()
                    .onSuccess {
                        _list.value = it.pedagios ?: emptyList()
                        _total30d.value = it.total30d
                    }
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
