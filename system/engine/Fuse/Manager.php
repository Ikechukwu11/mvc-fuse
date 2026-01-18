<?php

namespace Engine\Fuse;

use Engine\Http\Request;
use Engine\Http\Response;
use Native\Mobile\Native;

/**
 * Fuse Manager
 *
 * Handles incoming Fuse requests, component hydration, action execution, and response generation.
 */
class Manager
{
    /**
     * Handle the Fuse request.
     *
     * Processes the AJAX payload, executes component actions, and returns
     * updated DOM or redirect instructions.
     *
     * @param Request $request Incoming HTTP request
     * @return array Response payload (HTML/Data or Redirect)
     */
    public function handleRequest(Request $request)
    {
        $payload = $request->input('payload');

        if (!$payload) {
            return ['error' => 'No payload'];
        }

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        $className = $payload['name'];
        $id = $payload['id'];
        $data = $payload['data'] ?? []; // Optional for lazy loading
        $action = $payload['action'] ?? null;
        $params = $payload['params'] ?? []; // This might be an array or null
        $lazyLoad = $payload['lazyLoad'] ?? false;

        if (!class_exists($className)) {
            return ['error' => "Component class '$className' not found"];
        }

        /** @var Component $component */
        $component = new $className();
        $component->setId($id);

        // Lifecycle: boot
        $component->boot();

        if ($lazyLoad) {
            // Lazy load: Initialize from params instead of snapshot
            $component->hydrate($params); // Use params as props
            $component->mount();
        } else {
            // Standard request: Hydrate from snapshot
            $component->hydrate($data);
        }

        // Lifecycle: hydrated
        $component->hydrated();

        try {
            // Perform action if any
            // Special actions: $refresh / $commit trigger a re-render without calling a method
            if ($action === '$refresh' || $action === '$commit') {
                $action = null;
            }
            if ($action && method_exists($component, $action)) {
                // Ensure params is an array
                if (!is_array($params)) {
                    $params = [$params];
                }
                try {
                    call_user_func_array([$component, $action], $params);
                } catch (ValidationException $e) {
                    foreach ($e->getErrors() as $field => $msg) {
                        $component->addError($field, $msg);
                    }
                } catch (\Throwable $e) {
                    $stop = false;
                    $component->exception($e, $stop);
                    if (!$stop) {
                        throw $e;
                    }
                }
            }

            // Check for redirect
            if ($redirect = $component->getRedirectUrl()) {
                return [
                    'redirect' => $redirect,
                    'navigate' => $component->getRedirectNavigate(),
                ];
            }

            // Lifecycle: dehydrated
            $component->dehydrated();

            // Re-render
            $html = $component->output();

            $response = [
                'html' => $html,
                'data' => $component->getPublicProperties(), // Return updated data
                'events' => $component->getEvents()
            ];

            // Include any native calls
            $nativeCalls = Native::flush();
            if (!empty($nativeCalls)) {
                $response['native_events'] = $nativeCalls;
            }

            return $response;

        } catch (\Throwable $e) {
            // If we are in debug mode, return the exception as a view overlay
            if (env('APP_DEBUG', false)) {
                $trace = $e->getTraceAsString();
                $class = get_class($e);
                $message = $e->getMessage();
                $file = $e->getFile();
                $line = $e->getLine();

                $errorHtml = <<<HTML
<div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);color:#fff;z-index:99999;overflow:auto;font-family:monospace;padding:20px;">
    <div style="background:#1f2937;border:1px solid #374151;border-radius:8px;padding:24px;max-width:900px;margin:40px auto;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:20px;">
            <div>
                <span style="background:#ef4444;color:white;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:bold;text-transform:uppercase;">$class</span>
                <h2 style="margin:12px 0;font-size:20px;line-height:1.4;">$message</h2>
            </div>
            <button onclick="document.getElementById('fuse-error-overlay').remove()" style="background:none;border:none;color:#9ca3af;cursor:pointer;font-size:24px;">&times;</button>
        </div>
        <div style="background:#111827;padding:16px;border-radius:6px;font-size:13px;color:#d1d5db;margin-bottom:20px;">
            <div style="margin-bottom:8px;"><strong style="color:#60a5fa;">File:</strong> $file</div>
            <div><strong style="color:#60a5fa;">Line:</strong> $line</div>
        </div>
        <div style="background:#000;padding:16px;border-radius:6px;overflow-x:auto;">
            <pre style="margin:0;font-size:12px;color:#10b981;white-space:pre-wrap;">$trace</pre>
        </div>
    </div>
</div>
HTML;
                return ['error_html' => $errorHtml];
            }
            throw $e;
        }
    }
}
