<?php

namespace Engine\Fuse;

use Engine\Support\Validator;
use ReflectionClass;
use ReflectionProperty;

/**
 * Base Component Class
 *
 * Abstract base class for all Fuse components.
 * Handles state management, rendering, and client-server communication.
 */
abstract class Component
{
    /**
     * @var string Unique component ID
     */
    protected string $id;

    /**
     * @var string Component name
     */
    protected string $name;

    /**
     * @var array Snapshot of component state
     */
    protected array $snapshot = [];

    /**
     * @var string|null URL to redirect to on the client side
     */
    protected ?string $redirectTo = null;
    /**
     * @var bool Whether client should use SPA navigation when redirecting
     */
    protected bool $redirectNavigate = false;

    /**
     * @var string Layout view to use when rendering as a full page
     */
    protected string $layout = 'layouts/main';

    /**
     * @var bool Whether to lazy load this component
     */
    public bool $lazy = false;

    /**
     * Get the placeholder view or HTML for lazy loading.
     *
     * @param array $params
     * @return string
     */
    public function placeholder(array $params = []): string
    {
        return <<<HTML
        <div class="animate-pulse flex space-x-4">
            <div class="flex-1 space-y-4 py-1">
                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                <div class="space-y-2">
                    <div class="h-4 bg-gray-200 rounded"></div>
                    <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                </div>
            </div>
        </div>
HTML;
    }

    /**
     * @var array List of events to dispatch to the browser
     */
    protected array $events = [];

    /**
     * @var array Validation errors
     */
    protected array $errors = [];

    /**
     * Dispatch a browser event.
     *
     * @param string $event Event name
     * @param mixed $detail Event detail data
     * @return void
     */
    public function dispatch(string $event, mixed $detail = null)
    {
        $this->events[] = [
            'name' => $event,
            'detail' => $detail
        ];
    }

    /**
     * Get pending events.
     *
     * @return array
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * Validate the component properties.
     *
     * @param array $rules
     * @return array Validated data
     * @throws ValidationException
     */
    public function validate(array $rules = []): array
    {
        $this->errors = [];
        $validated = [];

        foreach ($rules as $field => $ruleString) {
            $value = $this->{$field} ?? null;
            $validated[$field] = $value;

            // Handle array rules or pipe-separated string
            $rulesList = is_string($ruleString) ? explode('|', $ruleString) : $ruleString;

            foreach ($rulesList as $rule) {
                $rule = trim($rule);

                if ($rule === 'required' && (is_null($value) || $value === '')) {
                    $this->addError($field, "The $field field is required.");
                } elseif ($rule === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The $field field must be a valid email.");
                } elseif (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (is_string($value) && strlen($value) < $min) {
                        $this->addError($field, "The $field field must be at least $min characters.");
                    }
                } elseif (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (is_string($value) && strlen($value) > $max) {
                        $this->addError($field, "The $field field must not exceed $max characters.");
                    }
                }
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return $validated;
    }

    /**
     * Add a validation error.
     *
     * @param string $field
     * @param string $message
     */
    public function addError(string $field, string $message)
    {
        $this->errors[$field] = $message;
    }

    /**
     * Get validation errors.
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if a field has an error.
     *
     * @param string $field
     * @return bool
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get error message for a field.
     *
     * @param string $field
     * @return string|null
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Initialize the component with a random ID.
     */
    public function __construct()
    {
        $this->id = bin2hex(random_bytes(10));
    }

    /**
     * Lifecycle hook: Called on every request.
     *
     * Allows components to run code on every lifecycle pass.
     * This is a no-op by default.
     *
     * @return void
     */
    public function boot()
    {
        // No-op by default
    }

    /**
     * Lifecycle hook: Called after hydration on subsequent requests.
     *
     * Useful for reacting after public properties have been filled
     * from the incoming payload. No-op by default.
     *
     * @return void
     */
    public function hydrated()
    {
        // No-op by default
    }

    /**
     * Lifecycle hook: Called before rendering the response.
     *
     * Gives components a chance to finalize state before output.
     * No-op by default.
     *
     * @return void
     */
    public function dehydrated()
    {
        // No-op by default
    }

    /**
     * Lifecycle hook: Called before render() is executed.
     *
     * Gives components a chance to prepare state before template rendering.
     * No-op by default.
     *
     * @return void
     */
    public function rendering()
    {
        // No-op by default
    }

    /**
     * Lifecycle hook: Called after render() is executed.
     *
     * Useful for post-render operations. No-op by default.
     *
     * @return void
     */
    public function rendered()
    {
        // No-op by default
    }

    /**
     * Lifecycle hook: Called when an exception is thrown during component handling.
     *
     * Components may set $stopPropagation = true to prevent the exception from bubbling.
     *
     * @param \Throwable $e
     * @param bool $stopPropagation Passed by reference; set to true to stop propagation
     * @return void
     */
    public function exception(\Throwable $e, bool &$stopPropagation = false)
    {
        // No-op by default
    }

    /**
     * Lifecycle hook: Called after hydration but before action execution.
     * Override this method to perform initialization logic.
     *
     * @return void
     */
    public function mount()
    {
        // Optional hook
    }

    /**
     * Magic method to handle computed properties.
     *
     * Accessing $this->foo will call getFooProperty() if it exists.
     *
     * @param string $property
     * @return mixed
     */
    public function __get($property)
    {
        $method = 'get' . str_replace('_', '', ucwords($property, '_')) . 'Property';
        if (method_exists($this, $method)) {
            return $this->$method();
        }
    }

    /**
     * Render the component's HTML.
     *
     * @return string
     */
    abstract public function render();

    /**
     * Set the component ID.
     *
     * @param string $id
     * @return void
     */
    public function setId(string $id)
    {
        $this->id = $id;
    }

    /**
     * Get the component ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Trigger a redirect on the client side.
     *
     * @param string $url Target URL
     * @return void
     */
    /**
     * Trigger a redirect on the client side.
     *
     * @param string $url Target URL
     * @param bool $navigate When true, instruct client to use SPA navigation
     * @return void
     */
    protected function redirect(string $url, bool $navigate = false)
    {
        $this->redirectTo = $url;
        $this->redirectNavigate = $navigate;
    }

    /**
     * Get the pending redirect URL.
     *
     * @return string|null
     */
    public function getRedirectUrl(): ?string
    {
        return $this->redirectTo;
    }

    /**
     * Get whether SPA navigation should be used for redirect.
     *
     * @return bool
     */
    public function getRedirectNavigate(): bool
    {
        return $this->redirectNavigate;
    }

    /**
     * Get public properties to send to frontend.
     *
     * Only public, non-static properties are synced.
     *
     * @return array
     */
    public function getPublicProperties(): array
    {
        $reflect = new ReflectionClass($this);
        $props = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC);
        $data = [];
        foreach ($props as $prop) {
            if (!$prop->isStatic() && $prop->isInitialized($this)) {
                $data[$prop->getName()] = $prop->getValue($this);
            }
        }
        return $data;
    }

    /**
     * Hydrate component state from data.
     *
     * @param array $data Key-value pairs of properties
     * @return void
     */
    public function hydrate(array $data)
    {
        $this->fill($data);
    }

    /**
     * Set properties on the component.
     *
     * @param array $values
     * @return void
     */
    public function fill(array $values)
    {
        foreach ($values as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Reset properties to their initial state.
     *
     * @param string|array ...$properties
     * @return void
     */
    public function reset(...$properties)
    {
        $properties = is_array($properties[0] ?? null) ? $properties[0] : $properties;
        $defaults = (new ReflectionClass($this))->getDefaultProperties();

        if (empty($properties)) {
            // Reset all public properties that have defaults
            // We should filter to only public ones to match getPublicProperties behavior mostly,
            // but reset() might be used for protected ones too internally.
            // For now, let's reset anything that has a default.
            foreach ($defaults as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $value;
                }
            }
            return;
        }

        foreach ($properties as $property) {
            if (array_key_exists($property, $defaults)) {
                $this->{$property} = $defaults[$property];
            }
        }
    }

    /**
     * Reset and return a property value.
     *
     * @param string|array|null $property
     * @return mixed
     */
    public function pull($property = null)
    {
        if (is_null($property)) {
            $values = $this->all();
            $this->reset();
            return $values;
        }

        if (is_array($property)) {
            $values = $this->only($property);
            $this->reset($property);
            return $values;
        }

        $value = $this->{$property} ?? null;
        $this->reset($property);
        return $value;
    }

    /**
     * Get all public properties.
     *
     * @return array
     */
    public function all()
    {
        return $this->getPublicProperties();
    }

    /**
     * Get a subset of properties.
     *
     * @param array|string $properties
     * @return array
     */
    public function only($properties)
    {
        $properties = is_array($properties) ? $properties : func_get_args();
        $results = [];
        foreach ($properties as $property) {
            $results[$property] = $this->{$property} ?? null;
        }
        return $results;
    }

    /**
     * Get all properties except the given ones.
     *
     * @param array|string $properties
     * @return array
     */
    public function except($properties)
    {
        $properties = is_array($properties) ? $properties : func_get_args();
        return array_diff_key($this->all(), array_flip($properties));
    }

    /**
     * Render the component to HTML with Fuse attributes.
     *
     * Injects `fuse:id` and `fuse:data` attributes into the root element.
     *
     * @return string Processed HTML
     */
    public function output(): string
    {
        $this->rendering();
        $viewContent = $this->render();
        $this->rendered();

        $data = [
            'id' => $this->id,
            'name' => static::class,
            'data' => $this->getPublicProperties(),
        ];

        $json = htmlspecialchars(json_encode($data), ENT_QUOTES);

        // Escape backslashes for preg_replace replacement string
        $replacementJson = str_replace('\\', '\\\\', $json);
        // Also escape $ signs if any
        $replacementJson = str_replace('$', '\\$', $replacementJson);

        // Inject into the first tag
        $viewContent = preg_replace(
            '/(<[a-zA-Z0-9-]+)/',
            '$1 fuse:id="' . $this->id . '" fuse:data="' . $replacementJson . '"',
            $viewContent,
            1
        );

        return $viewContent;
    }

    /**
     * Set the layout for the component.
     *
     * @param string $layout Layout view path (e.g., 'layouts/app')
     * @return void
     */
    public function setLayout(string $layout)
    {
        $this->layout = $layout;
    }

    /**
     * Get the current layout.
     *
     * @return string
     */
    public function getLayout(): string
    {
        return $this->layout;
    }

    /**
     * Render the component wrapped in a layout for full-page responses.
     *
     * Used when a component is accessed directly via a route.
     *
     * @param array $params Initial state parameters
     * @return string Full HTML page
     */
    public static function renderPage(array $params = []): string
    {
        $class = static::class;
        /** @var Component $component */
        $component = new $class();

        // Check for lazy loading
        if ($component->lazy) {
            $id = md5(uniqid('', true));
            $encodedParams = htmlspecialchars(json_encode($params), ENT_QUOTES, 'UTF-8');
            $encodedName = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
            $placeholder = $component->placeholder($params);

            $content = <<<HTML
<div fuse:id="{$id}" fuse:lazy fuse:name="{$encodedName}" fuse:params="{$encodedParams}">
    {$placeholder}
</div>
HTML;

            // Render layout with placeholder
            $layoutName = $component->getLayout();
            $base = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'views';
            $v = new \Engine\Support\View($base);

            // Hydrate simply to get default properties for the layout (e.g. title) if needed
            $component->hydrate($params);
            $data = $component->getPublicProperties();
            $data['content'] = $content;
            $data['title'] = $data['title'] ?? (new ReflectionClass($component))->getShortName();

            return $v->render($layoutName, $data);
        }

        $component->boot();

        $component->hydrate($params);
        $component->mount();

        // If redirect was set during mount, we might want to handle it?
        // But for initial page load, we should probably just redirect using header().
        if ($component->getRedirectUrl()) {
            header("Location: " . $component->getRedirectUrl());
            exit;
        }

        $component->dehydrated();

        $content = $component->output();

        // Get layout from component or config
        $layoutName = $component->getLayout();

        $base = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'views';
        $v = new \Engine\Support\View($base);

        // Pass content and any public properties as data
        $data = $component->getPublicProperties();
        $data['content'] = $content;
        $data['title'] = $data['title'] ?? (new ReflectionClass($component))->getShortName(); // Default title

        return $v->render($layoutName, $data);
    }
}
