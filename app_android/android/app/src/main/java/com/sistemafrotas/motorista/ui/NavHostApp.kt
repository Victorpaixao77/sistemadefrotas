package com.sistemafrotas.motorista.ui

import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.ui.screens.LoginScreen
import com.sistemafrotas.motorista.ui.screens.MainScaffold

@Composable
fun NavHostApp(authRepository: AuthRepository) {
    val navController = rememberNavController()
    var showLogoutSuccessMessage by remember { mutableStateOf(false) }

    val initialState by authRepository.isLoggedIn.collectAsState(initial = null)

    if (initialState == null) return

    val startDest = remember { if (initialState == true) "main" else "login" }

    NavHost(
        navController = navController,
        startDestination = startDest,
    ) {
        composable("login") {
            LoginScreen(
                onLoginSuccess = {
                    navController.navigate("main") {
                        popUpTo("login") { inclusive = true }
                    }
                },
                authRepository = authRepository,
                logoutSuccessMessage = if (showLogoutSuccessMessage) "Você foi desconectado com sucesso." else null,
                onLogoutMessageShown = { showLogoutSuccessMessage = false },
            )
        }
        composable("main") {
            MainScaffold(
                authRepository = authRepository,
                onLogout = {
                    showLogoutSuccessMessage = true
                    navController.navigate("login") {
                        popUpTo("main") { inclusive = true }
                    }
                },
                onSessionExpired = {
                    navController.navigate("login") {
                        popUpTo("main") { inclusive = true }
                    }
                },
            )
        }
    }
}
