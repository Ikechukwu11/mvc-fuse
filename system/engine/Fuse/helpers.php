<?php

/**
 * Generate Fuse scripts and configuration.
 *
 * Injects the Fuse JavaScript library and configuration (loading indicator settings).
 * Should be placed in the layout's <head> or before </body>.
 *
 * @return string HTML script/style tags
 */
function fuse_scripts(): string
{
    // Adjust path if base path is set
    $base = config('app.base_path', '');
    $config = config('fuse.loading', ['enabled' => true, 'color' => '#4F46E5', 'height' => '3px']);
    $configJson = json_encode($config);

    $script = <<<HTML
<script>
    window.FuseConfig = {
        loading: {$configJson}
    };
</script>
<script src="{$base}/js/fuse.js"></script>
HTML;

    if ($config['enabled']) {
        $color = $config['color'];
        $height = $config['height'];
        $spinnerCss = '';

        if (!empty($config['spinner'])) {
            $spinnerCss = <<<CSS
    #fuse-loading-spinner {
        position: fixed;
        top: 15px;
        right: 15px;
        width: 20px;
        height: 20px;
        border: 2px solid transparent;
        border-top-color: {$color};
        border-radius: 50%;
        z-index: 9999;
        animation: fuse-spin 0.8s linear infinite;
        opacity: 0;
        transition: opacity 0.2s ease;
        pointer-events: none;
    }
    @keyframes fuse-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
CSS;
        }

        $script .= <<<HTML
<style>
    #fuse-loading-bar {
        position: fixed;
        top: 0;
        left: 0;
        width: 0;
        height: {$height};
        background-color: {$color};
        z-index: 9999;
        transition: width 0.2s ease;
        box-shadow: 0 0 10px {$color};
    }
    {$spinnerCss}
</style>
HTML;
    }

    return $script;
}

function fuse(string $class, array $params = []): string
{
    if (!class_exists($class)) {
        // Try prepending App\Fuse\
        $class = "App\\Fuse\\" . $class;
        if (!class_exists($class)) {
            return "Component $class not found";
        }
    }

    /** @var \Engine\Fuse\Component $component */
    $component = new $class();

    // Check for lazy loading
    $lazy = $params['lazy'] ?? $component->lazy ?? false;
    unset($params['lazy']); // Remove lazy flag from params passed to mount

    if ($lazy) {
        // Generate a unique ID for the placeholder
        $id = md5(uniqid('', true));
        $encodedParams = htmlspecialchars(json_encode($params), ENT_QUOTES, 'UTF-8');
        $encodedName = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $placeholder = $component->placeholder($params);

        return <<<HTML
<div fuse:id="{$id}" fuse:lazy fuse:name="{$encodedName}" fuse:params="{$encodedParams}">
    {$placeholder}
</div>
HTML;
    }

    $component->hydrate($params);
    $component->mount();

    return $component->output();
}
