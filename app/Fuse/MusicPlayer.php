<?php

namespace App\Fuse;

use App\Fuse\BaseFuse;
use Engine\Fuse\Component;
use Engine\Storage\Storage;
use Native\Mobile\Facades\Media;
use Native\Mobile\Facades\Secure;
use Native\Mobile\Facades\MediaLibrary;

class MusicPlayer extends BaseFuse
{
    public string $url = 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3';
    public string $title = 'Demo Song';
    public string $artist = 'SoundHelix';
    public string $artwork = '';
    public string $defaultArtwork = '';
    public bool $isPlaying = false;
    public array $library = [];
    public bool $shuffle = false;
    public string $repeat = 'all';
    public int $positionMs = 0;
    public int $durationMs = 0;
    public bool $isScanning = false;

    public function play()
    {
        Media::play($this->url, $this->title, $this->artist, $this->artwork);
        $this->isPlaying = true;
        Media::startStatusUpdates();
    }

    public function pause()
    {
        Media::pause();
        $this->isPlaying = false;
        Media::stopStatusUpdates();
    }

    public function resume()
    {
        Media::resume();
        $this->isPlaying = true;
        Media::startStatusUpdates();
    }

    public function pick()
    {
        Media::pickTrack();
    }

    public int $queueIndex = -1;

    public function mount(): void
    {
        Secure::get('music.shuffle');
        Secure::get('music.repeat');
        $this->defaultArtwork = $this->defaultArtworkUrl();
        if ($this->artwork === '' || $this->artwork === 'https://via.placeholder.com/300') {
            $this->artwork = $this->defaultArtwork;
        }
        // Try to load from disk first for speed
        if (!$this->loadLibraryDirectly()) {
            $this->loadPersistedLibrary();
        }
        // Do not auto-start polling on mount to avoid UI interference
    }

    /**
     * Load the persisted music library directly from disk if available.
     *
     * @return bool True when the library was loaded successfully.
     */
    public function loadLibraryDirectly(): bool
    {
        $root = dirname(__DIR__, 2);
        // Path on Android (relative to app root)
        $androidPath = dirname($root) . '/persisted_data/storage/app/music/library.json';
        // Path in local dev (standard storage)
        $localPath = storage_path('app/music/library.json');

        $json = null;

        if (file_exists($androidPath)) {
            $json = file_get_contents($androidPath);
        } elseif (file_exists($localPath)) {
            $json = file_get_contents($localPath);
        }

        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data) && !empty($data)) {
                $this->library = $data;
                $this->isScanning = false;
                return true;
            }
        }
        return false;
    }

    /**
     * Get the default album art URL.
     *
     * @return string
     */
    private function defaultArtworkUrl(): string
    {
        return $this->appUrl('/music/albumart/default');
    }

    /**
     * Build an absolute URL for a path within the application.
     *
     * @param string $path
     * @return string
     */
    private function appUrl(string $path): string
    {
        $url = rtrim(config('app.url'), '/');
        $base = rtrim(config('app.base_path', ''), '/');
        return $url . $base . '/' . ltrim($path, '/');
    }

    /**
     * Resolve the best artwork source for a given track record.
     *
     * @param array $track
     * @return string
     */
    private function trackArtwork(array $track): string
    {
        $mobile = getenv('MVC_MOBILE_RUNNING');
        $isMobile = ($mobile && strtolower($mobile) === 'true') || (defined('MVC_NATIVE_MODE') && MVC_NATIVE_MODE);

        $artUri = $track['artwork_uri'] ?? '';
        if ($isMobile && is_string($artUri) && $artUri !== '') {
            return $artUri;
        }

        $id = $track['id'] ?? null;
        if ($id !== null && preg_match('/^\d+$/', (string)$id)) {
            return $this->appUrl('/music/albumart/' . (string)$id);
        }

        return $this->defaultArtworkUrl();
    }

    public function dehydrated()
    {
        Media::stopStatusUpdates();
    }
    public function onTrackPicked($event = [])
    {
        if (empty($event)) return;

        $this->playTrack(
            $event['url'] ?? $this->url,
            $event['title'] ?? $this->title,
            $event['artist'] ?? $this->artist
        );
    }

    public function onSecureValue($event = [])
    {
        $key = $event['key'] ?? null;
        $value = $event['value'] ?? null;
        if ($key === 'music.shuffle') {
            $this->shuffle = $value === 'true';
        } elseif ($key === 'music.repeat') {
            $this->repeat = $value ?: 'all';
        }
    }

    public function loadLibrary()
    {
        $json = Storage::get('app/music/library.json');
        if ($json) {
            $this->library = json_decode($json, true) ?? [];
        }
    }

    public function loadPersistedLibrary()
    {
        \Native\Mobile\Native::call('App.ReadStorageFile', ['path' => 'music/library.json']);
    }

    public function onStorageFileRead($event = [])
    {
        $path = $event['path'] ?? '';
        $content = $event['content'] ?? '';
        if ($path === 'music/library.json' && !empty($content)) {
            $data = json_decode($content, true);
            if (is_array($data)) {
                $this->library = $data;
            }
        }
        // Always turn off scanning flag when we get the file read result
        // (even if empty or failed, so UI doesn't hang)
        if ($path === 'music/library.json') {
            $this->isScanning = false;
        }
    }

    public function playTrack(string $uri, string $title, string $artist)
    {
        $this->url = $uri;
        $this->title = $title;
        $this->artist = $artist;
        $this->artwork = $this->defaultArtworkUrl();

        $this->queueIndex = -1;
        $matched = null;
        foreach ($this->library as $idx => $track) {
            if (($track['uri'] ?? '') === $uri) {
                $this->queueIndex = $idx;
                $matched = $track;
                break;
            }
        }
        if (is_array($matched) && $matched !== []) {
            $this->artwork = $this->trackArtwork($matched);
        }

        $this->play();
    }

    public function render()
    {
        return view('fuse/music_player', $this->getPublicProperties());
    }

    public function scan()
    {
        $this->isScanning = true;
        MediaLibrary::scan();
    }

    public function onLibraryScanned($event = [])
    {
        // Scan is complete, data is on disk.
        // Try direct load first for speed
        if (!$this->loadLibraryDirectly()) {
            $this->loadPersistedLibrary();
        }
    }

    public function next()
    {
        if (empty($this->library)) return;

        if ($this->shuffle) {
            $this->queueIndex = rand(0, count($this->library) - 1);
        } else {
            $this->queueIndex++;
            if ($this->queueIndex >= count($this->library)) {
                $this->queueIndex = 0;
            }
        }

        $track = $this->library[$this->queueIndex];
        $this->playTrack(
            $track['uri'] ?? '',
            $track['title'] ?? 'Unknown',
            $track['artist'] ?? 'Unknown'
        );
    }

    public function previous()
    {
        if (empty($this->library)) return;

        $this->queueIndex--;
        if ($this->queueIndex < 0) {
            $this->queueIndex = count($this->library) - 1;
        }

        $track = $this->library[$this->queueIndex];
        $this->playTrack(
            $track['uri'] ?? '',
            $track['title'] ?? 'Unknown',
            $track['artist'] ?? 'Unknown'
        );
    }

    public function toggleShuffle()
    {
        $this->shuffle = !$this->shuffle;
        Media::toggleShuffle($this->shuffle);
        Secure::set('music.shuffle', $this->shuffle ? 'true' : 'false');
        Media::startStatusUpdates();
    }

    public function toggleRepeat()
    {
        $cycle = ['all', 'one', 'off'];
        $idx = array_search($this->repeat, $cycle, true);
        $this->repeat = $cycle[($idx === false ? 0 : ($idx + 1) % count($cycle))];
        Media::toggleRepeat($this->repeat);
        Secure::set('music.repeat', $this->repeat);
        Media::startStatusUpdates();
    }

    public function refreshState()
    {
        Media::state();
    }

    public function onMediaState($event = [])
    {
        $incomingUri = $event['uri'] ?? null;
        $incomingArtwork = $event['artwork'] ?? null;
        if (is_string($incomingArtwork) && str_contains($incomingArtwork, 'via.placeholder.com')) {
            $incomingArtwork = null;
        }

        if ($incomingUri !== null && $incomingUri !== $this->url && empty($incomingArtwork)) {
            $matched = null;
            foreach ($this->library as $track) {
                if (($track['uri'] ?? null) === $incomingUri) {
                    $matched = $track;
                    break;
                }
            }
            $this->artwork = is_array($matched) ? $this->trackArtwork($matched) : $this->defaultArtworkUrl();
        }

        $this->positionMs = (int)($event['positionMs'] ?? 0);
        $this->durationMs = (int)($event['durationMs'] ?? 0);
        $this->isPlaying = (bool)($event['isPlaying'] ?? false);
        // Optionally update title/artist if provided
        if (isset($event['title'])) $this->title = $event['title'];
        if (isset($event['artist'])) $this->artist = $event['artist'];
        if (isset($event['album'])) $this->album = $event['album'];
        if (isset($event['uri'])) $this->url = $event['uri'];
        if (!empty($event['artwork']) && is_string($event['artwork']) && !str_contains($event['artwork'], 'via.placeholder.com')) {
            $this->artwork = $event['artwork'];
        } elseif (isset($event['uri']) && is_string($event['uri']) && $event['uri'] !== '') {
            $matched = null;
            foreach ($this->library as $track) {
                if (($track['uri'] ?? null) === $event['uri']) {
                    $matched = $track;
                    break;
                }
            }
            $this->artwork = is_array($matched) ? $this->trackArtwork($matched) : $this->defaultArtworkUrl();
        }
    }

    public function setSeek($value)
    {
        $pos = (int)$value;
        Media::seekTo($pos);
        $this->positionMs = $pos;
        Media::startStatusUpdates();
    }

    private function formatMs(int $ms): string
    {
        if ($ms <= 0) return '0:00';
        $sec = (int)round($ms / 1000);
        $m = (int)floor($sec / 60);
        $s = $sec % 60;
        return sprintf('%d:%02d', $m, $s);
    }
}
