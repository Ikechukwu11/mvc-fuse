package com.fuse.php.ui

import android.os.VibrationEffect
import android.os.Vibrator
import android.os.Build
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.res.Configuration
import android.os.Bundle
import android.os.Looper
import android.os.Handler
import android.util.Log
import android.webkit.CookieManager
import androidx.fragment.app.FragmentActivity
import androidx.activity.compose.setContent
import com.fuse.php.bridge.PHPBridge
import com.fuse.php.bridge.MobileEnvironment
import com.fuse.php.bridge.registerBridgeFunctions
import com.fuse.php.bridge.BridgeFunctionRegistry
import com.fuse.php.network.WebViewManager
import android.view.ViewGroup
import android.webkit.WebView
import androidx.activity.addCallback
import com.fuse.php.utils.NativeActionCoordinator
import com.fuse.php.utils.WebViewProvider
import com.fuse.php.security.MobileCookieStore
import com.fuse.php.lifecycle.NativePHPLifecycle
import java.io.File
import java.net.URL
import android.webkit.WebChromeClient
import org.json.JSONObject
import androidx.compose.animation.*
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.layout.ime
import androidx.compose.material3.FabPosition
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import android.provider.MediaStore
import android.provider.OpenableColumns
import android.net.Uri
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.view.WindowCompat
import androidx.core.view.WindowInsetsControllerCompat
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import androidx.core.graphics.Insets
import kotlinx.coroutines.launch
import androidx.media3.session.MediaController
import androidx.media3.session.SessionToken
import com.fuse.php.media.MediaPlaybackService
import com.fuse.php.media.AudioPickerHelper
import android.content.ComponentName
import com.google.common.util.concurrent.ListenableFuture
import com.google.common.util.concurrent.MoreExecutors

class MainActivity : FragmentActivity(), WebViewProvider {
    private lateinit var webView: WebView
    private val phpBridge = PHPBridge(this)
    private lateinit var mobileEnv: MobileEnvironment
    private lateinit var webViewManager: WebViewManager
    private lateinit var coord: NativeActionCoordinator
    private var pendingDeepLink: String? = null
    private var hotReloadWatcherThread: Thread? = null
    private var shouldStopWatcher = false
    private var pendingInsets: Insets? = null
    private var showSplash by mutableStateOf(true)

    // Status bar style configuration - replaced during build
    private val statusBarStyle = "REPLACE_STATUS_BAR_STYLE"

    // Media Controller
    private var mediaController: MediaController? = null
    private var controllerFuture: ListenableFuture<MediaController>? = null
    private var audioPickerHelper: AudioPickerHelper? = null

    fun getMusicController(): MediaController? {
        return mediaController
    }

    companion object {
        // Static instance holder for accessing MainActivity from other activities
        var instance: MainActivity? = null
            private set
    }

    fun pickAudio() {
        audioPickerHelper?.pickAudio()
    }

    fun getCoordinator(): NativeActionCoordinator? {
        return if (::coord.isInitialized) coord else null
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        instance = this
        audioPickerHelper = AudioPickerHelper(this) { uri, title, artist ->
            getCoordinator()?.dispatch(
                "Media.TrackPicked",
                JSONObject(mapOf("url" to uri.toString(), "title" to title, "artist" to artist)).toString()
            )
        }

        // Android 15 edge-to-edge compatibility fix
        WindowCompat.setDecorFitsSystemWindows(window, false)

        // Configure status bar icon colors
        configureStatusBar()

        // Apply window insets - inject as CSS variables for web content
        ViewCompat.setOnApplyWindowInsetsListener(window.decorView) { view, insets ->
            val systemBars = insets.getInsets(WindowInsetsCompat.Type.systemBars())
            pendingInsets = systemBars

            // Inject CSS custom properties into WebView if ready
            if (::webViewManager.isInitialized) {
                injectSafeAreaInsets(systemBars.left, systemBars.top, systemBars.right, systemBars.bottom)
            }

            // Detect keyboard visibility and inject class into WebView
            val imeVisible = insets.isVisible(WindowInsetsCompat.Type.ime())
            if (::webViewManager.isInitialized) {
                injectKeyboardVisibility(imeVisible)
            }

            insets
        }

        // Initialize WebView before setContent so it's available for composition
        webView = WebView(this).apply {
            layoutParams = ViewGroup.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
            )
            settings.mediaPlaybackRequiresUserGesture = false
        }

        MobileCookieStore.init(applicationContext)

        // Register bridge functions early, before PHP code can execute
        Log.d("MainActivity", "🔌 Registering bridge functions...")
        registerBridgeFunctions(this, applicationContext)
        Log.d("MainActivity", "✅ Bridge functions registered")

        handleDeepLinkIntent(intent)

        // Set up Compose UI
        setContent {
            MainScreen()
        }

        initializeEnvironmentAsync {
            // Setup WebView and managers FIRST
            webViewManager = WebViewManager(this, webView, phpBridge)
            webViewManager.setup()
            coord = NativeActionCoordinator.install(this)

            // Add JavaScript interface for drawer control
            webView.addJavascriptInterface(AndroidBridge(), "AndroidBridge")

            // Inject safe area insets BEFORE loading any URL to prevent content shift
            pendingInsets?.let {
                injectSafeAreaInsets(it.left, it.top, it.right, it.bottom)
            }

            // NOW load the URL after WebView is fully configured
            val target = pendingDeepLink ?: MobileEnvironment.getStartURL(this)
            val fullUrl = "http://127.0.0.1$target"
            Log.d("DeepLink", "🚀 Loading final URL after WebView setup: $fullUrl")
            webView.loadUrl(fullUrl)

            pendingDeepLink = null

            // Hide splash screen after URL is loaded
            showSplash = false

            // Start hot reload watcher AFTER Mobile environment is initialized
            startHotReloadWatcher()
            injectJavaScript(webView)
        }

        onBackPressedDispatcher.addCallback(this) {
            if (webView.canGoBack()) {
                webView.goBack()
            } else {
                finish()
            }
        }
    }

    override fun onStart() {
        super.onStart()
        val sessionToken = SessionToken(this, ComponentName(this, MediaPlaybackService::class.java))
        controllerFuture = MediaController.Builder(this, sessionToken).buildAsync()
        controllerFuture?.addListener({
            try {
                mediaController = controllerFuture?.get()
                Log.d("Media", "✅ MediaController connected")
            } catch (e: Exception) {
                Log.e("Media", "❌ Failed to connect MediaController", e)
            }
        }, MoreExecutors.directExecutor())
    }

    override fun onStop() {
        super.onStop()
        controllerFuture?.let {
            MediaController.releaseFuture(it)
        }
    }

     override fun onConfigurationChanged(newConfig: Configuration) {
        super.onConfigurationChanged(newConfig)
        Log.d("MainActivity", "🌀 Config changed: orientation = ${newConfig.orientation}")

        // Re-inject safe area insets on orientation change
        pendingInsets?.let {
            injectSafeAreaInsets(it.left, it.top, it.right, it.bottom)
        }

        // Reconfigure status bar on theme change
        if ((newConfig.uiMode and Configuration.UI_MODE_NIGHT_MASK) != 0) {
            configureStatusBar()
        }
    }

    /**
     * Configure status bar and navigation bar colors and icon appearance based on config
     * - auto: Detect from system theme (light icons in dark mode, dark icons in light mode)
     * - light: Always use light/white icons
     * - dark: Always use dark icons
     *
     * For edge-to-edge mode, system bars are transparent to allow content to draw behind them
     */
    @Suppress("DEPRECATION")
    private fun configureStatusBar() {
        val windowInsetsController = WindowInsetsControllerCompat(window, window.decorView)

        // Make status bar and navigation bar transparent for edge-to-edge
        window.statusBarColor = android.graphics.Color.TRANSPARENT
        window.navigationBarColor = android.graphics.Color.TRANSPARENT

        when (statusBarStyle) {
            "auto" -> {
                // Auto-detect from system theme
                val isSystemDarkMode = (resources.configuration.uiMode and
                    Configuration.UI_MODE_NIGHT_MASK) == Configuration.UI_MODE_NIGHT_YES

                // Light status/nav bars (dark icons) for light theme
                // Dark status/nav bars (light icons) for dark theme
                windowInsetsController.isAppearanceLightStatusBars = !isSystemDarkMode
                windowInsetsController.isAppearanceLightNavigationBars = !isSystemDarkMode

                Log.d("StatusBar", "🎨 System bars style: auto (system ${if (isSystemDarkMode) "dark" else "light"} mode)")
                Log.d("StatusBar", "🎨 Using ${if (!isSystemDarkMode) "dark" else "light"} icons with transparent background")
            }
            "light" -> {
                // Light/white icons (for dark backgrounds)
                windowInsetsController.isAppearanceLightStatusBars = false
                windowInsetsController.isAppearanceLightNavigationBars = false

                Log.d("StatusBar", "🎨 System bars style: light (white icons with transparent background)")
            }
            "dark" -> {
                // Dark icons (for light backgrounds)
                windowInsetsController.isAppearanceLightStatusBars = true
                windowInsetsController.isAppearanceLightNavigationBars = true

                Log.d("StatusBar", "🎨 System bars style: dark (dark icons with transparent background)")
            }
            else -> {
                Log.w("StatusBar", "⚠️ Unknown status bar style: $statusBarStyle, defaulting to auto")
                // Default to auto
                val isSystemDarkMode = (resources.configuration.uiMode and
                    Configuration.UI_MODE_NIGHT_MASK) == Configuration.UI_MODE_NIGHT_YES
                windowInsetsController.isAppearanceLightStatusBars = !isSystemDarkMode
                windowInsetsController.isAppearanceLightNavigationBars = !isSystemDarkMode
            }
        }
    }

    /**
     * Dynamically update status bar color and style
     * Called from Bridge functions
     */
    @Suppress("DEPRECATION")
    fun updateStatusBar(colorHex: String?, style: String?, overlay: Boolean = true) {
        runOnUiThread {
            val windowInsetsController = WindowInsetsControllerCompat(window, window.decorView)
            if (overlay) {
                window.statusBarColor = android.graphics.Color.TRANSPARENT
                val targetColor = try {
                    colorHex?.let { android.graphics.Color.parseColor(it) } ?: android.graphics.Color.TRANSPARENT
                } catch (e: Exception) {
                    Log.e("StatusBar", "❌ Invalid color format: $colorHex", e)
                    android.graphics.Color.TRANSPARENT
                }
                val hexx = String.format("#%08X", targetColor)
                val r = android.graphics.Color.red(targetColor)
                val g = android.graphics.Color.green(targetColor)
                val b = android.graphics.Color.blue(targetColor)
                val a = android.graphics.Color.alpha(targetColor) / 255f
                val hex = "rgba($r,$g,$b,$a)"
                val density = resources.displayMetrics.density
                val topPx = ((pendingInsets?.top ?: 0) / density).toInt()
                val js = """
                    (function() {
                        try {
                            var id = '__native_statusbar_style__';
                            var styleEl = document.getElementById(id);
                            var css = 'body::before{content:"";position:fixed;top:0;left:0;right:0;height:${topPx}px;background:${hex};pointer-events:none;z-index:2147483647;}';
                            if (!styleEl) {
                                styleEl = document.createElement('style');
                                styleEl.id = id;
                                styleEl.type = 'text/css';
                                styleEl.appendChild(document.createTextNode(css));
                                if (document.head) { document.head.appendChild(styleEl); }
                            } else {
                                while (styleEl.firstChild) styleEl.removeChild(styleEl.firstChild);
                                styleEl.appendChild(document.createTextNode(css));
                            }
                        } catch (err) {}
                    })();
                """.trimIndent()
                webView.evaluateJavascript(js, null)
            } else {
                if (colorHex != null) {
                    try {
                        val color = android.graphics.Color.parseColor(colorHex)
                        window.statusBarColor = color
                    } catch (e: Exception) {
                        Log.e("StatusBar", "❌ Invalid color format: $colorHex", e)
                    }
                } else {
                    window.statusBarColor = android.graphics.Color.TRANSPARENT
                }
            }
            if (style != null) {
                when (style.lowercase()) {
                    "light" -> {
                        windowInsetsController.isAppearanceLightStatusBars = false
                    }
                    "dark" -> {
                        windowInsetsController.isAppearanceLightStatusBars = true
                    }
                    "auto" -> {
                        val isSystemDarkMode = (resources.configuration.uiMode and
                                Configuration.UI_MODE_NIGHT_MASK) == Configuration.UI_MODE_NIGHT_YES
                        windowInsetsController.isAppearanceLightStatusBars = !isSystemDarkMode
                    }
                }
            }
        }
    }

    private fun initializeEnvironmentAsync(onReady: () -> Unit) {
        Thread {
            Log.d("MobileInit", "📦 Starting async Mobile extraction...")
            mobileEnv = MobileEnvironment(this)
            mobileEnv.initialize()

            Log.d("MobileInit", "✅ Mobile environment ready — continuing")

            Handler(Looper.getMainLooper()).post {
                onReady()
            }
        }.start()
    }

    override fun onNewIntent(intent: Intent?) {
        super.onNewIntent(intent)
        handleDeepLinkIntent(intent)

        // Post lifecycle event for plugins
        intent?.data?.let { uri ->
            NativePHPLifecycle.post(
                NativePHPLifecycle.Events.ON_NEW_INTENT,
                mapOf("url" to uri.toString())
            )
        }
    }

    override fun onResume() {
        super.onResume()
        NativePHPLifecycle.post(NativePHPLifecycle.Events.ON_RESUME)
    }

    override fun onPause() {
        super.onPause()
        NativePHPLifecycle.post(NativePHPLifecycle.Events.ON_PAUSE)
    }

    private fun handleDeepLinkIntent(intent: Intent?) {
        val uri = intent?.data ?: return
        Log.d("DeepLink", "🌐 Received deep link: $uri")

        // Check if this is an OAuth callback from nativephp:// scheme
        if (uri.scheme == "nativephp") {
            Log.d("OAuth", "🔐 OAuth callback detected from scheme: ${uri.scheme}")
            Log.d("OAuth", "🔐 OAuth callback host: ${uri.host}")
            Log.d("OAuth", "🔐 OAuth callback path: ${uri.path}")
            Log.d("OAuth", "🔐 OAuth callback query: ${uri.query}")

            // Check for common OAuth parameters
            val code = uri.getQueryParameter("code")
            val state = uri.getQueryParameter("state")
            val error = uri.getQueryParameter("error")

            if (code != null) {
                Log.d("OAuth", "✅ OAuth authorization code received: ${code.take(10)}...")
            }
            if (state != null) {
                Log.d("OAuth", "✅ OAuth state parameter: $state")
            }
            if (error != null) {
                Log.e("OAuth", "❌ OAuth error received: $error")
            }
        }

        val query = uri.query
        val appUrl = if (uri.scheme != "http" && uri.scheme != "https") {
            // Custom scheme (e.g., myapp://profile/settings): treat host as first path segment
            // This matches iOS behavior where the entire URI after scheme:// is the path
            val host = uri.host ?: ""
            val path = uri.path ?: ""
            buildString {
                if (host.isNotEmpty()) append("/$host")
                if (path.isNotEmpty()) append(path) else if (host.isEmpty()) append("/")
                if (!query.isNullOrBlank()) append("?$query")
            }
        } else {
            // HTTP(S) app links: just use the path (host is the verified domain)
            buildString {
                append(uri.path ?: "/")
                if (!query.isNullOrBlank()) append("?$query")
            }
        }

        Log.d("DeepLink", "📦 Saving deep link for later: $appUrl")
        pendingDeepLink = appUrl
        if (::mobileEnv.isInitialized && ::webViewManager.isInitialized) {
            // Only load immediately if both Mobile environment AND WebView are ready
            val fullUrl = "http://127.0.0.1$appUrl"
            Log.d("DeepLink", "🚀 Loading deep link immediately (app already running): $fullUrl")
            webView.loadUrl(fullUrl)
            pendingDeepLink = null
        } else {
            Log.d("DeepLink", "⏳ Deep link saved, waiting for app initialization to complete")
        }
    }


    private fun initializeEnvironment() {
        mobileEnv = MobileEnvironment(this)
        mobileEnv.initialize()
    }

    fun clearAllCookies() {
        val cookieManager = CookieManager.getInstance()
        cookieManager.removeAllCookies(null)
        cookieManager.flush()
        Log.d("CookieInfo", "All cookies cleared")
    }


    override fun onDestroy() {
        super.onDestroy()
        instance = null

        // Post lifecycle event for plugins
        NativePHPLifecycle.post(NativePHPLifecycle.Events.ON_DESTROY)

        // Clean up coordinator fragment to prevent memory leaks
        if (::coord.isInitialized) {
            supportFragmentManager.beginTransaction()
                .remove(coord)
                .commitNowAllowingStateLoss()
        }

        if (::webViewManager.isInitialized) {
            val chromeClient = webView.webChromeClient
            if (chromeClient is WebChromeClient) {
                chromeClient.onHideCustomView()
            }
        }

        // Stop hot reload watcher thread
        shouldStopWatcher = true
        hotReloadWatcherThread?.interrupt()

        mobileEnv.cleanup()
        phpBridge.shutdown()
    }

    override fun getWebView(): WebView {
        return webView
    }

    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)

        // Post lifecycle event for each permission result
        permissions.forEachIndexed { index, permission ->
            val granted = grantResults.getOrNull(index) == PackageManager.PERMISSION_GRANTED
            NativePHPLifecycle.post(
                NativePHPLifecycle.Events.ON_PERMISSION_RESULT,
                mapOf(
                    "permission" to permission,
                    "granted" to granted,
                    "requestCode" to requestCode
                )
            )
        }

        when (requestCode) {
            1001 -> {
                if ((grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED)) {
                    Log.d("Permission", "✅ Location permission granted")
                    // Optionally re-trigger the location fetch
                } else {
                    Log.e("Permission", "❌ Location permission denied")
                }
            }
            1002 -> {
                if ((grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED)) {
                    Log.d("Permission", "✅ Push notification permission granted")
                } else {
                    Log.e("Permission", "❌ Push notification permission denied")
                }
            }
            1003 -> {
                if ((grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED)) {
                    Log.d("Permission", "✅ Camera permission granted — re-triggering flashlight toggle")
                    try {
                        val function = com.fuse.php.bridge.BridgeFunctionRegistry.shared.get("Device.ToggleFlashlight")
                        if (function != null) {
                            // Execute with empty parameters
                            function.execute(emptyMap())
                            Log.d("Permission", "✅ Flashlight toggle executed after permission grant")
                        } else {
                            Log.e("Permission", "❌ Bridge function 'Device.ToggleFlashlight' not found")
                        }
                    } catch (e: Exception) {
                        Log.e("Permission", "❌ Error re-triggering flashlight toggle: ${e.message}", e)
                    }
                } else {
                    Log.e("Permission", "❌ Camera permission denied — cannot toggle flashlight")
                }
            }
            2004 -> {
                if ((grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED)) {
                    Log.d("Permission", "✅ Media permission granted — re-triggering library scan")
                    try {
                        val function = com.fuse.php.bridge.BridgeFunctionRegistry.shared.get("MediaLibrary.Scan")
                        if (function != null) {
                            function.execute(emptyMap())
                            Log.d("Permission", "✅ MediaLibrary.Scan executed after permission grant")
                        } else {
                            Log.e("Permission", "❌ Bridge function 'MediaLibrary.Scan' not found")
                        }
                    } catch (e: Exception) {
                        Log.e("Permission", "❌ Error re-triggering media scan: ${e.message}", e)
                    }
                } else {
                    Log.e("Permission", "❌ Media permission denied — cannot scan library")
                }
            }
        }
    }

    private fun startHotReloadWatcher() {
        if (!isDebugVersion()) return

        // Configure WebView for development - disable caching for hot reload
        webView.settings.cacheMode = android.webkit.WebSettings.LOAD_NO_CACHE

        hotReloadWatcherThread = Thread {
            val appStorageDir = File(filesDir.parent, "app_storage")
            val reloadFile = File("${appStorageDir.absolutePath}/persisted_data/storage/framework/reload_signal.json")
            var lastModified: Long = 0

            while (!shouldStopWatcher && !Thread.currentThread().isInterrupted) {
                try {
                    if (reloadFile.exists() && reloadFile.lastModified() > lastModified) {
                        lastModified = reloadFile.lastModified()

                        runOnUiThread {
                            webView.stopLoading()
                            webView.clearCache(true)
                            webView.clearHistory()
                            webView.clearFormData()

                            val currentUrl = webView.url ?: "http://127.0.0.1/"
                            val separator = if (currentUrl.contains("?")) "&" else "?"
                            val cacheBustUrl = "${currentUrl}${separator}_cb=${System.currentTimeMillis()}"

                            Handler(Looper.getMainLooper()).postDelayed({
                                webView.loadUrl(cacheBustUrl)
                            }, 100)
                        }
                    }

                    Thread.sleep(500)
                } catch (e: InterruptedException) {
                    break
                } catch (e: Exception) {
                    Log.e("HotReload", "Watcher error: ${e.message}", e)
                    Thread.sleep(1000)
                }
            }
        }
        hotReloadWatcherThread?.start()
    }

    private fun isDebugVersion(): Boolean {
        return try {
            val appStorageDir = File(filesDir.parent, "app_storage")
            val versionFile = File(appStorageDir, "app/.version")

            if (versionFile.exists()) {
                val version = versionFile.readText().trim().trim('"').trim('\'')
                version.equals("DEBUG", ignoreCase = true)
            } else {
                false
            }
        } catch (e: Exception) {
            false
        }
    }


    private fun injectJavaScript(view: WebView) {
        val jsCode = """
        (function() {
            // Add platform identifier class
            document.body.classList.add('nativephp-android');

            // 🌐 Native event bridge
            const listeners = {};

            const Native = {
                on: function(eventName, callback) {
                    if (!listeners[eventName]) {
                        listeners[eventName] = [];
                    }
                    listeners[eventName].push(callback);
                },
                off: function(eventName, callback) {
                    if (listeners[eventName]) {
                        listeners[eventName] = listeners[eventName].filter(cb => cb !== callback);
                    }
                },
                dispatch: function(eventName, payload) {
                    const cbs = listeners[eventName] || [];
                    cbs.forEach(cb => cb(payload, eventName));

                    if (window.AndroidBridge && window.AndroidBridge.dispatch) {
                         const payloadStr = (typeof payload === 'object') ? JSON.stringify(payload) : String(payload);
                         window.AndroidBridge.dispatch(eventName, payloadStr);
                    }
                },
                openDrawer: function() {
                    if (window.AndroidBridge) {
                        window.AndroidBridge.openDrawer();
                    }
                }
            };

            window.Native = Native;

            window.addEventListener("native-event", function (e) {
                // Normalize event names by removing leading backslashes
                let eventName = e.detail.event.replace(/^(\\\\)+/, '');
                const payload = e.detail.payload;

                // Dispatch with normalized event name
                Native.dispatch(eventName, payload);

                // Also dispatch to Livewire if available
                if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                    window.Livewire.dispatch('native:' + eventName, payload);
                }
            });
        })();
        """
        view.evaluateJavascript(jsCode, null)
    }

    private fun injectSafeAreaInsets(left: Int, top: Int, right: Int, bottom: Int) {
        val density = resources.displayMetrics.density
        val displayMetrics = resources.displayMetrics

        // Get current screen dimensions (rotated)
        val currentWidthPx = (displayMetrics.widthPixels / density).toInt()
        val currentHeightPx = (displayMetrics.heightPixels / density).toInt()

        // Determine natural (portrait) dimensions
        // The smaller dimension is always the width in portrait orientation
        val portraitWidthPx = minOf(currentWidthPx, currentHeightPx)
        val portraitHeightPx = maxOf(currentWidthPx, currentHeightPx)

        val leftPx = (left / density).toInt()
        var topPx = (top / density).toInt()
        val rightPx = (right / density).toInt()
        val bottomPx = (bottom / density).toInt()

        // Check if native top bar is present - if so, set top inset to 0
        // The native top bar already handles status bar spacing
        val hasTopBar = NativeUIState.topBarData.value != null
        if (hasTopBar) {
            topPx = 0
            Log.d("SafeArea", "Native top bar detected - setting top inset to 0")
        }

        // Get actual device orientation from Android Configuration
        val isPortrait = resources.configuration.orientation == Configuration.ORIENTATION_PORTRAIT

        Log.d("SafeArea", "Device orientation: ${if (isPortrait) "Portrait" else "Landscape"}")
        Log.d("SafeArea", "Current screen dimensions: ${currentWidthPx}x${currentHeightPx}")
        Log.d("SafeArea", "Natural (portrait) dimensions: ${portraitWidthPx}x${portraitHeightPx}")
        Log.d("SafeArea", "Injecting insets: top=${topPx}px, right=${rightPx}px, bottom=${bottomPx}px, left=${leftPx}px")

        // Inject CSS as early as possible - create a self-executing function that runs immediately
        // and also sets up listeners for Livewire navigation to persist styles
        val jsCode = """
        (function() {
            function injectSafeAreaStyles() {
                // Remove existing safe-area style to avoid duplicates
                const existingStyle = document.getElementById('nativephp-safe-area-style');
                if (existingStyle) {
                    existingStyle.remove();
                }

                // Create style element with inset CSS variables and helper class
                const style = document.createElement('style');
                style.id = 'nativephp-safe-area-style';
                style.setAttribute('data-nativephp-persist', 'true');
                style.textContent = ':root { --inset-top: ${topPx}px; --inset-right: ${rightPx}px; --inset-bottom: ${bottomPx}px; --inset-left: ${leftPx}px; } .nativephp-safe-area { ${if (isPortrait) "padding-top: var(--inset-top); padding-bottom: var(--inset-bottom);" else "padding-right: var(--inset-right); padding-left: var(--inset-left);"} }';

                // Try to insert into head, or create head if it doesn't exist yet
                if (!document.head) {
                    const head = document.createElement('head');
                    if (document.documentElement) {
                        document.documentElement.insertBefore(head, document.documentElement.firstChild);
                    }
                }

                if (document.head) {
                    // Insert at the BEGINNING of head for highest priority
                    if (document.head.firstChild) {
                        document.head.insertBefore(style, document.head.firstChild);
                    } else {
                        document.head.appendChild(style);
                    }
                }

                // Also set CSS variables directly on documentElement for immediate availability
                // These persist across Livewire navigate because html element is not replaced
                if (document.documentElement) {
                    document.documentElement.style.setProperty('--inset-top', '${topPx}px');
                    document.documentElement.style.setProperty('--inset-right', '${rightPx}px');
                    document.documentElement.style.setProperty('--inset-bottom', '${bottomPx}px');
                    document.documentElement.style.setProperty('--inset-left', '${leftPx}px');

                    // Add orientation class to HTML element for Tailwind targeting
                    document.documentElement.classList.remove('portrait', 'landscape');
                    document.documentElement.classList.add('${if (isPortrait) "portrait" else "landscape"}');
                }

                console.log('SafeArea injected at ' + document.readyState + ': ${if (isPortrait) "portrait" else "landscape"}');
            }

            // Inject immediately
            injectSafeAreaStyles();

            // Re-inject when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', injectSafeAreaStyles);
            }

            // IMPORTANT: Re-inject after Livewire navigation to persist styles
            // Livewire can swap out the <head> content during navigate: true transitions
            document.addEventListener('livewire:navigated', function() {
                console.log('Livewire navigated - re-injecting safe area styles');
                injectSafeAreaStyles();
            });

            // Also listen for the older wire:navigate event (Livewire 2.x compatibility)
            document.addEventListener('wire:navigate', function() {
                console.log('Wire navigate - re-injecting safe area styles');
                injectSafeAreaStyles();
            });
        })();
        """
        webView.evaluateJavascript(jsCode, null)
    }

    // Public function called by WebViewManager on page load
    fun injectSafeAreaInsetsToWebView() {
        pendingInsets?.let {
            injectSafeAreaInsets(it.left, it.top, it.right, it.bottom)
        }
    }

    // Track keyboard visibility state to avoid redundant JS calls
    private var lastKeyboardVisible: Boolean? = null

    private fun injectKeyboardVisibility(isVisible: Boolean) {
        // Only inject if state actually changed
        if (lastKeyboardVisible == isVisible) return
        lastKeyboardVisible = isVisible

        val jsCode = if (isVisible) {
            "document.body.classList.add('keyboard-visible');"
        } else {
            "document.body.classList.remove('keyboard-visible');"
        }
        webView.evaluateJavascript(jsCode, null)
        Log.d("Keyboard", "⌨️ Keyboard visibility changed: $isVisible")
    }

    /**
     * Extract path and query from URL, handling both full URLs and relative paths
     * Supports Laravel route() helper output and relative paths
     */
    private fun extractPath(url: String): String {
        Log.d("Navigation", "📥 Received URL: $url")

        return try {
            if (url.startsWith("http://") || url.startsWith("https://")) {
                // Parse as full URL and extract path + query
                val parsedUrl = URL(url)
                // URL.getPath() returns empty string for root, not null - handle both cases
                val path = if (parsedUrl.path.isNullOrEmpty()) "/" else parsedUrl.path
                val query = parsedUrl.query
                val result = if (query != null) "$path?$query" else path
                Log.d("Navigation", "✅ Extracted path from full URL: $result")
                result
            } else if (url.startsWith("/")) {
                // Already a path
                Log.d("Navigation", "✅ Using path as-is: $url")
                url
            } else {
                // Relative path, prepend /
                val result = "/$url"
                Log.d("Navigation", "✅ Converted relative to absolute: $result")
                result
            }
        } catch (e: Exception) {
            Log.e("Navigation", "❌ Error parsing URL: $url", e)
            // Fallback: treat as relative path
            val fallback = if (url.startsWith("/")) url else "/$url"
            Log.d("Navigation", "🔄 Using fallback: $fallback")
            fallback
        }
    }

    /**
     * Navigate using Inertia router if available, otherwise fall back to direct navigation.
     * This allows native edge component clicks to integrate with Inertia.js for SPA-like
     * navigation while maintaining compatibility with non-Inertia apps.
     */
    private fun navigateWithInertia(url: String) {
        val path = extractPath(url)
        Log.d("Navigation", "🚀 Navigating with Inertia check: $path")

        // Escape the path for JavaScript string (use double quotes to avoid issues with /)
        val escapedPath = path.replace("\\", "\\\\").replace("\"", "\\\"")

        val jsCode = """
            (function() {
                var path = "$escapedPath";
                console.log('[NativePHP] Navigation requested:', path);

                // Check if Inertia router is available
                if (typeof window.router !== 'undefined' && typeof window.router.visit === 'function') {
                    console.log('[NativePHP] Using Inertia router.visit():', path);
                    window.router.visit(path);
                } else {
                    console.log('[NativePHP] Inertia not available, using location.href');
                    window.location.href = path;
                }
            })();
        """.trimIndent()

        webView.evaluateJavascript(jsCode, null)
    }

    /**
     * Main Compose UI screen with WebView, navigation, and overlays
     * Side drawer wraps everything to avoid touch blocking issues
     */
    @Composable
    private fun MainScreen() {
        Box(Modifier.fillMaxSize()) {
            // Side drawer wraps the main content (correct ModalNavigationDrawer usage)
            SideDrawerContent(
                content = {
                    // Get FAB position from state
                    val fabData by NativeUIState.fabData
                    val fabPosition = when (fabData?.position?.lowercase()) {
                        "center" -> FabPosition.Center
                        "start" -> FabPosition.Start
                        else -> FabPosition.End  // Default to end (bottom-right)
                    }

                    // Scaffold provides standard Material3 layout with FAB support
                    // Configure for edge-to-edge by using zero content window insets
                    Scaffold(
                        topBar = {
                            NativeTopBar(
                                onMenuClick = {
                                    Log.d("Navigation", "🍔 Menu button clicked - opening drawer")
                                },
                                onNavigate = { url ->
                                    Log.d("Navigation", "⚡ TopBar action navigation clicked")
                                    navigateWithInertia(url)
                                }
                            )
                        },
                        bottomBar = {
                            BottomNavigationContent()
                        },
                        floatingActionButton = {
                            NativeFab(
                                onNavigate = { url ->
                                    Log.d("Navigation", "🖱️ FAB navigation clicked")
                                    navigateWithInertia(url)
                                },
                                onEvent = { eventName ->
                                    Log.d("NativeEvent", "🖱️ FAB event dispatched: $eventName")
                                    // Dispatch native event via JavaScript
                                    val jsCode = """
                                        if (window.Native) {
                                            window.Native.dispatch('$eventName', {});
                                        }
                                    """.trimIndent()
                                    webView.evaluateJavascript(jsCode, null)
                                }
                            )
                        },
                        floatingActionButtonPosition = fabPosition,
                        contentWindowInsets = WindowInsets(0, 0, 0, 0)
                    ) { paddingValues ->
                        // Main content: WebView only
                        // Use paddingValues to respect TopBar and BottomNav heights
                        // IMPORTANT: Add IME (keyboard) inset padding so content isn't hidden behind keyboard

                        AndroidView(
                            factory = { webView },
                            modifier = Modifier
                                .fillMaxSize()
                                .padding(paddingValues)
                                .windowInsetsPadding(WindowInsets.ime),
                            update = { view ->
                                // Force layout recalculation when Compose size changes
                                // This ensures viewport units (100vh, 100vw) work correctly
                                view.requestLayout()
                            }
                        )
                    }
                }
            )

            // Splash overlay with fade animation (full screen, no insets)
            AnimatedVisibility(
                visible = showSplash,
                exit = fadeOut(animationSpec = tween(300))
            ) {
                SplashScreen()
            }
        }
    }

    /**
     * Splash screen composable - shows custom image or fallback text
     */
    @Composable
    private fun SplashScreen() {
        val splashResourceId = remember {
            try {
                resources.getIdentifier("splash", "drawable", packageName)
            } catch (e: Exception) {
                0
            }
        }

        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(Color.Black),
            contentAlignment = Alignment.Center
        ) {
            if (splashResourceId != 0) {
                Image(
                    painter = painterResource(id = splashResourceId),
                    contentDescription = "App splash screen",
                    modifier = Modifier.fillMaxSize(),
                    contentScale = ContentScale.Crop
                )
            } else {
                SplashText()
            }
        }
    }

    @Composable
    private fun SplashText() {
        Box(
            modifier = Modifier.fillMaxSize(),
            contentAlignment = Alignment.BottomCenter
        ) {
            Text(
                text = "Loading…",
                fontSize = 16.sp,
                color = Color.White,
                modifier = Modifier.padding(bottom = 64.dp)
            )
        }
    }

    /**
     * Bottom navigation composable
     */
    @Composable
    private fun BottomNavigationContent() {
        val systemInDarkMode = isSystemInDarkTheme()
        val bottomNavData by NativeUIState.bottomNavData
        val useDarkTheme = bottomNavData?.dark ?: systemInDarkMode
        val colorScheme = if (useDarkTheme) darkColorScheme() else lightColorScheme()

        MaterialTheme(colorScheme = colorScheme) {
            NativeBottomNavigation(
                onNavigate = { url ->
                    Log.d("Navigation", "🖱️ Bottom nav item clicked")
                    navigateWithInertia(url)
                }
            )
        }
    }

    /**
     * Side drawer composable - wraps main content in ModalNavigationDrawer
     */
    @Composable
    private fun SideDrawerContent(content: @Composable () -> Unit) {
        val systemInDarkMode = isSystemInDarkTheme()
        val sideNavData by NativeUIState.sideNavData
        val useDarkTheme = sideNavData?.dark ?: systemInDarkMode
        val colorScheme = if (useDarkTheme) darkColorScheme() else lightColorScheme()

        MaterialTheme(colorScheme = colorScheme) {
            NativeSideDrawer(
                onNavigate = { url ->
                    Log.d("Navigation", "🖱️ Side nav item clicked")
                    navigateWithInertia(url)
                },
                onDrawerStateChange = { isOpen ->
                    Log.d("SideDrawer", "Drawer state changed: $isOpen")
                },
                content = content
            )
        }
    }

    inner class AndroidBridge {
        @android.webkit.JavascriptInterface
        fun dispatch(event: String, payload: String): String {
             Log.d("AndroidBridge", "⚡ dispatch() called: $event, $payload")

             try {
                 // 1. Check registry
                 val function = BridgeFunctionRegistry.shared.get(event)
                 if (function == null) {
                     Log.w("AndroidBridge", "⚠️ Function not found: $event")
                     return "{}"
                 }

                // 2. Parse Payload (robust to arrays/empty/null)
                val params = mutableMapOf<String, Any>()
                val trimmed = payload.trim()
                if (trimmed.isNotEmpty() && trimmed.lowercase() != "null") {
                    try {
                        if (trimmed.startsWith("[")) {
                            // If array, use first object or ignore if empty
                            val arr = org.json.JSONArray(trimmed)
                            if (arr.length() > 0) {
                                val obj = arr.getJSONObject(0)
                                val keys = obj.keys()
                                while (keys.hasNext()) {
                                    val key = keys.next()
                                    params[key] = obj.get(key)
                                }
                            } // else: empty array => no params
                        } else {
                            val json = JSONObject(trimmed)
                            val keys = json.keys()
                            while (keys.hasNext()) {
                                val key = keys.next()
                                params[key] = json.get(key)
                            }
                        }
                    } catch (je: Exception) {
                        Log.w("AndroidBridge", "⚠️ Payload parse fallback for event $event: ${je.message}")
                    }
                }

                // 3. Execute
                return try {
                    val result = function.execute(params)
                    JSONObject(result).toString()
                } catch (e: Exception) {
                     Log.e("AndroidBridge", "❌ Error executing function $event", e)
                     val errorMap = mapOf("error" to (e.message ?: "Unknown error"))
                     JSONObject(errorMap).toString()
                 }

             } catch (e: Exception) {
                 Log.e("AndroidBridge", "❌ Bridge error", e)
                 return "{}"
             }
        }

        @android.webkit.JavascriptInterface
        fun openDrawer() {
            Log.d("AndroidBridge", "🖱️ openDrawer() called from JavaScript")
            runOnUiThread {
                // Check if we have side nav data first
                val hasData = NativeUIState.sideNavData.value != null &&
                             !NativeUIState.sideNavData.value?.children.isNullOrEmpty()

                if (!hasData) {
                    Log.w("AndroidBridge", "⚠️ Cannot open drawer - no side nav data available")
                    return@runOnUiThread
                }

                if (NativeUIState.drawerScope == null) {
                    Log.e("AndroidBridge", "❌ drawerScope is null!")
                    return@runOnUiThread
                }
                if (NativeUIState.drawerState == null) {
                    Log.e("AndroidBridge", "❌ drawerState is null!")
                    return@runOnUiThread
                }

                // Open drawer via Compose state
                NativeUIState.drawerScope?.launch {
                    NativeUIState.drawerState?.open()
                    Log.d("AndroidBridge", "✅ Drawer opened!")
                }
            }
        }
    }

}
