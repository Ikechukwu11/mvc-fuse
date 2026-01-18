class FuseScheduler {
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

// Make it globally available since we aren't using modules
window.FuseScheduler = FuseScheduler;
