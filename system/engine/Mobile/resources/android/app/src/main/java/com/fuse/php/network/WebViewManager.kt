package com.fuse.php.network

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.util.Log
import android.webkit.*
import android.widget.Toast
import android.view.View
import android.view.ViewGroup
import android.widget.FrameLayout
import android.content.pm.ActivityInfo
import android.app.Activity
import com.acsbendi.requestinspectorwebview.RequestInspectorWebViewClient
import com.fuse.php.bridge.PHPBridge
import com.fuse.php.ui.MainActivity
import com.fuse.php.ui.NativeUIState
import org.json.JSONObject
import com.fuse.php.security.MobileSecurity

class WebViewManager(
    private val context: Context,
    private val webView: WebView,
    private val phpBridge: PHPBridge
) {
    private val TAG = "PHPMonitor"
    private var fullscreenView: View? = null
    private var customViewCallback: WebChromeClient.CustomViewCallback? = null

    companion object {
        var shared: WebViewManager? = null
    }

    fun setup() {
        configureWebViewSettings()
        setupCookieManager()
        setupWebViewClient()
        setupJavaScriptInterfaces()
        WebViewManager.shared = this // 👈 make this instance globally accessible
    }

    private fun configureWebViewSettings() {
        // Don't clear cache on every setup - let it persist for performance
        // webView.clearCache(true)
        // webView.clearHistory()

        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
            allowFileAccess = true
            allowContentAccess = true
            loadsImagesAutomatically = true
            blockNetworkImage = false
            mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
            mediaPlaybackRequiresUserGesture = false // Allows autoplay
            setSupportMultipleWindows(true) // Required for fullscreen
            cacheMode = WebSettings.LOAD_CACHE_ELSE_NETWORK // Prefer cache for faster loads
        }

        WebView.setWebContentsDebuggingEnabled(true)
    }

    private fun setupCookieManager() {
        CookieManager.getInstance().apply {
            setAcceptCookie(true)
            setAcceptThirdPartyCookies(webView, true)
        }
    }

    private fun setupWebViewClient() {
        webView.webChromeClient = createWebChromeClient()
        webView.webViewClient = createCustomWebViewClient()
    }

    private fun createWebChromeClient(): WebChromeClient {
        return object : WebChromeClient() {
            override fun onShowCustomView(view: View, callback: CustomViewCallback) {
                fullscreenView?.let { onHideCustomView() }

                fullscreenView = view
                customViewCallback = callback

                (context as? Activity)?.let { activity ->
                    val decorView = activity.window.decorView as FrameLayout
                    decorView.addView(view,
                        FrameLayout.LayoutParams(
                            ViewGroup.LayoutParams.MATCH_PARENT,
                            ViewGroup.LayoutParams.MATCH_PARENT
                        )
                    )
                }

                webView.visibility = View.GONE

                (context as? Activity)?.requestedOrientation =
                    ActivityInfo.SCREEN_ORIENTATION_LANDSCAPE
            }

            override fun onHideCustomView() {
                (context as? Activity)?.let { activity ->
                    val decorView = activity.window.decorView as FrameLayout

                    fullscreenView?.let { decorView.removeView(it) }
                    fullscreenView = null

                    webView.visibility = View.VISIBLE

                    activity.requestedOrientation =
                        ActivityInfo.SCREEN_ORIENTATION_UNSPECIFIED

                    customViewCallback?.onCustomViewHidden()
                    customViewCallback = null
                }
            }

            override fun onConsoleMessage(consoleMessage: ConsoleMessage): Boolean {
                Log.d(
                    "$TAG-Console",
                    "${consoleMessage.message()} -- From line ${consoleMessage.lineNumber()}"
                )
                return true
            }
        }
    }

    private fun createCustomWebViewClient(): WebViewClient {
        return object : WebViewClient() {
            private val requestInspector = RequestInspectorWebViewClient(webView)
            private val phpHandler = PHPWebViewClient(phpBridge, context as MainActivity)

            override fun shouldOverrideUrlLoading(
                view: WebView,
                request: WebResourceRequest
            ): Boolean {
                val url = request.url.toString()
                val method = request.method
                Log.d("$TAG-DEBUG", "URL: $url, Method: $method")
                Log.d(TAG, "⬆️ shouldOverrideUrlLoading: $url")

                // Handle system URL schemes (tel:, mailto:, sms:, geo:) - open with system handler
                val scheme = request.url.scheme?.lowercase()
                if (scheme in listOf("tel", "mailto", "sms", "geo")) {
                    Log.d("WebView", "📞 Intercepted system URL scheme: $url")
                    val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                    intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK
                    try {
                        context.startActivity(intent)
                    } catch (e: ActivityNotFoundException) {
                        Log.e("WebView", "No app can handle $scheme: links")
                        Toast.makeText(context, "No app can handle this link", Toast.LENGTH_SHORT).show()
                    }
                    return true
                }

                if (url.startsWith("nativephp://")) {
                    Log.d("WebView", "🔗 Intercepted deep link inside WebView: $url")

                    val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                    intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK

                    try {
                        context.startActivity(intent)
                    } catch (e: ActivityNotFoundException) {
                        Toast.makeText(context, "No app can handle this link", Toast.LENGTH_SHORT).show()
                    }

                    return true // prevent WebView from loading it
                }

                if ((url.startsWith("http://") || url.startsWith("https://")) &&
                    !url.contains("127.0.0.1") &&
                    !url.contains("localhost") &&
                    request.isForMainFrame
                ) {
                    // This is a navigation request to an external site - open in browser
                    val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                    view.context.startActivity(intent)
                    return true
                }

                // Handle relative URLs (convert to php://)
                if (url.startsWith("/")) {
                    val uri = request.url
                    val fullUrl = "http://127.0.0.1${uri.encodedPath}" +
                        (uri.encodedQuery?.let { "?$it" } ?: "")

                    Log.d(TAG, "🛠️ Rewriting relative URL with query: $fullUrl")
                    view.loadUrl(fullUrl)
                    return true
                }

                return false
            }

            override fun shouldInterceptRequest(
                view: WebView,
                request: WebResourceRequest
            ): WebResourceResponse? {
                val url = request.url.toString()
                val method = request.method

                Log.d(TAG, "🔄 Intercepting $method request to $url")

                request.requestHeaders.forEach { (key, value) ->
                    Log.d("$TAG-Headers", "📋 $key: $value")
                }

                val inspectorResponse = requestInspector.shouldInterceptRequest(view, request)

                if (url.startsWith("http://") && !url.contains(".") && !url.contains("127.0.0.1") && !url.contains("localhost")) {
                    val host = url.substring("http://".length).substringBefore("/")
                    val path = if (url.contains("/")) "/${url.substringAfter("/")}" else "/"
                    val correctedUrl = "http://127.0.0.1/$host$path"

                    Log.d(TAG, "🔄 Correcting malformed URL from $url to $correctedUrl")

                    // Create a modified request with the corrected URL
                    val correctedUri = Uri.parse(correctedUrl)
                    val correctedRequest = object : WebResourceRequest {
                        override fun getUrl(): Uri = correctedUri
                        override fun isForMainFrame(): Boolean = request.isForMainFrame
                        override fun isRedirect(): Boolean = request.isRedirect
                        override fun hasGesture(): Boolean = request.hasGesture()
                        override fun getMethod(): String = request.method
                        override fun getRequestHeaders(): Map<String, String> = request.requestHeaders
                    }

                    // Handle this corrected request normally
                    return shouldInterceptRequest(view, correctedRequest)
                }

                if (!url.contains("127.0.0.1") && !url.contains("localhost")) {
                    // This is an external resource - let the WebView handle it directly
                    Log.d(TAG, "📡 External resource - passing to system: $url")
                    return null // Returning null lets the WebView load it normally
                }

                // Allow Vite dev server (port 5173) to handle its own requests, including WebSocket upgrades for HMR
                if (url.contains(":5173")) {
                    Log.d(TAG, "🔥 Vite dev server request - allowing native WebView handling: $url")
                    return null
                }

                return when {
                    isStaticAssetExtension(url) ||
                            url.contains("_assets") ||
                            url.contains("/js/") ||
                            url.contains("/css/") ||
                            url.contains("/fonts/") ||
                            url.contains("/images/") -> {
                        Log.d(TAG, "🖼️ Handling asset request")
                        phpHandler.handleAssetRequest(url, request.requestHeaders)
                    }
                    // Regular PHP requests
                    url.contains("127.0.0.1") -> {
                        Log.d(TAG, "🌐 Handling PHP request")
                        phpHandler.handlePHPRequest(request, phpBridge.getLastPostData())
                    }
                    else -> {
                        Log.d(TAG, "↪️ Delegating to system handler: $url")
                        inspectorResponse
                    }
                }
            }

            override fun onPageStarted(view: WebView, url: String, favicon: android.graphics.Bitmap?) {
                super.onPageStarted(view, url, favicon)
                Log.d(TAG, "🚀 Page started loading: $url")

                // Inject safe area insets IMMEDIATELY when page starts loading
                // This ensures CSS variables are available before DOM parsing
                (context as? MainActivity)?.injectSafeAreaInsetsToWebView()
                // Install native-event bridge as early as possible to avoid missed events on initial load
                injectJavaScript(view)
            }

            /**
             * Process response headers - for HTML and JSON responses to handle native UI updates
             * from both page loads and AJAX requests
             */
            private fun processResponseHeaders(
                url: String,
                response: WebResourceResponse?,
                request: WebResourceRequest
            ) {
                if (response == null) {
                    return
                }

                val isMainFrame = request.isForMainFrame

                // Get content type
                val contentType = response.responseHeaders?.entries?.firstOrNull {
                    it.key.equals("content-type", ignoreCase = true)
                }?.value ?: ""

                val isHtmlResponse = contentType.contains("text/html", ignoreCase = true)
                val isJsonResponse = contentType.contains("application/json", ignoreCase = true)

                // Find x-native-ui header (case-insensitive)
                val nativeUiHeader = response.responseHeaders?.entries?.firstOrNull {
                    it.key.equals("x-native-ui", ignoreCase = true)
                }?.value

                // Process for HTML pages (main frame) or JSON responses (AJAX)
                if (isHtmlResponse || isJsonResponse) {
                    if (nativeUiHeader != null) {
                        Log.d(TAG, "✅ x-native-ui header found (${if (isJsonResponse) "JSON" else "HTML"}): $nativeUiHeader")
                        NativeUIState.updateFromJson(nativeUiHeader)
                    } else if (isHtmlResponse && isMainFrame) {
                        // Only clear UI state if this is a main frame HTML response without the header
                        // Don't clear for JSON responses to avoid clearing UI on every API call
                        Log.d(TAG, "❌ x-native-ui header NOT in HTML main frame - clearing state")
                        NativeUIState.clearAll()
                    }
                } else {
                    // Asset request - ignore completely to avoid false negatives
                    Log.d(TAG, "⏭️ Skipping x-native-ui check for asset: $url")
                }
            }


            override fun onPageFinished(view: WebView, url: String) {
                super.onPageFinished(view, url)
                Log.d(TAG, "✅ Page finished loading: $url")

                // Inject safe area insets again to ensure they're set
                (context as? MainActivity)?.injectSafeAreaInsetsToWebView()

                // Inject JavaScript to capture form submissions and AJAX requests
                injectJavaScript(view)
            }
        }
    }


    private fun injectJavaScript(view: WebView) {
        val jsCode = """
        (function() {
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
                }
            };

            window.Native = Native;

            // Flag to indicate native-event is being forwarded by injected bridge
            window.__nativeEventForwarder = true;
            // Buffer to store early native events until app listeners are ready
            window.__nativeEventBuffer = window.__nativeEventBuffer || [];

            document.addEventListener("native-event", function (e) {
                const eventName = e.detail.event;
                const payload = e.detail.payload;

                // Buffer the event for later flush by client when ready
                try {
                    window.__nativeEventBuffer.push({ name: eventName, detail: payload });
                } catch (err) {}

                // Forward to window listeners (Fuse components listen on window)
                try {
                    window.dispatchEvent(new CustomEvent(eventName, { detail: payload }));
                } catch (err) {}

                // Forward to Native listener registry for any direct subscribers
                window.Native.dispatch(eventName, payload);


            });

            // Capture form submissions (event listener)
            document.addEventListener('submit', function(e) {
                var form = e.target;

                // Prevent default submission to ensure we capture data first
                // and avoid race conditions with WebView interception
                if (!form.getAttribute('data-processed')) {
                    e.preventDefault();
                    console.log('📝 Intercepting form submission for bridge capture');

                    captureFormSubmission(form);

                    // Mark as processed
                    form.setAttribute('data-processed', 'true');

                    // Small delay to ensure bridge receives data before network request starts
                    setTimeout(function() {
                        console.log('🚀 Resuming form submission');
                        form.submit();
                        // Reset after submit (though page usually navigates away)
                        setTimeout(function() { form.removeAttribute('data-processed'); }, 1000);
                    }, 100);
                }
            });

            // Capture programmatic form.submit()
            var originalFormSubmit = HTMLFormElement.prototype.submit;
            HTMLFormElement.prototype.submit = function() {
                captureFormSubmission(this);
                originalFormSubmit.apply(this, arguments);
            };

            function captureFormSubmission(form) {
                var method = (form.method || 'GET').toLowerCase();
                if (["post", "patch", "put"].includes(method)) {
                    var formData = new FormData(form);
                    var urlEncodedData = new URLSearchParams();
                    for (var pair of formData.entries()) {
                        urlEncodedData.append(pair[0], pair[1]);
                    }

                    // Handle action (resolve relative to absolute if needed, or let bridge handle it)
                    var action = form.action || window.location.href;

                    AndroidPOST.logPostData(urlEncodedData.toString(), action, "Content-Type: application/x-www-form-urlencoded");
                }
            }

            // Capture XHR/AJAX requests
            var originalXHROpen = XMLHttpRequest.prototype.open;
            var originalXHRSend = XMLHttpRequest.prototype.send;

            XMLHttpRequest.prototype.open = function(method, url) {
                this._method = method;
                this._url = url;
                return originalXHROpen.apply(this, arguments);
            };

            XMLHttpRequest.prototype.send = function(data) {
                if (["post", "patch", "put"].includes(this._method.toLowerCase()) && data) {
                    var headers = "";

                    if (this.getAllResponseHeaders) {
                        headers = this.getAllResponseHeaders();
                    }

                    AndroidPOST.logPostData(data, this._url, headers);
                }
                return originalXHRSend.apply(this, arguments);
            };

            // Capture fetch() requests
            var originalFetch = window.fetch;

            window.fetch = function(url, options) {
                if (options && options.method && ["post", "patch", "put"].includes(options.method.toLowerCase()) && options.body) {
                    var headerString = "";

                    if (options.headers) {
                        if (options.headers instanceof Headers) {
                            options.headers.forEach(function(value, key) {
                                headerString += key + ": " + value + "\\n";
                            });
                        } else if (typeof options.headers === 'object') {
                            Object.keys(options.headers).forEach(function(key) {
                                headerString += key + ": " + options.headers[key] + "\\n";
                            });
                        }
                    }

                    var bodyStr = options.body;
                    if (options.body instanceof FormData) {
                        var formObj = {};
                        options.body.forEach(function(value, key) {
                            formObj[key] = value;
                        });
                        bodyStr = JSON.stringify(formObj);
                    } else if (typeof options.body === 'object') {
                        bodyStr = JSON.stringify(options.body);
                    }

                    AndroidPOST.logPostData(bodyStr, url, headerString);
                }
                return originalFetch.apply(this, arguments);
            };

            // Find CSRF token
            function findAndSendCsrfToken() {
                var tokenField = document.querySelector('input[name="_token"]');
                if (tokenField) {
                    AndroidPOST.storeCsrfToken(tokenField.value);
                    return;
                }

                if (window.livewire && window.livewire.csrfToken) {
                    AndroidPOST.storeCsrfToken(window.livewire.csrfToken);
                }
            }

            findAndSendCsrfToken();

            var observer = new MutationObserver(function() {
                findAndSendCsrfToken();
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            return "POST+PATCH+PUT interception installed";
        })();
    """.trimIndent()

        view.evaluateJavascript(jsCode) { result ->
            Log.d(TAG, "JavaScript injection result: $result")
        }
    }


    private fun setupJavaScriptInterfaces() {
        webView.addJavascriptInterface(JSBridge(phpBridge, TAG), "AndroidPOST")
    }

    // Helper methods
    fun isStaticAssetExtension(url: String): Boolean {
        val staticExtensions = listOf(
            ".js", ".css", ".png", ".jpg", ".jpeg", ".gif", ".svg", ".woff",
            ".woff2", ".ttf", ".eot", ".ico", ".json", ".map"
        )
        return staticExtensions.any { url.endsWith(it) || url.contains("$it?") }
    }
}

class JSBridge(private val phpBridge: PHPBridge, private val TAG: String) {
    @JavascriptInterface
    fun logPostData(data: String, url: String, headers: String) {
        // Store using the URL as key
        Log.d("$TAG-JS", "📦 Captured POST data for: $url")
        // Pass headers to bridge so they can be merged in PHPWebViewClient
        phpBridge.storeRequestData(url, data, headers)
    }

    @JavascriptInterface
    fun storeCsrfToken(token: String) {
        Log.d("$TAG-CSRF", "🔑 JS provided token: $token")
        MobileSecurity.set(token)
    }

    private fun extractCsrfToken(postData: String?) {
        if (postData.isNullOrEmpty()) return

        try {
            // Check if it's JSON
            if (postData.startsWith("{")) {
                val jsonObj = JSONObject(postData)

                // Look for _token field
                if (jsonObj.has("_token")) {
                    val token = jsonObj.getString("_token")
                    Log.d("$TAG-CSRF", "🔑 Extracted token from POST data: $token")
                    MobileSecurity.set(token)
                }
            }
            // Check for form data format
            else if (postData.contains("_token=")) {
                val parts = postData.split("&")
                for (part in parts) {
                    if (part.startsWith("_token=")) {
                        val token = part.substring("_token=".length)
                        Log.d("$TAG-CSRF", "🔑 Extracted token from form data: $token")
                        MobileSecurity.set(token)
                        break
                    }
                }
            }
        } catch (e: Exception) {
            Log.e("$TAG-CSRF", "⚠️ Error extracting CSRF token: ${e.message}")
        }
    }
}
