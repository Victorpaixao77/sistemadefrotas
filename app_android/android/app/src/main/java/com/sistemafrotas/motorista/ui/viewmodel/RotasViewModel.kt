package com.sistemafrotas.motorista.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.RotaItem
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class RotasViewModel(private val authRepository: AuthRepository) : ViewModel() {

    private val _list = MutableStateFlow<List<RotaItem>>(emptyList())
    val list: StateFlow<List<RotaItem>> = _list.asStateFlow()

    private val _refreshing = MutableStateFlow(false)
    val refreshing: StateFlow<Boolean> = _refreshing.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    private val _statusFilter = MutableStateFlow<String?>(null)
    val statusFilter: StateFlow<String?> = _statusFilter.asStateFlow()

    private val _dataInicio = MutableStateFlow<String?>(null)
    val dataInicio: StateFlow<String?> = _dataInicio.asStateFlow()

    private val _dataFim = MutableStateFlow<String?>(null)
    val dataFim: StateFlow<String?> = _dataFim.asStateFlow()

    fun setStatusFilter(status: String?) {
        _statusFilter.value = status?.takeIf { it.isNotBlank() }
    }

    fun setIntervaloDatas(inicio: String?, fim: String?) {
        _dataInicio.value = inicio?.takeIf { it.isNotBlank() }
        _dataFim.value = fim?.takeIf { it.isNotBlank() }
    }

    fun refreshList() {
        viewModelScope.launch {
            _refreshing.value = true
            _error.value = null
            try {
                authRepository.loadRotas(
                    status = _statusFilter.value,
                    dataInicio = _dataInicio.value,
                    dataFim = _dataFim.value,
                    limite = 80,
                )
                    .onSuccess { _list.value = it.rotas ?: emptyList() }
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
