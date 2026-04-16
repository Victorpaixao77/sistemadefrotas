package com.sistemafrotas.motorista.data

import android.content.Context

/**
 * Aviso quando o motorista desliga a localização no sistema, com throttle para não spammar.
 */
object GpsSystemLocationNotifier {

    private const val THROTTLE_MS = 30 * 60 * 1000L

    suspend fun onPossibleGpsOff(context: Context) {
        val app = context.applicationContext
        val prefs = GpsPreferencesStore(app)
        val (trackingOn, _) = prefs.getTracking()
        if (!trackingOn) return
        if (GpsLocationUtils.isSystemLocationEnabled(app)) return
        val last = prefs.getLastSystemGpsOffWarnMillis()
        if (System.currentTimeMillis() - last < THROTTLE_MS) return
        prefs.setLastSystemGpsOffWarnMillis()
        GpsNotifyHelper.notifyGpsSystemDisabled(app)
    }
}
