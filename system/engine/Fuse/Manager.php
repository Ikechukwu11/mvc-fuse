<?php

namespace Engine\Fuse;

use Engine\Http\Request;
use Engine\Http\Response;

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

        return [
            'html' => $html,
            'data' => $component->getPublicProperties(), // Return updated data
            'events' => $component->getEvents()
        ];
    }
}
