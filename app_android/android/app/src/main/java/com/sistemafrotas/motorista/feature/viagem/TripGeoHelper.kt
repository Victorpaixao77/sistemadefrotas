package com.sistemafrotas.motorista.feature.viagem

import com.sistemafrotas.motorista.data.api.ViagemCerca
import com.sistemafrotas.motorista.data.api.ViagemPoi
import kotlin.math.atan2
import kotlin.math.cos
import kotlin.math.sin
import kotlin.math.sqrt

object TripGeoHelper {
    fun distanceMeters(lat1: Double, lng1: Double, lat2: Double, lng2: Double): Double {
        val r = 6_371_000.0
        val dLat = Math.toRadians(lat2 - lat1)
        val dLng = Math.toRadians(lng2 - lng1)
        val a = sin(dLat / 2) * sin(dLat / 2) +
            cos(Math.toRadians(lat1)) * cos(Math.toRadians(lat2)) *
            sin(dLng / 2) * sin(dLng / 2)
        return r * 2 * atan2(sqrt(a), sqrt(1 - a))
    }

    fun findNearbyPoi(lat: Double, lng: Double, pois: List<ViagemPoi>, radiusM: Double = 220.0): ViagemPoi? =
        pois.firstOrNull { p ->
            distanceMeters(lat, lng, p.lat, p.lng) <= radiusM
        }

    fun isInsideCerca(lat: Double, lng: Double, cerca: ViagemCerca): Boolean =
        distanceMeters(lat, lng, cerca.lat, cerca.lng) <= cerca.raioMetros

    fun cityMatchesDestination(destino: String, city: String): Boolean {
        val d = destino.lowercase()
        val c = city.lowercase()
        if (d.contains(c) || c.contains(d)) return true
        val firstWord = d.split(" ", "-").firstOrNull { it.length > 3 } ?: return false
        return c.contains(firstWord)
    }
}
