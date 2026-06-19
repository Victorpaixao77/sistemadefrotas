package com.sistemafrotas.motorista.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.AbastecimentoItem
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

/**
 * Estado da lista de abastecimentos. Sobrevive a rotações de tela e evita
 * recarregar a cada recomposição (o carregamento é disparado uma única vez).
 */
class AbastecimentosViewModel(private val authRepository: AuthRepository) : ViewModel() {

    private val _list = MutableStateFlow<List<AbastecimentoItem>>(emptyList())
    val list: StateFlow<List<AbastecimentoItem>> = _list.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    private var carregouUmaVez = false

    /** Carrega só na primeira vez (ex.: ao entrar na tela). */
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
                authRepository.loadAbastecimentos()
                    .onSuccess { _list.value = it.abastecimentos ?: emptyList() }
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
