package com.sistemafrotas.motorista.feature.viagem

import kotlinx.coroutines.flow.MutableSharedFlow
import kotlinx.coroutines.flow.SharedFlow
import kotlinx.coroutines.flow.asSharedFlow

/**
 * Ponte leve entre serviços Android (GPS) e o Modo Viagem, sem acoplar o serviço ao ViewModel.
 */
object TripModeSignals {
    private val _idleMinutes = MutableSharedFlow<Int>(extraBufferCapacity = 1)
    val idleMinutes: SharedFlow<Int> = _idleMinutes.asSharedFlow()

    data class GpsPoint(val lat: Double, val lng: Double, val speedKmh: Double)

    private val _gpsPoints = MutableSharedFlow<GpsPoint>(extraBufferCapacity = 1)
    val gpsPoints: SharedFlow<GpsPoint> = _gpsPoints.asSharedFlow()

    fun onIdleDetected(minutes: Int) {
        if (minutes >= 5) _idleMinutes.tryEmit(minutes)
    }

    fun onGpsPoint(lat: Double, lng: Double, speedKmh: Double) {
        _gpsPoints.tryEmit(GpsPoint(lat, lng, speedKmh))
    }
}
