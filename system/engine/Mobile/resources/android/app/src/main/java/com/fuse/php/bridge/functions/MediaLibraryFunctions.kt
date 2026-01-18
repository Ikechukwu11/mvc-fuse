package com.fuse.php.bridge.functions

import android.content.Context
import android.content.pm.PackageManager
import android.util.Log
import androidx.fragment.app.FragmentActivity
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import com.fuse.php.bridge.BridgeFunction
import com.fuse.php.media.MediaStoreScanner
import com.fuse.php.utils.NativeActionCoordinator
import org.json.JSONArray
import org.json.JSONObject
import java.io.File

object MediaLibraryFunctions {
    private const val TAG = "MediaLibrary"
    private const val REQUEST_CODE_MEDIA = 2004

    class Scan(private val activity: FragmentActivity, private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val permission = if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
                android.Manifest.permission.READ_MEDIA_AUDIO
            } else {
                android.Manifest.permission.READ_EXTERNAL_STORAGE
            }

            val granted = ContextCompat.checkSelfPermission(context, permission) == PackageManager.PERMISSION_GRANTED
            if (!granted) {
                try {
                    ActivityCompat.requestPermissions(activity, arrayOf(permission), REQUEST_CODE_MEDIA)
                    Log.d(TAG, "📄 Requested media permission: $permission")
                } catch (e: Exception) {
                    Log.e(TAG, "❌ Failed to request media permission", e)
                }
                return mapOf("requested" to true, "permission" to permission)
            }

            val scanner = MediaStoreScanner(context)
            val results: JSONArray = scanner.scanAll()
            try {
                val appStorageDir = File(context.filesDir.parent, "app_storage")
                val targetDir = File(appStorageDir, "persisted_data/storage/app/music")
                if (!targetDir.exists()) targetDir.mkdirs()
                val libFile = File(targetDir, "library.json")
                libFile.writeText(results.toString())
            } catch (e: Exception) {
                Log.e(TAG, "❌ Failed to persist library.json", e)
            }

            val payload = JSONObject().apply {
                put("success", true)
                put("count", results.length())
                put("json_path", "storage/app/music/library.json")
            }
            try {
                NativeActionCoordinator.dispatchEvent(activity, "MediaLibrary.Scanned", payload.toString())

                // Ludoria Compatibility
                val ludoriaPayload = JSONObject().apply {
                    put("success", true)
                    put("tracks", results)
                    put("count", results.length())
                }
                NativeActionCoordinator.dispatchEvent(activity, "Native\\Mobile\\Events\\Music\\LibraryLoaded", ludoriaPayload.toString())
            } catch (e: Exception) {
                Log.e(TAG, "Dispatch error", e)
            }
            return mapOf("count" to results.length())
        }
    }

    class Search(private val activity: FragmentActivity, private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val q = (parameters["query"] as? String)?.trim() ?: ""
            val scanner = MediaStoreScanner(context)
            val results = if (q.isNotEmpty()) scanner.search(q) else JSONArray()
            val payload = JSONObject().apply {
                put("query", q)
                put("tracks", results)
            }
            try {
                NativeActionCoordinator.dispatchEvent(activity, "MediaLibrary.SearchResults", payload.toString())
            } catch (e: Exception) {
                Log.e(TAG, "Dispatch error", e)
            }
            return mapOf("count" to results.length())
        }
    }
}
