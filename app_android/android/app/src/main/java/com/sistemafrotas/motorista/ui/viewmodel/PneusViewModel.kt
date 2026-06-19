package com.sistemafrotas.motorista.ui.viewmodel

import android.content.Context
import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.api.PneuBuscaData
import com.sistemafrotas.motorista.data.api.VeiculoItem
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

/**
 * Estado de rede da tela de Pneus (busca por QR + registro de inspeção).
 * Os campos do formulário (pressão, sulco, etc.) permanecem locais na tela;
 * aqui ficam só os dados que vêm/vão para a API e os estados de operação,
 * que sobrevivem a rotações.
 */
class PneusViewModel(private val authRepository: AuthRepository) : ViewModel() {

    private val _veiculos = MutableStateFlow<List<VeiculoItem>>(emptyList())
    val veiculos: StateFlow<List<VeiculoItem>> = _veiculos.asStateFlow()

    private val _pneuData = MutableStateFlow<PneuBuscaData?>(null)
    val pneuData: StateFlow<PneuBuscaData?> = _pneuData.asStateFlow()

    private val _loading = MutableStateFlow(false)
    val loading: StateFlow<Boolean> = _loading.asStateFlow()

    private val _saving = MutableStateFlow(false)
    val saving: StateFlow<Boolean> = _saving.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    private val _success = MutableStateFlow<String?>(null)
    val success: StateFlow<String?> = _success.asStateFlow()

    private var carregouVeiculos = false

    fun carregarVeiculosSeNecessario() {
        if (carregouVeiculos) return
        carregouVeiculos = true
        viewModelScope.launch {
            authRepository.loadPneusVeiculos().onSuccess { _veiculos.value = it.veiculos ?: emptyList() }
        }
    }

    /** Busca o pneu pela referência do QR. [onFound] recebe a referência usada (para refletir no input). */
    fun buscar(ref: String, onFound: (String) -> Unit) {
        if (ref.isBlank()) return
        viewModelScope.launch {
            _loading.value = true
            _error.value = null
            _success.value = null
            authRepository.buscarPneuQr(ref)
                .onSuccess {
                    _pneuData.value = it
                    onFound(ref)
                }
                .onFailure { _error.value = it.message }
            _loading.value = false
        }
    }

    /** Registra a inspeção. [onSaved] é chamado em caso de sucesso para a tela limpar o formulário. */
    fun salvar(
        pressao: String,
        sulco: String,
        temCorte: Boolean,
        temBolha: Boolean,
        obs: String,
        veiculoId: Int?,
        fotoUri: Uri?,
        context: Context,
        onSaved: () -> Unit,
    ) {
        val pid = _pneuData.value?.pneu?.id ?: return
        viewModelScope.launch {
            _saving.value = true
            _error.value = null
            _success.value = null
            val body = mutableMapOf<String, Any?>(
                "pneu_id" to pid,
                "pressao_psi" to pressao.toDoubleOrNull(),
                "sulco_mm" to sulco.toDoubleOrNull(),
                "tem_corte" to if (temCorte) 1 else 0,
                "tem_bolha" to if (temBolha) 1 else 0,
                "observacoes" to obs.ifBlank { null },
            )
            veiculoId?.let { body["veiculo_id"] = it }
            authRepository.registrarInspecaoPneu(body)
                .onSuccess { inspecaoId ->
                    val uri = fotoUri
                    if (uri != null) {
                        authRepository.uploadFotoPneu(pid, "inspecao", context, uri)
                            .onFailure { _error.value = "Inspeção #$inspecaoId salva, mas foto falhou: ${it.message}" }
                            .onSuccess { _success.value = "Inspeção e foto registradas!" }
                    } else {
                        _success.value = "Inspeção registrada!"
                    }
                    onSaved()
                }
                .onFailure { _error.value = it.message }
            _saving.value = false
        }
    }

    fun setError(message: String?) {
        _error.value = message
    }

    fun setSuccess(message: String?) {
        _success.value = message
    }
}
