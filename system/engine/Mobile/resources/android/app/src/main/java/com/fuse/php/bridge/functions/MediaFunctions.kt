package com.fuse.php.bridge.functions

import android.net.Uri
import android.util.Log
import androidx.media3.common.MediaItem
import androidx.media3.common.MediaMetadata
import com.fuse.php.bridge.BridgeFunction
import com.fuse.php.ui.MainActivity
import android.media.MediaMetadataRetriever
import java.io.File
import android.net.Uri as AndroidUri

object MediaFunctions {
    class Play : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val rawUrl = parameters["url"] as? String ?: return mapOf("error" to "URL required")
            val rawTitle = parameters["title"] as? String ?: "Unknown Title"
            val rawArtist = parameters["artist"] as? String ?: "Unknown Artist"
            val rawArtwork = parameters["artwork"] as? String?

            val url = rawUrl.trim().trim('`')
            val title = rawTitle.trim().trim('`')
            val artist = rawArtist.trim().trim('`')
            val artwork = rawArtwork?.trim()?.trim('`')

            val activity = MainActivity.instance ?: return mapOf("error" to "Activity not available")
            activity.runOnUiThread {
                val controller = activity.getMusicController()
                if (controller == null) {
                    Log.e("Media", "❌ MediaController not connected")
                    return@runOnUiThread
                }
                try {
                    var artworkUri: AndroidUri? = null
                    try {
                        val isExternalArtwork = !artwork.isNullOrBlank() && (artwork.startsWith("http://") || artwork.startsWith("https://"))
                        if (artwork.isNullOrBlank() || isExternalArtwork) {
                            val appStorageDir = File(activity.filesDir.parent, "app_storage")
                            val artDir = File(appStorageDir, "persisted_data/storage/app/music/art")
                            if (!artDir.exists()) artDir.mkdirs()
                            val mmr = MediaMetadataRetriever()
                            mmr.setDataSource(activity, AndroidUri.parse(url))
                            val artBytes = mmr.embeddedPicture
                            mmr.release()
                            if (artBytes != null && artBytes.isNotEmpty()) {
                                val outFile = File(artDir, "${System.currentTimeMillis()}.jpg")
                                outFile.outputStream().use { it.write(artBytes) }
                                artworkUri = AndroidUri.fromFile(outFile)
                            }
                        } else {
                            artworkUri = AndroidUri.parse(artwork)
                        }
                    } catch (e: Exception) {
                        Log.w("Media", "⚠️ Artwork retrieval failed: ${e.message}")
                    }

                    val mediaItem = MediaItem.Builder()
                        .setUri(url)
                        .setMediaMetadata(
                            MediaMetadata.Builder()
                                .setTitle(title)
                                .setArtist(artist)
                                .setArtworkUri(artworkUri)
                                .build()
                        )
                        .build()

                    controller.setMediaItem(mediaItem)
                    controller.prepare()
                    controller.play()
                } catch (e: Exception) {
                    Log.e("Media", "❌ Error setting media item or playing", e)
                }
            }
            return mapOf("success" to true)
        }
    }

    class Pause : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val activity = MainActivity.instance ?: return mapOf("error" to "Activity not available")
            activity.runOnUiThread {
                val controller = activity.getMusicController()
                if (controller == null) {
                    Log.e("Media", "❌ MediaController not connected")
                    return@runOnUiThread
                }
                try {
                    controller.pause()
                } catch (e: Exception) {
                    Log.e("Media", "❌ Error pausing playback", e)
                }
            }
            return mapOf("success" to true)
        }
    }

    class Resume : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val activity = MainActivity.instance ?: return mapOf("error" to "Activity not available")
            activity.runOnUiThread {
                val controller = activity.getMusicController()
                if (controller == null) {
                    Log.e("Media", "❌ MediaController not connected")
                    return@runOnUiThread
                }
                try {
                    controller.play()
                } catch (e: Exception) {
                    Log.e("Media", "❌ Error resuming playback", e)
                }
            }
            return mapOf("success" to true)
        }
    }

    class PickTrack : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val activity = MainActivity.instance
            if (activity != null) {
                activity.runOnUiThread {
                    try {
                        activity.pickAudio()
                    } catch (e: Exception) {
                        Log.e("Media", "❌ Error launching audio picker", e)
                    }
                }
            } else {
                return mapOf("error" to "Activity not available")
            }
            return mapOf("status" to "launched")
        }
    }
}
