package com.fuse.php.bridge.functions

import android.content.Context
import androidx.fragment.app.FragmentActivity
import com.fuse.php.bridge.BridgeFunction
import com.fuse.php.ui.MainActivity
import android.util.Log

object MusicFunctions {

    class Load(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            // Map Ludoria parameters (uri, artUri) to Fuse parameters (url, artwork)
            val uri = parameters["uri"] as? String
            val title = parameters["title"] as? String
            val artist = parameters["artist"] as? String
            val artUri = parameters["artUri"] as? String

            if (uri == null) return mapOf("error" to "URI required")

            val fuseParams = mutableMapOf<String, Any>(
                "url" to uri,
                "title" to (title ?: ""),
                "artist" to (artist ?: "")
            )
            if (artUri != null) fuseParams["artwork"] = artUri

            // Reuse MediaFunctions.Play logic which handles loading and playing
            return MediaFunctions.Play().execute(fuseParams)
        }
    }

    class Play(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return MediaFunctions.Resume().execute(parameters)
        }
    }

    class Pause(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return MediaFunctions.Pause().execute(parameters)
        }
    }

    class Stop(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            activity.runOnUiThread {
                val controller = (activity as? MainActivity)?.getMusicController()
                controller?.stop()
            }
            return mapOf("success" to true)
        }
    }

    class Seek(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return MediaQueueFunctions.SeekTo().execute(parameters)
        }
    }

    class Next(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return MediaQueueFunctions.Next().execute(parameters)
        }
    }

    class Previous(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return MediaQueueFunctions.Previous().execute(parameters)
        }
    }

    class Status(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            // Start status updates if not already running, or just get one-time state?
            // Ludoria's Status just sends ACTION_STATUS.
            // We'll map to Media.State for one-time, or Media.StartStatusUpdates
            // Let's just return current state.
            return MediaQueueFunctions.State().execute(parameters)
        }
    }

    class ListLibrary(private val activity: FragmentActivity, private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return MediaLibraryFunctions.Scan(activity, context).execute(parameters)
        }
    }
}
