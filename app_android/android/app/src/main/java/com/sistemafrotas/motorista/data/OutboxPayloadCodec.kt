package com.sistemafrotas.motorista.data

import org.json.JSONObject

object OutboxPayloadCodec {

    fun mapToJson(m: Map<String, Any?>): String {
        val j = JSONObject()
        for ((k, v) in m) {
            when (v) {
                null -> j.put(k, JSONObject.NULL)
                is Boolean -> j.put(k, v)
                is Int -> j.put(k, v)
                is Long -> j.put(k, v)
                is Float -> j.put(k, v.toDouble())
                is Double -> j.put(k, v)
                is Number -> j.put(k, v.toDouble())
                else -> j.put(k, v.toString())
            }
        }
        return j.toString()
    }

    @Suppress("UNCHECKED_CAST")
    fun jsonToMap(json: String): Map<String, Any?> {
        val j = JSONObject(json)
        val out = mutableMapOf<String, Any?>()
        val keys = j.keys()
        while (keys.hasNext()) {
            val k = keys.next()
            if (j.isNull(k)) {
                out[k] = null
                continue
            }
            when (val v = j.get(k)) {
                is Number -> {
                    val d = v.toDouble()
                    out[k] = if (d == d.toLong().toDouble() && d >= Long.MIN_VALUE && d <= Long.MAX_VALUE) {
                        d.toLong()
                    } else {
                        d
                    }
                }
                is Boolean -> out[k] = v
                else -> out[k] = v.toString()
            }
        }
        return out
    }
}
