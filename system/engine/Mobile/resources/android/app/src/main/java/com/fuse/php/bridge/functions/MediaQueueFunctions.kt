package com.fuse.php.bridge.functions

import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.media3.common.Player
import com.fuse.php.bridge.BridgeFunction
import com.fuse.php.ui.MainActivity
import com.fuse.php.utils.NativeActionCoordinator
import org.json.JSONObject

object MediaQueueFunctions {
    private var handler: Handler? = null
    private var runnable: Runnable? = null
    private var running: Boolean = false
    private var lastPositionMs: Long = -1L
    private var lastDurationMs: Long = -1L

    class Next : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val activity = MainActivity.instance ?: return mapOf("error" to "Activity not available")
            activity.runOnUiThread {
                val controller = activity.getMusicController()
                if (controller != null) {
                    try { controller.seekToNext() } catch (e: Exception) { Log.e("Media", "Next error", e) }
                }
            }
            return mapOf("success" to true)
        }
    }

    class Previous : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val activity = MainActivity.instance ?: return mapOf("error" to "Activity not available")
            activity.runOnUiThread {
                val controller = activity.getMusicController()
                if (controller != null) {
                    try { controller.seekToPrevious() } catch (e: Exception) { Log.e("Media", "Prev error", e) }
                }
            }
            return mapOf("success" to true)
        }
    }

    class SeekTo : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val posParam = parameters["position"]
            val posMs: Long = when (posParam) {
                is Number -> posParam.toLong()
                is String -> posParam.trim().toLongOrNull() ?: 0L
                else -> 0L
            }
            val activity = MainActivity.instance ?: return mapOf("error" to "Activity not available")
            activity.runOnUiThread {
                val controller = activity.getMusicController()
                if (controller != null) {
                    try { controller.seekTo(posMs) } catch (e: Exception) { Log.e("Media", "Seek error", e) }
                }
            }
            return mapOf("success" to true)
        }
    }

    class ToggleShuffle : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val enabledParam = parameters["enabled"]
            val enabled: Boolean = when (enabledParam) {
                is Boolean -> enabledParam
                is String -> enabledParam.trim().equals("true", true)
                else -> true
            }
            val activity = MainActivity.instance ?: return mapOf("error" to "Activity not available")
            activity.runOnUiThread {
                val controller = activity.getMusicController()
                if (controller != null) {
                    try { controller.setShuffleModeEnabled(enabled) } catch (e: Exception) { Log.e("Media", "Shuffle error", e) }
                }
            }
            return mapOf("success" to true, "enabled" to enabled)
        }
    }

    class ToggleRepeat : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val modeParam = parameters["mode"] as? String ?: "all"
            val mode = when (modeParam.lowercase()) {
                "off" -> Player.REPEAT_MODE_OFF
                "one" -> Player.REPEAT_MODE_ONE
                else -> Player.REPEAT_MODE_ALL
            }
            val activity = MainActivity.instance ?: return mapOf("error" to "Activity not available")
            activity.runOnUiThread {
                val controller = activity.getMusicController()
                if (controller != null) {
                    try { controller.setRepeatMode(mode) } catch (e: Exception) { Log.e("Media", "Repeat error", e) }
                }
            }
            return mapOf("success" to true, "mode" to modeParam)
        }
    }

    class State : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val activity = MainActivity.instance ?: return mapOf("error" to "Activity not available")
            activity.runOnUiThread {
                val controller = activity.getMusicController()
                if (controller != null) {
                    try {
                        val pos = controller.currentPosition
                        val dur = controller.duration
                        val item = controller.currentMediaItem
                        val title = item?.mediaMetadata?.title?.toString() ?: ""
                        val artist = item?.mediaMetadata?.artist?.toString() ?: ""
                        val album = item?.mediaMetadata?.albumTitle?.toString() ?: ""
                        val artUri = item?.mediaMetadata?.artworkUri?.toString() ?: ""
                        val uri = item?.requestMetadata?.mediaUri?.toString() ?: ""

                        if (controller.isPlaying) {
                            val changed = (lastPositionMs < 0 || kotlin.math.abs(pos - lastPositionMs) >= 250L || dur != lastDurationMs)
                            if (changed) {
                                lastPositionMs = pos
                                lastDurationMs = dur
                                val payload = JSONObject().apply {
                                    put("positionMs", pos)
                                    put("durationMs", dur)
                                    put("isPlaying", true)
                                    put("title", title)
                                    put("artist", artist)
                                    put("album", album)
                                    put("artwork", artUri)
                                    put("uri", uri)
                                }
                                NativeActionCoordinator.dispatchEvent(activity, "Media.State", payload.toString())

                                // Ludoria Compatibility
                                val ludoriaPayload = JSONObject().apply {
                                    put("playing", true)
                                    put("position", pos)
                                    put("duration", dur)
                                    put("title", title)
                                    put("artist", artist)
                                    put("album", album)
                                    put("artUri", artUri)
                                    put("uri", uri)
                                }
                                NativeActionCoordinator.dispatchEvent(activity, "Native\\Mobile\\Events\\Music\\PlaybackState", ludoriaPayload.toString())
                            }
                        }
                    } catch (e: Exception) {
                        Log.e("Media", "❌ Error getting state", e)
                    }
                }
            }
            return mapOf("success" to true)
        }
    }

    class StartStatusUpdates : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            if (running) return mapOf("success" to true)
            if (handler == null) handler = Handler(Looper.getMainLooper())
            running = true
            runnable = Runnable {
                try {
                    val activity = MainActivity.instance
                    val controller = activity?.getMusicController()
                    if (controller == null || !controller.isPlaying) {
                        running = false
                        handler?.removeCallbacksAndMessages(null)
                        runnable = null
                        return@Runnable
                    }
                    State().execute(emptyMap())
                } catch (_: Exception) {}
                if (running) {
                    handler?.postDelayed(runnable!!, 1000L)
                }
            }
            handler?.removeCallbacksAndMessages(null)
            lastPositionMs = -1L
            lastDurationMs = -1L
            handler?.post(runnable!!)
            return mapOf("success" to true)
        }
    }

    class StopStatusUpdates : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            running = false
            handler?.removeCallbacksAndMessages(null)
            runnable = null
            lastPositionMs = -1L
            lastDurationMs = -1L
            return mapOf("success" to true)
        }
    }
}
