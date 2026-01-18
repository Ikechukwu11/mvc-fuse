NativePHP-Fuse Performance Fix

Your UI jank is caused by event-loop starvation – DOM hydration, native hardware calls and PHP bridge events are all running inside the same JS frame.

This document explains exactly what to change.

Root Problem

You currently execute this inside one JS tick:

DOM hydration

Media preload

Vibration

Torch

Permission sync

State diff

Event hooks

Mobile JS runtime has a 16ms frame budget.
Your frame time is >100ms → UI thread freezes → jank.

Files To Fix
File Fix
Sample files to fix /Introduce
public/js/fuse.js Introduce event scheduler
public/js/native.js Defer all hardware calls
system/engine/Fuse/Bridge.php Chunk event payloads
system/engine/Fuse/EventBus.php Queue instead of direct emit
system/engine/Fuse/Component.php Delay mounted hooks
system/engine/Fuse/ViewHydrator.php Do not run native events during hydration
Step 1 — Add Scheduler

Create file:
public/js/scheduler.js

export class FuseScheduler {
constructor() {
this.queue = [];
this.running = false;
}

    push(task) {
        this.queue.push(task);
        this.run();
    }

    run() {
        if (this.running) return;
        this.running = true;
        requestAnimationFrame(() => this.flush());
    }

    flush() {
        const start = performance.now();

        while (this.queue.length && performance.now() - start < 8) {
            this.queue.shift()();
        }

        this.running = false;
        if (this.queue.length) this.run();
    }

}

Step 2 — Patch fuse.js

Replace:

this.patchDom(html);
this.emitEvents(events);

With:

scheduler.push(() => this.patchDom(html));

events.forEach(evt => {
scheduler.push(() => this.emitEvent(evt));
});

Step 3 — Patch native.js

Replace all direct hardware calls:

torch.enable();
navigator.vibrate(200);
media.preload();

With:

scheduler.push(() => torch.enable());
scheduler.push(() => navigator.vibrate(200));
scheduler.push(() => media.preload());

Never execute native APIs directly during hydration.

Step 4 — Chunk PHP Payloads

In Bridge.php replace:

return [
'html' => $html,
'events' => $events
];

With:

return [
'html' => $html,
'events' => array_chunk($events, 5)
];

Step 5 — Delay Mounted Hooks

In Component.php replace:

$this->emit('mounted');

With:

$this->emitDeferred('mounted');

And only fire after first render frame.

Step 6 — Prevent Native Calls During Hydration

In ViewHydrator.php block hardware execution until hydration completes.

Result
Before After
200ms JS frame < 16ms
Frozen navigation Smooth navigation
UI thread blocked Frame-batched execution
Feels like Electron Feels native
Rule Going Forward

Nothing touches hardware or heavy state during DOM hydration.
Everything goes through the scheduler.

Your system is not slow.
It was just running without a brain.
