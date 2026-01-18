package com.fuse.php.bridge.functions

import android.os.Handler
import android.os.Looper
import android.util.Log
import androidx.fragment.app.FragmentActivity
import com.fuse.php.bridge.BridgeFunction
import com.fuse.php.utils.NativeActionCoordinator
import com.google.android.material.snackbar.Snackbar
import org.json.JSONArray

/**
 * Functions related to native alert dialogs
 * Namespace: "Dialog.*"
 */
object DialogFunctions {

    /**
     * Show a native alert dialog with custom buttons
     * Parameters:
     *   - title: (optional) string - Alert title
     *   - message: (optional) string - Alert message body
     *   - buttons: (optional) array of strings - Button titles (defaults to ["OK"])
     *   - id: (optional) string - Custom ID included in event payload
     *   - event: (optional) string - Custom event class name (defaults to "Native\Mobile\Events\Alert\ButtonPressed")
     */
    class Alert(private val context: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val title = parameters["title"] as? String
            val message = parameters["message"] as? String
            val id = parameters["id"] as? String
            val event = parameters["event"] as? String ?: "Native\\Mobile\\Events\\Alert\\ButtonPressed"

            // Parse buttons array
            val buttons = mutableListOf<String>()
            when (val buttonsParam = parameters["buttons"]) {
                is JSONArray -> {
                    for (i in 0 until buttonsParam.length()) {
                        buttonsParam.optString(i)?.let { buttons.add(it) }
                    }
                }
                is List<*> -> {
                    buttonsParam.filterIsInstance<String>().forEach { buttons.add(it) }
                }
                is Array<*> -> {
                    buttonsParam.filterIsInstance<String>().forEach { buttons.add(it) }
                }
            }

            if (buttons.isEmpty()) {
                buttons.add("OK")
            }

            // Launch alert on UI thread
            Handler(Looper.getMainLooper()).post {
                try {
                    // Use existing instance if possible or install new one (Fragment logic)
                    // Note: NativeActionCoordinator.install handles fragment tag checking
                    val coord = NativeActionCoordinator.install(context)
                    coord.launchAlert(
                        title ?: "",
                        message ?: "",
                        buttons.toTypedArray(),
                        id,
                        event
                    )
                } catch (e: Exception) {
                    Log.e("DialogFunctions.Alert", "❌ Error launching alert: ${e.message}", e)
                }
            }

            return emptyMap()
        }
    }

    /**
     * Show a toast notification (uses Snackbar)
     * Parameters:
     *   - message: string - The message to display
     *   - duration: string (optional) - "short" or "long" (default: "short")
     */
    class Toast(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val message = parameters["message"]?.toString() ?: ""
            val durationParam = parameters["duration"]?.toString() ?: "short"

            val duration = if (durationParam == "long") {
                Snackbar.LENGTH_LONG
            } else {
                Snackbar.LENGTH_SHORT
            }

            activity.runOnUiThread {
                // Find a suitable view for Snackbar
                val contentView = activity.findViewById<android.view.View>(android.R.id.content)
                if (contentView != null) {
                    val snackbar = Snackbar.make(contentView, message, duration)

                    // Add app icon to Snackbar
                    try {
                        val textView = snackbar.view.findViewById<android.widget.TextView>(com.google.android.material.R.id.snackbar_text)
                        val iconId = activity.applicationInfo.icon
                        val icon = androidx.core.content.ContextCompat.getDrawable(activity, iconId)
                        if (icon != null) {
                            // Scale icon to reasonable size (e.g., 24dp)
                            val density = activity.resources.displayMetrics.density
                            val size = (24 * density).toInt()
                            icon.setBounds(0, 0, size, size)
                            textView.setCompoundDrawables(icon, null, null, null)
                            textView.compoundDrawablePadding = (8 * density).toInt()
                        }
                    } catch (e: Exception) {
                        Log.w("DialogFunctions", "Could not set icon on Snackbar: ${e.message}")
                    }

                    snackbar.show()
                } else {
                    // Fallback to Toast if no view found (rare)
                    android.widget.Toast.makeText(activity.applicationContext, message, if (duration == Snackbar.LENGTH_LONG) 1 else 0).show()
                }
            }

            return mapOf("success" to true)
        }
    }
}
