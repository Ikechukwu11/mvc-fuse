package com.fuse.php.media

import android.app.PendingIntent
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import androidx.media3.common.MediaItem
import androidx.media3.common.MediaMetadata
import androidx.media3.common.Player
import androidx.media3.exoplayer.ExoPlayer
import androidx.media3.session.MediaSession
import androidx.media3.session.MediaSessionService
import com.fuse.php.ui.MainActivity

class MediaPlaybackService : MediaSessionService() {
    private var mediaSession: MediaSession? = null
    private lateinit var player: ExoPlayer

    companion object {
        const val ACTION_PLAY = "com.fuse.php.media.PLAY"
        const val ACTION_PAUSE = "com.fuse.php.media.PAUSE"
        const val ACTION_RESUME = "com.fuse.php.media.RESUME"
        const val ACTION_TOGGLE = "com.fuse.php.media.TOGGLE"
        const val ACTION_NEXT = "com.fuse.php.media.NEXT"
        const val ACTION_PREV = "com.fuse.php.media.PREV"
        const val ACTION_WIDGET_UPDATE = "com.fuse.php.media.WIDGET_UPDATE"

        const val EXTRA_URL = "url"
        const val EXTRA_TITLE = "title"
        const val EXTRA_ARTIST = "artist"
        const val EXTRA_ARTWORK = "artwork"
        const val EXTRA_IS_PLAYING = "is_playing"
    }

    override fun onCreate() {
        super.onCreate()
        player = ExoPlayer.Builder(this).build()

        player.addListener(object : Player.Listener {
            override fun onMediaItemTransition(mediaItem: MediaItem?, reason: Int) {
                broadcastWidgetUpdate()
            }

            override fun onIsPlayingChanged(isPlaying: Boolean) {
                broadcastWidgetUpdate()
            }
        })

        // Open MainActivity when notification is clicked
        val intent = Intent(this, MainActivity::class.java)
        val pendingIntent = PendingIntent.getActivity(
            this, 0, intent, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )

        mediaSession = MediaSession.Builder(this, player)
            .setSessionActivity(pendingIntent)
            .build()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        super.onStartCommand(intent, flags, startId)

        when (intent?.action) {
            ACTION_PLAY -> {
                val url = intent.getStringExtra(EXTRA_URL)
                val title = intent.getStringExtra(EXTRA_TITLE) ?: "Unknown Title"
                val artist = intent.getStringExtra(EXTRA_ARTIST) ?: "Unknown Artist"
                val artwork = intent.getStringExtra(EXTRA_ARTWORK)

                if (url != null) {
                    playMedia(url, title, artist, artwork)
                }
            }
            ACTION_PAUSE -> player.pause()
            ACTION_RESUME -> player.play()
            ACTION_TOGGLE -> {
                if (player.isPlaying) {
                    player.pause()
                } else {
                    player.play()
                }
            }
            ACTION_NEXT -> {
                player.seekToNext()
            }
            ACTION_PREV -> {
                player.seekToPrevious()
            }
        }

        return START_NOT_STICKY
    }

    private fun playMedia(url: String, title: String, artist: String, artwork: String?) {
        val metadataBuilder = MediaMetadata.Builder()
            .setTitle(title)
            .setArtist(artist)

        if (artwork != null) {
             metadataBuilder.setArtworkUri(Uri.parse(artwork))
        }

        val metadata = metadataBuilder.build()

        val mediaItem = MediaItem.Builder()
            .setUri(url)
            .setMediaMetadata(metadata)
            .build()

        player.setMediaItem(mediaItem)
        player.prepare()
        player.play()
    }

    private fun broadcastWidgetUpdate() {
        val intent = Intent(ACTION_WIDGET_UPDATE)
        // Explicitly target the widget provider to ensure it receives the broadcast even in background on some versions?
        // Actually, explicit broadcast is safer.
        intent.setPackage(packageName)

        val mediaItem = player.currentMediaItem
        val isPlaying = player.isPlaying

        intent.putExtra(EXTRA_IS_PLAYING, isPlaying)

        if (mediaItem != null) {
            intent.putExtra(EXTRA_TITLE, mediaItem.mediaMetadata.title ?: "Unknown Title")
            intent.putExtra(EXTRA_ARTIST, mediaItem.mediaMetadata.artist ?: "Unknown Artist")
            val art = mediaItem.mediaMetadata.artworkUri?.toString()
            if (art != null) {
                val lower = art.lowercase()
                if (!(lower.startsWith("http://") || lower.startsWith("https://"))) {
                intent.putExtra(EXTRA_ARTWORK, art)
                }
            }
        }

        sendBroadcast(intent)
    }

    override fun onGetSession(controllerInfo: MediaSession.ControllerInfo): MediaSession? {
        return mediaSession
    }

    override fun onDestroy() {
        mediaSession?.run {
            player.release()
            release()
            mediaSession = null
        }
        super.onDestroy()
    }
}
