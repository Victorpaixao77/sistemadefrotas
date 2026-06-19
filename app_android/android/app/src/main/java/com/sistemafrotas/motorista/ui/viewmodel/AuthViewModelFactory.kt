package com.sistemafrotas.motorista.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import com.sistemafrotas.motorista.data.AuthRepository

/**
 * Factory genérica para ViewModels que dependem apenas do [AuthRepository].
 * Evita criar uma factory por tela.
 *
 * Uso:
 *   val vm: AbastecimentosViewModel = viewModel(
 *       factory = AuthViewModelFactory(authRepository) { AbastecimentosViewModel(it) }
 *   )
 */
class AuthViewModelFactory(
    private val authRepository: AuthRepository,
    private val creator: (AuthRepository) -> ViewModel,
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T = creator(authRepository) as T
}
