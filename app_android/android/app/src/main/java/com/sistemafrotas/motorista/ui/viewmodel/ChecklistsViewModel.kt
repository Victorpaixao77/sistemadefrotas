package com.sistemafrotas.motorista.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.ChecklistItem
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

/** Estado da lista de checklists. Sobrevive a rotações de tela. */
class ChecklistsViewModel(private val authRepository: AuthRepository) : ViewModel() {

    private val _list = MutableStateFlow<List<ChecklistItem>>(emptyList())
    val list: StateFlow<List<ChecklistItem>> = _list.asStateFlow()

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
                authRepository.loadChecklists()
                    .onSuccess { _list.value = it.checklists ?: emptyList() }
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
