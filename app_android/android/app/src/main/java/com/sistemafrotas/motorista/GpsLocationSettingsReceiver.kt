package com.sistemafrotas.motorista

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import com.sistemafrotas.motorista.data.GpsSystemLocationNotifier
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking

/**
 * Reage ao motorista ligar/desligar localização no sistema (sem nova tela).
 */
class GpsLocationSettingsReceiver : BroadcastReceiver() {

    override fun onReceive(context: Context, intent: Intent?) {
        val pending = goAsync()
        val app = context.applicationContext
        Thread {
            try {
                runBlocking(Dispatchers.IO) {
                    GpsSystemLocationNotifier.onPossibleGpsOff(app)
                }
            } finally {
                pending.finish()
            }
        }.start()
    }
}
