package com.sistemafrotas.motorista

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Surface
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.lifecycleScope
import com.sistemafrotas.motorista.data.GpsNotifyHelper
import com.sistemafrotas.motorista.data.GpsSystemLocationNotifier
import com.sistemafrotas.motorista.data.AuthDataStore
import com.sistemafrotas.motorista.data.AuthRepository
import com.sistemafrotas.motorista.data.OutboxSyncScheduler
import com.sistemafrotas.motorista.data.api.Api
import com.sistemafrotas.motorista.data.local.DatabaseProvider
import com.sistemafrotas.motorista.data.local.LocalCache
import com.sistemafrotas.motorista.ui.NavHostApp
import com.sistemafrotas.motorista.ui.theme.SistemaFrotasMotoristaTheme
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        val dataStore = AuthDataStore(applicationContext)
        val localCache = LocalCache(DatabaseProvider.get(applicationContext))
        val authRepo = AuthRepository(dataStore, localCache, applicationContext)
        Api.authRepository = authRepo
        GpsNotifyHelper.ensureAlertChannel(applicationContext)

        setContent {
            SistemaFrotasMotoristaTheme {
                Surface(modifier = Modifier.fillMaxSize()) {
                    val lifecycleOwner = LocalLifecycleOwner.current
                    DisposableEffect(lifecycleOwner) {
                        val obs = LifecycleEventObserver { _, event ->
                            if (event == Lifecycle.Event.ON_RESUME) {
                                OutboxSyncScheduler.kickOnce(this@MainActivity)
                                this@MainActivity.lifecycleScope.launch(Dispatchers.IO) {
                                    GpsSystemLocationNotifier.onPossibleGpsOff(this@MainActivity)
                                }
                            }
                        }
                        lifecycleOwner.lifecycle.addObserver(obs)
                        onDispose { lifecycleOwner.lifecycle.removeObserver(obs) }
                    }
                    var tokenRestored by remember { mutableStateOf(false) }
                    LaunchedEffect(Unit) {
                        withContext(Dispatchers.IO) {
                            authRepo.restoreToken()
                            authRepo.loadApiBaseUrl()
                        }
                        tokenRestored = true
                    }
                    if (!tokenRestored) {
                        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                            CircularProgressIndicator()
                        }
                    } else {
                        NavHostApp(authRepository = authRepo)
                    }
                }
            }
        }
    }
}
