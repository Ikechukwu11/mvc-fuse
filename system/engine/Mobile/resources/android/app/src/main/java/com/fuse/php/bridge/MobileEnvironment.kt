package com.fuse.php.bridge

import android.annotation.SuppressLint
import android.content.Context
import android.util.Log
import java.io.File
import java.io.FileOutputStream
import java.io.FileInputStream
import java.io.BufferedInputStream
import java.util.zip.ZipEntry
import java.util.zip.ZipInputStream
import java.security.MessageDigest
import kotlinx.coroutines.*

class MobileEnvironment(private val context: Context) {
    private val appStorageDir = context.getDir("storage", Context.MODE_PRIVATE)
    private val phpBridge = PHPBridge(context)

    // Cached bundle metadata to avoid reading ZIP multiple times
    private var bundleMetadataCache: BundleMetadata? = null

    private external fun nativeSetEnv(name: String, value: String, overwrite: Int): Int

    // Data class to hold bundle metadata read from ZIP
    private data class BundleMetadata(
        val version: String?
    )

    // Data class for version information with utility methods
    private data class VersionInfo(val raw: String, val clean: String) {
        val isDebug: Boolean get() = clean.equals(VERSION_DEBUG, ignoreCase = true)

        companion object {
            fun from(version: String?): VersionInfo? {
                if (version == null) return null
                val clean = version.trim().trim('"').trim('\'')
                return VersionInfo(version, clean)
            }
        }
    }

    companion object {
        private const val TAG = "MobileEnvironment"

        // File and directory names
        private const val BUNDLE_ZIP = "app_bundle.zip"
        private const val VERSION_FILE = ".version"
        private const val ENV_FILE = ".env"
        private const val CACERT_FILE = "cacert.pem"
        private const val PHP_INI_FILE = "php.ini"
        private const val APP_KEY_FILE = "persisted_data/appkey.txt"

        // Directory paths
        private const val DIR_APP_ROOT = "app"
        private const val DIR_PERSISTED = "persisted_data"
        private const val DIR_STORAGE = "persisted_data/storage"
        private const val DIR_LOGS = "persisted_data/storage/logs"
        private const val DIR_APP_DATA = "persisted_data/storage/app"
        private const val DIR_SESSIONS = "persisted_data/storage/sessions"
        private const val DIR_PUBLIC = "persisted_data/storage/app/public"
        private const val DIR_DATABASE = "persisted_data/database/"
        private const val DIR_PHP_SESSIONS = "php_sessions"

        // Version constants
        private const val VERSION_DEBUG = "DEBUG"
        private const val VERSION_DEFAULT = "0.0.0"

        // Environment variable regex patterns
        private const val REGEX_APP_VERSION = "MVC_APP_VERSION=(.+)"

        init {
            System.loadLibrary("php_wrapper")
        }

        /**
         * Read MVC_START_URL from the extracted .env file
         */
        fun getStartURL(context: Context): String {
            val appStorageDir = context.getDir("storage", Context.MODE_PRIVATE)
            val appDir = File(appStorageDir, DIR_APP_ROOT)
            val envFile = File(appDir, ".env")

            if (!envFile.exists()) {
                Log.d(TAG, "⚙️ No .env file found, using default start URL")
                return "/"
            }

            try {
                val envContent = envFile.readText()
                val pattern = Regex("""MVC_START_URL\s*=\s*([^\r\n]+)""")
                val match = pattern.find(envContent)

                if (match != null) {
                    var value = match.groupValues[1]
                        .trim()
                        .trim('"', '\'')

                    if (value.isNotEmpty()) {
                        // Ensure path starts with /
                        if (!value.startsWith("/")) {
                            value = "/$value"
                        }
                        Log.d(TAG, "⚙️ Found start URL in .env: $value")
                        return value
                    }
                }
            } catch (e: Exception) {
                Log.e(TAG, "⚠️ Error reading .env file", e)
            }

            Log.d(TAG, "⚙️ No MVC_START_URL found, using default: /")
            return "/"
        }
    }

    fun initialize() {
        try {
            val persistedPublic = File(appStorageDir, DIR_PUBLIC)
            Log.d(TAG, "🔍 Starting Initialization...")

            setupDirectories()
            extractAppBundle()
            setupEnvironment()
            runBaseRunnerCommands()

            Log.d(TAG, "✅ Initialization Complete")
        } catch (e: Exception) {
            Log.e(TAG, "Error initializing Mobile environment", e)
            throw RuntimeException("Failed to initialize Mobile environment", e)
        }
    }

    private fun extractAppBundle() {
        val appDir = File(appStorageDir, DIR_APP_ROOT)

        // Get embedded version
        val embeddedVersion = readVersionFromZip() ?: VERSION_DEFAULT

        // Check current version from .env if exists
        val currentVersion = if (appDir.exists()) {
            val envFile = File(appDir, ENV_FILE)
            if (envFile.exists()) {
                getVersionFromEnvFile(envFile)
            } else {
                null
            }
        } else {
            null
        }

        Log.d(TAG, "📦 Current: ${currentVersion ?: "none"}, Embedded: $embeddedVersion")

        // If DEBUG mode (in version string), ALWAYS extract
        val isDebug = embeddedVersion.equals(VERSION_DEBUG, ignoreCase = true)
        val isUpToDate = currentVersion == embeddedVersion
        val shouldExtract = isDebug || !isUpToDate

        if (!shouldExtract) {
            Log.d(TAG, "✅ App already up to date (version $embeddedVersion)")
            return
        }

        Log.d(TAG, "📦 Extracting App bundle...")

        // Delete entire app directory - persisted_data is separate and safe
        if (appDir.exists()) {
            try {
                // Use system rm for speed and reliability
                val process = Runtime.getRuntime().exec(arrayOf("rm", "-rf", appDir.absolutePath))
                process.waitFor()
            } catch (e: Exception) {
                appDir.deleteRecursively()
            }
        }

        appDir.mkdirs()

        try {
            val zipStream = context.assets.open(BUNDLE_ZIP)
            unzip(zipStream, appDir)

            // Update .version file
            val versionFile = File(appDir, VERSION_FILE)
            versionFile.writeText(embeddedVersion)

            Log.d(TAG, "✅ Extraction complete to ${appDir.absolutePath}")

            // Create storage structure for hot reload/cache compatibility if needed
            val storageFramework = File(appDir, "storage/framework")
            storageFramework.mkdirs()

        } catch (e: Exception) {
            Log.e(TAG, "❌ Failed to extract App zip", e)
        }
    }

    private fun isDebugVersion(version: String?): Boolean {
        // Use VersionInfo for consistent version handling
        return VersionInfo.from(version)?.isDebug ?: false
    }

    /**
     * Read bundle metadata from ZIP in a single pass
     */
    private fun readBundleMetadata(): BundleMetadata {
        bundleMetadataCache?.let { return it }

        var version: String? = null

        try {
            val zis = ZipInputStream(context.assets.open(BUNDLE_ZIP) as java.io.InputStream)
            var entry: ZipEntry?

            while (zis.nextEntry.also { entry = it } != null) {
                when (entry?.name) {
                    ENV_FILE -> {
                        val envContent = zis.bufferedReader().readText()
                        val versionMatch = Regex(REGEX_APP_VERSION).find(envContent)
                        version = versionMatch?.groupValues?.get(1)?.trim()
                    }
                    VERSION_FILE -> {
                        if (version == null) {
                            version = zis.bufferedReader().readText().trim()
                        }
                    }
                }
                if (version != null) break
            }
            zis.close()
        } catch (e: Exception) {
            Log.e(TAG, "Failed to read bundle metadata", e)
        }

        val metadata = BundleMetadata(version)
        bundleMetadataCache = metadata
        return metadata
    }

    private fun readVersionFromZip(): String? {
        return readBundleMetadata().version
    }

    private fun getVersionFromEnvFile(envFile: File): String? {
        return try {
            val envContent = envFile.readText()
            val versionMatch = Regex(REGEX_APP_VERSION).find(envContent)
            versionMatch?.groupValues?.get(1)?.trim()
        } catch (e: Exception) {
            null
        }
    }

    private fun unzip(inputStream: java.io.InputStream, destinationDir: File) {
        val buffer = ByteArray(65536)
        val zis = ZipInputStream(BufferedInputStream(inputStream))

        val directories = mutableListOf<File>()
        val fileDataList = mutableListOf<Pair<File, ByteArray>>()

        var ze: ZipEntry? = zis.nextEntry
        while (ze != null) {
            val name = ze.name
            Log.d(TAG, "📦 Zip Entry: '$name'")

            // Skip storage directory - we use persisted_data/storage instead
            if (name.startsWith("storage/") || name == "storage") {
                zis.closeEntry()
                ze = zis.nextEntry
                continue
            }

            val file = File(destinationDir, name)

            if (ze.isDirectory) {
                directories.add(file)
            } else {
                val outputStream = java.io.ByteArrayOutputStream()
                var count: Int
                while (zis.read(buffer).also { count = it } != -1) {
                    outputStream.write(buffer, 0, count)
                }
                fileDataList.add(file to outputStream.toByteArray())
            }
            zis.closeEntry()
            ze = zis.nextEntry
        }
        zis.close()

        // Phase 2: Create all directories
        directories.forEach { it.mkdirs() }

        // Phase 3: Write files in parallel using coroutines
        runBlocking {
            fileDataList.map { (file, data) ->
                async(Dispatchers.IO) {
                    file.parentFile?.mkdirs()
                    FileOutputStream(file).use { fos ->
                        fos.write(data)
                    }
                }
            }.awaitAll()
        }
    }

    private fun copyAssetToInternalStorage(assetName: String, targetFileName: String, forceUpdate: Boolean = false): File {
        val outFile = File(context.filesDir, targetFileName)

        if (!outFile.exists() || forceUpdate) {
            try {
                context.assets.open(assetName).use { input ->
                    FileOutputStream(outFile).use { output ->
                        input.copyTo(output)
                    }
                }
            } catch (e: Exception) {
                Log.e(TAG, "❌ Failed to copy asset $assetName", e)
            }
        }
        return outFile
    }

    private fun runBaseRunnerCommands() {
        val dbFile = File(appStorageDir, "persisted_data/database/database.sqlite")
        if (!dbFile.exists()) {
            Log.d(TAG, "📄 Creating empty SQLite file: ${dbFile.absolutePath}")
            dbFile.createNewFile()
        }

        // Placeholder for any initial runner commands if needed
        val publicDir = File(appStorageDir, "persisted_data/storage/app/public")
        if (!publicDir.exists()) {
            publicDir.mkdirs()
        }
        phpBridge.runRunnerCommand("migrate --force")
    }

    private fun setupDirectories() {
        try {
            createDirectory(DIR_SESSIONS, withPermissions = true)
            createDirectory(DIR_LOGS)
            createDirectory(DIR_APP_DATA)
            createDirectory(DIR_PUBLIC)
            createDirectory(DIR_DATABASE)
            File(appStorageDir, DIR_STORAGE).setWritable(true, true)
        } catch (e: Exception) {
            Log.e(TAG, "Failed to create directories", e)
            throw e
        }
    }

    private fun setupEnvironment() {
        try {
            val appKeyFile = File(appStorageDir, APP_KEY_FILE)
            val appKey: String = if (appKeyFile.exists()) {
                val contents = appKeyFile.readText().trim()
                if (contents.startsWith("base64:")) {
                    contents
                } else {
                    appKeyFile.delete()
                    generateAndSaveAppKey(appKeyFile)
                }
            } else {
                generateAndSaveAppKey(appKeyFile)
            }

            setEnvironmentVariables(
                "APP_KEY" to appKey,
                "DOCUMENT_ROOT" to "${appStorageDir.absolutePath}/$DIR_APP_ROOT",
                "MVC_ROOT" to "${appStorageDir.absolutePath}/$DIR_APP_ROOT",
                "MVC_STORAGE_PATH" to "${appStorageDir.absolutePath}/$DIR_STORAGE",

                "APP_ENV" to "local",
                "APP_URL" to "http://127.0.0.1",
                "ASSET_URL" to "http://127.0.0.1/_assets",
                "DB_CONNECTION" to "sqlite",
                "DB_DATABASE" to "${appStorageDir.absolutePath}/persisted_data/database/database.sqlite",
                "CACHE_DRIVER" to "file",
                "SESSION_DRIVER" to "file",
                "MVC_MOBILE_PLATFORM" to "android",
                "MVC_TEMPDIR" to context.cacheDir.absolutePath,

                "COOKIE_PATH" to "/",
                "COOKIE_DOMAIN" to "127.0.0.1",
                "COOKIE_SECURE" to "false",
                "COOKIE_HTTP_ONLY" to "true",
                // Session settings
                "SESSION_DRIVER" to "file",
                "SESSION_DOMAIN" to "127.0.0.1",
                "SESSION_SECURE_COOKIE" to "false",
                "SESSION_HTTP_ONLY" to "true",
                "SESSION_SAME_SITE" to "lax",

                "PHP_INI_SCAN_DIR" to appStorageDir.absolutePath,
                "CA_CERT_DIR" to context.filesDir.absolutePath,
                "PHPRC" to context.filesDir.absolutePath,
                // PHP/Server environment
                "REMOTE_ADDR" to "127.0.0.1",
                "SERVER_NAME" to "127.0.0.1",
                "SERVER_PORT" to "80",
                "SERVER_PROTOCOL" to "HTTP/1.1",
                "REQUEST_SCHEME" to "http"
            )

            Log.d(TAG, "✅ Environment variables configured")

            val phpSessionDir = File(appStorageDir, DIR_SESSIONS).apply {
                mkdirs()
                setReadable(true, true)
                setWritable(true, true)
                setExecutable(true, true)
            }
            setEnvironmentVariable("SESSION_SAVE_PATH", phpSessionDir.absolutePath)

            try {
                copyAssetToInternalStorage(CACERT_FILE, CACERT_FILE)
                val phpIni = """
curl.cainfo="${context.filesDir.absolutePath}/$CACERT_FILE"
openssl.cafile="${context.filesDir.absolutePath}/$CACERT_FILE"
"""
                File(context.filesDir, PHP_INI_FILE).writeText(phpIni)
            } catch (e: Exception) {
                Log.e(TAG, "❌ Failed to copy or set CURL_CA_BUNDLE", e)
            }

        } catch (e: Exception) {
            Log.e(TAG, "Failed to setup environment", e)
            throw e
        }
    }

    private fun generateAndSaveAppKey(file: File): String {
        // Generate a random 32-character string
        val chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
        val sb = StringBuilder()
        for (i in 0 until 32) {
            sb.append(chars[java.util.Random().nextInt(chars.length)])
        }
        val generatedKey = "base64:" + android.util.Base64.encodeToString(sb.toString().toByteArray(), android.util.Base64.NO_WRAP)

        file.parentFile?.mkdirs()
        file.writeText(generatedKey)

        Log.d(TAG, "🔐 Generated and stored new APP_KEY: $generatedKey")
        return generatedKey
    }

    private fun setEnvironmentVariable(name: String, value: String) {
        try {
            val result = nativeSetEnv(name, value, 1)
            if (result != 0) {
                throw RuntimeException("Failed to set environment variable: $name")
            }
        } catch (e: Exception) {
            Log.e(TAG, "Failed to set environment variable: $name", e)
            throw e
        }
    }

    /**
     * Set multiple environment variables at once
     * More efficient than individual calls due to reduced JNI overhead
     */
    private fun setEnvironmentVariables(vararg pairs: Pair<String, String>) {
        for ((name, value) in pairs) {
            setEnvironmentVariable(name, value)
        }
    }

    private fun createDirectory(path: String, withPermissions: Boolean = false) {
        val dir = File(appStorageDir, path)

        // Skip if already exists
        if (dir.exists()) return

        dir.mkdirs()

        // Set owner-only permissions if requested
        if (withPermissions) {
            dir.setReadable(true, true)
            dir.setWritable(true, true)
            dir.setExecutable(true, true)
        }
    }

    fun cleanup() {
        try {
            phpBridge.shutdown()
        } catch (e: Exception) {
            Log.e(TAG, "Error during cleanup", e)
        }
    }
}
