package com.fuse.php.widget

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.widget.RemoteViews
import com.fuse.php.R
import com.fuse.php.media.MediaPlaybackService

class MusicWidgetProvider : AppWidgetProvider() {

    override fun onUpdate(context: Context, appWidgetManager: AppWidgetManager, appWidgetIds: IntArray) {
        for (appWidgetId in appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId)
        }
    }

    override fun onReceive(context: Context, intent: Intent) {
        super.onReceive(context, intent)
        if (intent.action == MediaPlaybackService.ACTION_WIDGET_UPDATE) {
            val appWidgetManager = AppWidgetManager.getInstance(context)
            val appWidgetIds = appWidgetManager.getAppWidgetIds(ComponentName(context, MusicWidgetProvider::class.java))

            val isPlaying = intent.getBooleanExtra(MediaPlaybackService.EXTRA_IS_PLAYING, false)
            val title = intent.getStringExtra(MediaPlaybackService.EXTRA_TITLE)
            val artist = intent.getStringExtra(MediaPlaybackService.EXTRA_ARTIST)

            for (appWidgetId in appWidgetIds) {
                val views = RemoteViews(context.packageName, R.layout.widget_music_player)

                if (title != null) views.setTextViewText(R.id.widget_title, title)
                if (artist != null) views.setTextViewText(R.id.widget_artist, artist)
                val artwork = intent.getStringExtra(MediaPlaybackService.EXTRA_ARTWORK)
                if (artwork != null) {
                    try {
                        views.setImageViewUri(R.id.widget_album_art, android.net.Uri.parse(artwork))
                    } catch (_: Exception) {}
                }

                // Update Play/Pause icon
                val iconRes = if (isPlaying) android.R.drawable.ic_media_pause else android.R.drawable.ic_media_play
                views.setImageViewResource(R.id.widget_btn_play_pause, iconRes)

                // Also refresh the pending intent to be safe
                val toggleIntent = Intent(context, MediaPlaybackService::class.java).apply {
                    action = MediaPlaybackService.ACTION_TOGGLE
                }
                val togglePendingIntent = PendingIntent.getService(
                    context, 0, toggleIntent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )
                views.setOnClickPendingIntent(R.id.widget_btn_play_pause, togglePendingIntent)

                appWidgetManager.updateAppWidget(appWidgetId, views)
            }
        }
    }

    companion object {
        fun updateAppWidget(context: Context, appWidgetManager: AppWidgetManager, appWidgetId: Int) {
            val views = RemoteViews(context.packageName, R.layout.widget_music_player)

            // Play/Pause Intent
            val toggleIntent = Intent(context, MediaPlaybackService::class.java).apply {
                action = MediaPlaybackService.ACTION_TOGGLE
            }
            val togglePendingIntent = PendingIntent.getService(
                context, 0, toggleIntent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.widget_btn_play_pause, togglePendingIntent)

            // Next Intent
            val nextIntent = Intent(context, MediaPlaybackService::class.java).apply {
                action = MediaPlaybackService.ACTION_NEXT
            }
            val nextPendingIntent = PendingIntent.getService(
                context, 1, nextIntent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.widget_btn_next, nextPendingIntent)

            // Previous Intent
            val prevIntent = Intent(context, MediaPlaybackService::class.java).apply {
                action = MediaPlaybackService.ACTION_PREV
            }
            val prevPendingIntent = PendingIntent.getService(
                context, 2, prevIntent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.widget_btn_prev, prevPendingIntent)

            appWidgetManager.updateAppWidget(appWidgetId, views)
        }
    }
}
