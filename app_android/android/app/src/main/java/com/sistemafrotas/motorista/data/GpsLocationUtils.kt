package com.sistemafrotas.motorista.data

import android.content.Context
import android.location.LocationManager
import android.os.Build

object GpsLocationUtils {

    /** Localização ligada nas configurações do aparelho (não confunde com permissão do app). */
    fun isSystemLocationEnabled(context: Context): Boolean {
        val lm = context.getSystemService(Context.LOCATION_SERVICE) as LocationManager
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            lm.isLocationEnabled
        } else {
            @Suppress("DEPRECATION")
            lm.isProviderEnabled(LocationManager.GPS_PROVIDER) ||
                lm.isProviderEnabled(LocationManager.NETWORK_PROVIDER)
        }
    }
}
