package com.fuse.php.bridge.functions

import android.util.Log
import com.fuse.php.bridge.BridgeFunction
import com.fuse.php.ui.MainActivity
import com.fuse.php.utils.NativeActionCoordinator
import java.io.File

/**
 * Functions related to App-wide configuration
 * Namespace: "App.*"
 */
object AppFunctions {

    /**
     * Set status bar color and style
     * Parameters:
     *   - color: (optional) string - Hex color code (e.g. "#FF0000")
     *   - style: (optional) string - "light", "dark", or "auto"
     *   - overlay: (optional) boolean - Whether to overlay content (default: true)
     */
    class SetStatusBar : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val color = parameters["color"] as? String
            val style = parameters["style"] as? String
            val overlay = parameters["overlay"] as? Boolean ?: true

            Log.d("AppFunctions", "SetStatusBar called: color=$color, style=$style, overlay=$overlay")

            val activity = MainActivity.instance
            if (activity != null) {
                activity.updateStatusBar(color, style, overlay)
                return mapOf("success" to true)
            } else {
                return mapOf("error" to "MainActivity not found")
            }
        }
    }

    /**
     * Read a persisted storage file and dispatch its contents.
     * Parameters:
     *   - path: string - relative path under storage/app (e.g., "music/library.json")
     * Dispatches event "App.StorageFileRead" with { path, content }.
     */
    class ReadStorageFile : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val relPath = parameters["path"] as? String ?: return mapOf("error" to "path required")
            val activity = MainActivity.instance ?: return mapOf("error" to "Activity not available")
            return try {
                val appStorageDir = File(activity.filesDir.parent, "app_storage")
                val persistedRoot = File(appStorageDir, "persisted_data/storage/app")
                val file = File(persistedRoot, relPath)
                val content = if (file.exists()) file.readText() else ""
                val payload = org.json.JSONObject().apply {
                    put("path", relPath)
                    put("content", content)
                }
                NativeActionCoordinator.dispatchEvent(activity, "App.StorageFileRead", payload.toString())
                mapOf("success" to true, "size" to content.length)
            } catch (e: Exception) {
                Log.e("AppFunctions", "ReadStorageFile error: ${e.message}", e)
                mapOf("error" to (e.message ?: "unknown"))
            }
        }
    }
}
