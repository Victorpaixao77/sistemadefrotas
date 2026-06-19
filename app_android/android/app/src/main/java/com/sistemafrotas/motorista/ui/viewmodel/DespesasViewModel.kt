package com.sistemafrotas.motorista.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.RotaItem
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

/** Estado da lista de rotas usada na tela de Despesas. Sobrevive a rotações. */
class DespesasViewModel(private val authRepository: AuthRepository) : ViewModel() {

    private val _rotas = MutableStateFlow<List<RotaItem>>(emptyList())
    val rotas: StateFlow<List<RotaItem>> = _rotas.asStateFlow()

    private val _loading = MutableStateFlow(true)
    val loading: StateFlow<Boolean> = _loading.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    private var carregouUmaVez = false

    fun carregarSeNecessario() {
        if (carregouUmaVez) return
        carregouUmaVez = true
        loadRotas()
    }

    fun loadRotas() {
        viewModelScope.launch {
            _refreshing.value = true
            _error.value = null
            try {
                authRepository.loadRotas(limite = 80)
                    .onSuccess { _rotas.value = it.rotas ?: emptyList() }
                    .onFailure { _error.value = it.message }
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
