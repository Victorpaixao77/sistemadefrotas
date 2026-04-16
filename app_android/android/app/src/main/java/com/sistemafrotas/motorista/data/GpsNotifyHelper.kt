package com.sistemafrotas.motorista.data

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import com.sistemafrotas.motorista.R

/**
 * Avisos pontuais (permissão, bateria) sem novas telas — canal separado do rastreio em primeiro plano.
 */
object GpsNotifyHelper {

    const val CHANNEL_ALERTS_ID = "gps_alerts"

    private const val ID_LOC = 9101
    private const val ID_NOTIF = 9102
    private const val ID_BATTERY = 9103
    private const val ID_BG_LOC = 9104
    private const val ID_SYS_GPS_OFF = 9105

    fun ensureAlertChannel(context: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val nm = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        val ch = NotificationChannel(
            CHANNEL_ALERTS_ID,
            context.getString(R.string.gps_alerts_channel_name),
            NotificationManager.IMPORTANCE_DEFAULT,
        ).apply {
            description = context.getString(R.string.gps_alerts_channel_desc)
        }
        nm.createNotificationChannel(ch)
    }

    private fun canPost(context: Context): Boolean =
        NotificationManagerCompat.from(context.applicationContext).areNotificationsEnabled()

    private val piFlags: Int
        get() = PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE

    private fun newTask(intent: Intent) = intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)

    /** Ajustes do app (permissões, bateria). */
    private fun appDetailsIntent(ctx: Context): Intent =
        newTask(Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS).apply {
            data = Uri.fromParts("package", ctx.packageName, null)
        })

    private fun activityPi(app: Context, requestCode: Int, intent: Intent): PendingIntent =
        PendingIntent.getActivity(app, requestCode, intent, piFlags)

    fun notifyLocationRequired(context: Context) {
        val app = context.applicationContext
        ensureAlertChannel(app)
        if (!canPost(app)) return
        val contentPi = activityPi(app, 91011, appDetailsIntent(app))
        val n = NotificationCompat.Builder(app, CHANNEL_ALERTS_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_alert)
            .setContentTitle(app.getString(R.string.gps_alert_loc_title))
            .setContentText(app.getString(R.string.gps_alert_loc_text))
            .setContentIntent(contentPi)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .build()
        NotificationManagerCompat.from(app).notify(ID_LOC, n)
    }

    fun notifyNotificationsHelp(context: Context) {
        val app = context.applicationContext
        ensureAlertChannel(app)
        if (!canPost(app)) return
        val notifIntent = newTask(Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).apply {
            putExtra(Settings.EXTRA_APP_PACKAGE, app.packageName)
        })
        val contentPi = activityPi(app, 91022, notifIntent)
        val n = NotificationCompat.Builder(app, CHANNEL_ALERTS_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(app.getString(R.string.gps_alert_notif_title))
            .setContentText(app.getString(R.string.gps_alert_notif_text))
            .setContentIntent(contentPi)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .build()
        NotificationManagerCompat.from(app).notify(ID_NOTIF, n)
    }

    fun notifyBatteryLowWhileTracking(context: Context) {
        val app = context.applicationContext
        ensureAlertChannel(app)
        if (!canPost(app)) return
        val contentPi = activityPi(app, 91033, appDetailsIntent(app))
        val n = NotificationCompat.Builder(app, CHANNEL_ALERTS_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_alert)
            .setContentTitle(app.getString(R.string.gps_alert_battery_title))
            .setContentText(app.getString(R.string.gps_alert_battery_text))
            .setContentIntent(contentPi)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .build()
        NotificationManagerCompat.from(app).notify(ID_BATTERY, n)
    }

    fun notifyBackgroundLocationDenied(context: Context) {
        val app = context.applicationContext
        ensureAlertChannel(app)
        if (!canPost(app)) return
        val contentPi = activityPi(app, 91044, appDetailsIntent(app))
        val n = NotificationCompat.Builder(app, CHANNEL_ALERTS_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentTitle(app.getString(R.string.gps_alert_bg_title))
            .setContentText(app.getString(R.string.gps_alert_bg_text))
            .setContentIntent(contentPi)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .build()
        NotificationManagerCompat.from(app).notify(ID_BG_LOC, n)
    }

    fun notifyTrackingStoppedNoPermission(context: Context) {
        notifyLocationRequired(context)
    }

    /** Localização desligada em Ajustes do aparelho (diferente de só negar permissão ao app). */
    fun notifyGpsSystemDisabled(context: Context) {
        val app = context.applicationContext
        ensureAlertChannel(app)
        if (!canPost(app)) return
        val openLoc = newTask(Intent(Settings.ACTION_LOCATION_SOURCE_SETTINGS))
        val contentPi = activityPi(app, 91050, openLoc)
        val n = NotificationCompat.Builder(app, CHANNEL_ALERTS_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_alert)
            .setContentTitle(app.getString(R.string.gps_alert_sys_gps_title))
            .setContentText(app.getString(R.string.gps_alert_sys_gps_text))
            .setContentIntent(contentPi)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .build()
        NotificationManagerCompat.from(app).notify(ID_SYS_GPS_OFF, n)
    }
}
