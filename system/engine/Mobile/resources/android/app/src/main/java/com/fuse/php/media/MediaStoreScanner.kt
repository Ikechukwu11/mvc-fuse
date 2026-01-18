package com.fuse.php.media

import android.content.Context
import android.database.Cursor
import android.net.Uri
import android.provider.MediaStore
import android.util.Log
import org.json.JSONArray
import org.json.JSONObject
import android.media.MediaMetadataRetriever
import java.io.File

class MediaStoreScanner(private val context: Context) {
    private fun getAppStorageDir(): File {
        return File(context.filesDir.parent, "app_storage")
    }

    private fun cacheArtwork(contentUri: Uri, id: Long): File? {
        return try {
            val mmr = MediaMetadataRetriever()
            mmr.setDataSource(context, contentUri)
            val art = mmr.embeddedPicture
            mmr.release()
            if (art != null && art.isNotEmpty()) {
                val appStorageDir = getAppStorageDir()
                val targetDir = File(appStorageDir, "persisted_data/storage/app/music/art")
                if (!targetDir.exists()) {
                    targetDir.mkdirs()
                }
                val outFile = File(targetDir, "$id.jpg")
                outFile.outputStream().use { it.write(art) }
                outFile
            } else {
                null
            }
        } catch (e: Exception) {
            Log.e("MediaStoreScanner", "Artwork cache error", e)
            null
        }
    }

    fun scanAll(): JSONArray {
        val list = JSONArray()
        try {
            val collection: Uri = if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.Q) {
                MediaStore.Audio.Media.getContentUri(MediaStore.VOLUME_EXTERNAL)
            } else {
                MediaStore.Audio.Media.EXTERNAL_CONTENT_URI
            }

            val projection = arrayOf(
                MediaStore.Audio.Media._ID,
                MediaStore.Audio.Media.TITLE,
                MediaStore.Audio.Media.ARTIST,
                MediaStore.Audio.Media.ALBUM,
                MediaStore.Audio.Media.DURATION,
                MediaStore.Audio.Media.DATE_ADDED
            )

            val selection = "${MediaStore.Audio.Media.IS_MUSIC} != 0"
            val sortOrder = "${MediaStore.Audio.Media.DATE_ADDED} DESC"

            context.contentResolver.query(collection, projection, selection, null, sortOrder)?.use { cursor ->
                val idCol = cursor.getColumnIndexOrThrow(MediaStore.Audio.Media._ID)
                val titleCol = cursor.getColumnIndexOrThrow(MediaStore.Audio.Media.TITLE)
                val artistCol = cursor.getColumnIndexOrThrow(MediaStore.Audio.Media.ARTIST)
                val albumCol = cursor.getColumnIndexOrThrow(MediaStore.Audio.Media.ALBUM)
                val durationCol = cursor.getColumnIndexOrThrow(MediaStore.Audio.Media.DURATION)
                val dateAddedCol = cursor.getColumnIndexOrThrow(MediaStore.Audio.Media.DATE_ADDED)

                while (cursor.moveToNext()) {
                    val id = cursor.getLong(idCol)
                    val contentUri = Uri.withAppendedPath(collection, id.toString())
                    val artFile = cacheArtwork(contentUri, id)
                    val item = JSONObject().apply {
                        put("id", id)
                        put("uri", contentUri.toString())
                        put("title", cursor.getString(titleCol) ?: "Unknown Title")
                        put("artist", cursor.getString(artistCol) ?: "Unknown Artist")
                        put("album", cursor.getString(albumCol) ?: "")
                        put("duration", cursor.getLong(durationCol))
                        put("dateAdded", cursor.getLong(dateAddedCol))
                        if (artFile != null) {
                            put("artwork_file", "app/music/art/${id}.jpg")
                            put("artwork_uri", Uri.fromFile(artFile).toString())
                        } else {
                            put("artwork_file", "")
                            put("artwork_uri", "")
                        }
                    }
                    list.put(item)
                }
            }
        } catch (e: Exception) {
            Log.e("MediaStoreScanner", "Error scanning MediaStore", e)
        }
        return list
    }

    fun search(query: String): JSONArray {
        val normalized = query.trim().lowercase()
        val all = scanAll()
        val results = JSONArray()
        for (i in 0 until all.length()) {
            val obj = all.getJSONObject(i)
            val hay = "${obj.optString("title")} ${obj.optString("artist")} ${obj.optString("album")}".lowercase()
            if (hay.contains(normalized)) {
                results.put(obj)
            }
        }
        return results
    }
}
