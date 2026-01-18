package com.fuse.php.media

import android.net.Uri
import android.util.Log
import androidx.activity.result.contract.ActivityResultContracts
import androidx.fragment.app.FragmentActivity
import android.provider.OpenableColumns
import android.content.Intent

class AudioPickerHelper(
    private val activity: FragmentActivity,
    private val onPicked: (uri: Uri, title: String, artist: String) -> Unit
) {
    private val launcher = activity.registerForActivityResult(ActivityResultContracts.OpenDocument()) { uri: Uri? ->
        if (uri != null) {
            try {
                activity.contentResolver.takePersistableUriPermission(
                    uri,
                    Intent.FLAG_GRANT_READ_URI_PERMISSION
                )
            } catch (_: Exception) {}
            val metadata = getAudioMetadata(uri)
            val title = metadata["title"] ?: "Unknown Title"
            val artist = metadata["artist"] ?: "Unknown Artist"
            onPicked(uri, title, artist)
        }
    }

    fun pickAudio() {
        launcher.launch(arrayOf("audio/*"))
    }

    private fun getAudioMetadata(uri: Uri): Map<String, String> {
        val result = mutableMapOf<String, String>()
        try {
            activity.contentResolver.query(uri, null, null, null, null)?.use { cursor ->
                if (cursor.moveToFirst()) {
                    val titleIndex = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
                    if (titleIndex != -1) {
                        result["title"] = cursor.getString(titleIndex)
                    }
                }
            }
        } catch (e: Exception) {
            Log.e("Media", "Error reading metadata", e)
        }
        return result
    }
}
