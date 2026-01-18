<?php

namespace Native\Mobile\Facades;

use Native\Mobile\Native;

class Media
{
    public static function play(string $url, string $title = 'Unknown Title', string $artist = 'Unknown Artist', ?string $artwork = null)
    {
        Native::call('Media.Play', [
            'url' => $url,
            'title' => $title,
            'artist' => $artist,
            'artwork' => $artwork
        ]);
    }

    public static function pause()
    {
        Native::call('Media.Pause');
    }

    public static function resume()
    {
        Native::call('Media.Resume');
    }

    public static function pickTrack()
    {
        Native::call('Media.PickTrack');
    }

    public static function next()
    {
        Native::call('Media.Next');
    }

    public static function previous()
    {
        Native::call('Media.Previous');
    }

    public static function seekTo(int $positionMs)
    {
        Native::call('Media.SeekTo', ['position' => $positionMs]);
    }

    public static function toggleShuffle(bool $enabled = true)
    {
        Native::call('Media.ToggleShuffle', ['enabled' => $enabled]);
    }

    public static function toggleRepeat(string $mode = 'all')
    {
        Native::call('Media.ToggleRepeat', ['mode' => $mode]);
    }

    public static function state()
    {
        Native::call('Media.State');
    }

    public static function startStatusUpdates()
    {
        Native::call('Media.StartStatusUpdates');
    }

    public static function stopStatusUpdates()
    {
        Native::call('Media.StopStatusUpdates');
    }
}
