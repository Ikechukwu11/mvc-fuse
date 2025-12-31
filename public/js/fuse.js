document.addEventListener("DOMContentLoaded", () => {
  Fuse.init();
});

/**
 * Fuse JS Client
 *
 * Handles client-side component interactions, AJAX requests, and DOM updates.
 */
const Fuse = {
  /**
   * @var {Object} components Registry of active components
   */
  components: {},

  /**
   * Parse an action string like: "save('a', 1)"
   *
   * @param {string} rawAction
   * @returns {{action: string, params: Array}}
   */
  parseAction(rawAction) {
    let action = rawAction;
    let params = [];
    if (rawAction && rawAction.includes("(")) {
      const parts = rawAction.split("(");
      action = parts[0];
      const args = parts[1].replace(")", "");
      params = args
        .split(",")
        .map((arg) => {
          arg = arg.trim();
          if (arg === "") return null;
          if (!isNaN(arg)) return Number(arg);
          if (
            (arg.startsWith("'") && arg.endsWith("'")) ||
            (arg.startsWith('"') && arg.endsWith('"'))
          ) {
            return arg.slice(1, -1);
          }
          return arg;
        })
        .filter((v) => v !== null);
    }
    return { action, params };
  },

  /**
   * Check key and modifier matches for keyboard events.
   *
   * @param {KeyboardEvent} e
   * @param {Array<string>} mods
   * @returns {boolean}
   */
  matchKeyEvent(e, mods) {
    const keyAliases = {
      enter: "Enter",
      escape: "Escape",
      space: " ",
      tab: "Tab",
      up: "ArrowUp",
      down: "ArrowDown",
      left: "ArrowLeft",
      right: "ArrowRight",
      "caps-lock": "CapsLock",
      equal: "=",
      period: ".",
      slash: "/",
    };

    const requireShift = mods.includes("shift");
    const requireCtrl = mods.includes("ctrl");
    const requireAlt = mods.includes("alt");
    const requireMeta = mods.includes("meta") || mods.includes("cmd");

    if (requireShift && !e.shiftKey) return false;
    if (requireCtrl && !e.ctrlKey) return false;
    if (requireAlt && !e.altKey) return false;
    if (requireMeta && !e.metaKey) return false;

    const keyMod = mods.find((m) => Object.keys(keyAliases).includes(m));
    if (keyMod) {
      const expected = keyAliases[keyMod];
      return e.key === expected;
    }

    return true;
  },

  /**
   * Initialize Fuse
   *
   * Sets up loading bars, scans for components, and initializes navigation.
   */
  init() {
    this.createLoadingBar();
    this.prefetchCache = {};
    document.querySelectorAll("[fuse\\:id]").forEach((el) => {
      this.initComponent(el);
    });
    this.initLazy();
    this.initNavigation();
    window.addEventListener("popstate", (e) => this.handlePopState(e));
  },

  /**
   * Initialize lazy loading observer
   */
  initLazy() {
    const observer = new IntersectionObserver((entries, observer) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const el = entry.target;
          observer.unobserve(el);
          this.loadLazyComponent(el);
        }
      });
    });

    document.querySelectorAll("[fuse\\:lazy]").forEach((el) => {
      if (el.getAttribute("fuse:lazy") === "on-load") {
        this.loadLazyComponent(el);
      } else {
        observer.observe(el);
      }
    });
  },

  /**
   * Load a lazy component
   *
   * @param {HTMLElement} el
   */
  async loadLazyComponent(el) {
    const id = el.getAttribute("fuse:id");
    const name = el.getAttribute("fuse:name");
    const paramsJson = el.getAttribute("fuse:params");
    let params = [];
    try {
      params = JSON.parse(paramsJson);
    } catch (e) {
      console.error("Failed to parse params for lazy component", name, e);
    }

    const payload = {
      id: id,
      name: name,
      params: params,
      lazyLoad: true,
    };

    try {
      const basePath =
        document
          .querySelector('meta[name="base-path"]')
          ?.getAttribute("content") || "";
      const url = `${basePath}/fuse/update`;

      const response = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": this.getCsrfToken(),
        },
        body: JSON.stringify({ payload: payload }),
      });

      const res = await response.json();

      if (res.html) {
        const temp = document.createElement("div");
        temp.innerHTML = res.html;
        const newEl = temp.firstElementChild;

        el.replaceWith(newEl);

        this.initComponent(newEl);

        if (res.data && this.components[id]) {
          this.components[id].data = res.data;
        }
      }
    } catch (e) {
      console.error("Lazy load failed", e);
    }
  },

  /**
   * Create loading indicator elements in the DOM.
   */
  createLoadingBar() {
    if (!document.getElementById("fuse-loading-bar")) {
      const bar = document.createElement("div");
      bar.id = "fuse-loading-bar";
      document.body.appendChild(bar);
    }

    if (
      window.FuseConfig &&
      window.FuseConfig.loading &&
      window.FuseConfig.loading.spinner &&
      !document.getElementById("fuse-loading-spinner")
    ) {
      const spinner = document.createElement("div");
      spinner.id = "fuse-loading-spinner";
      document.body.appendChild(spinner);
    }
  },

  /**
   * Show global loading indicators.
   */
  showLoading() {
    const bar = document.getElementById("fuse-loading-bar");
    const spinner = document.getElementById("fuse-loading-spinner");
    if (bar) {
      bar.style.width = "30%";
      bar.style.opacity = "1";
    }
    if (spinner) {
      spinner.style.opacity = "1";
    }
  },

  /**
   * Hide and reset global loading indicators.
   */
  finishLoading() {
    const bar = document.getElementById("fuse-loading-bar");
    const spinner = document.getElementById("fuse-loading-spinner");
    if (bar) {
      bar.style.width = "100%";
      setTimeout(() => {
        bar.style.opacity = "0";
        setTimeout(() => {
          bar.style.width = "0";
        }, 200);
      }, 300);
    }
    if (spinner) {
      setTimeout(() => {
        spinner.style.opacity = "0";
      }, 300);
    }
  },

  /**
   * Initialize server-side navigation handling (SPA-like links).
   */
  initNavigation() {
    document.body.addEventListener("click", (e) => {
      const link =
        e.target.closest("a[fuse\\:navigate]") ||
        e.target.closest("a[fuse\\:navigate\\.hover]");
      if (link) {
        e.preventDefault();
        const url = link.href;
        this.navigate(url);
      }
    });

    // Hover prefetch support: a[fuse:navigate.hover]
    document.body.addEventListener(
      "mouseenter",
      (e) => {
        const link = e.target.closest("a[fuse\\:navigate\\.hover]");
        if (link) {
          const url = link.href;
          // Debounce: wait 60ms before prefetching
          clearTimeout(link.__fuseHoverTimer);
          link.__fuseHoverTimer = setTimeout(() => {
            this.prefetch(url);
          }, 60);
        }
      },
      true
    );
  },

  /**
   * Navigate to a URL via AJAX and update the page content.
   *
   * @param {string} url
   * @param {boolean} pushState Whether to update browser history
   */
  async navigate(url, pushState = true) {
    const prevScroll = { x: window.scrollX, y: window.scrollY };
    window.dispatchEvent(
      new CustomEvent("fuse:navigating", { detail: { url } })
    );
    this.showLoading();
    try {
      const persisted = {};
      document.querySelectorAll("[fuse\\:persist]").forEach((el) => {
        const name = el.getAttribute("fuse:persist");
        if (name) persisted[name] = el;
      });
      // Use prefetch cache if available, otherwise fetch
      let html = this.prefetchCache[url];
      if (!html) {
        const response = await fetch(url, {
          headers: {
            "X-FUSE-NAVIGATE": "true",
          },
        });

        if (response.redirected) {
          window.location.href = response.url;
          return;
        }

        if (!response.ok) throw new Error("Navigation failed");

        html = await response.text();
      }

      // Parse HTML
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");

      // Replace Title
      document.title = doc.title;

      // Replace Body Content
      document.body.innerHTML = doc.body.innerHTML;

      // Restore persisted elements by name
      Object.keys(persisted).forEach((name) => {
        const target = document.querySelector(`[fuse\\:persist="${name}"]`);
        if (target && target.parentNode) {
          const el = persisted[name];
          target.parentNode.replaceChild(el, target);
        }
      });

      // Re-initialize Fuse
      this.init();

      // Push State
      if (pushState) {
        window.history.pushState({}, "", url);
      }

      // Restore previous scroll position
      window.scrollTo(prevScroll.x, prevScroll.y);
      window.dispatchEvent(
        new CustomEvent("fuse:navigated", { detail: { url } })
      );
    } catch (error) {
      console.error("Navigation error:", error);
      // Fallback to normal navigation
      window.location.href = url;
    } finally {
      this.finishLoading();
    }
  },

  /**
   * Handle browser back/forward buttons.
   */
  handlePopState(e) {
    this.navigate(window.location.href, false);
  },

  /**
   * Prefetch a URL and cache the HTML.
   *
   * @param {string} url
   */
  async prefetch(url) {
    try {
      if (this.prefetchCache[url]) return;
      window.dispatchEvent(
        new CustomEvent("fuse:prefetching", { detail: { url } })
      );
      const response = await fetch(url, {
        headers: { "X-FUSE-NAVIGATE": "true" },
      });
      if (!response.ok) return;
      const html = await response.text();
      this.prefetchCache[url] = html;
      window.dispatchEvent(
        new CustomEvent("fuse:prefetched", { detail: { url } })
      );
    } catch (e) {
      // Ignore prefetch errors
    }
  },

  /**
   * Initialize a specific component element.
   *
   * @param {HTMLElement} el
   */
  initComponent(el) {
    const id = el.getAttribute("fuse:id");
    const rawData = el.getAttribute("fuse:data");
    const componentData = JSON.parse(rawData);

    this.components[id] = {
      el: el,
      name: componentData.name,
      data: componentData.data,
    };

    this.attachListeners(el, id);
  },

  /**
   * Attach event listeners for `fuse:click` and `fuse:model`.
   *
   * @param {HTMLElement} el
   * @param {string} componentId
   */
  attachListeners(el, componentId) {
    // fuse:click
    el.querySelectorAll("[fuse\\:click]").forEach((node) => {
      node.addEventListener("click", (e) => {
        e.preventDefault();
        const confirmMsg = node.getAttribute("fuse:confirm");
        if (confirmMsg && !window.confirm(confirmMsg)) {
          return;
        }
        const rawAction = node.getAttribute("fuse:click");
        const { action, params } = this.parseAction(rawAction);

        this.sendRequest(componentId, action, params, node);
      });
    });

    // fuse:model
    el.querySelectorAll("[fuse\\:model]").forEach((node) => {
      node.addEventListener("input", (e) => {
        const property = node.getAttribute("fuse:model");
        // Support dot notation for nested properties (e.g. form.title)
        if (property.includes(".")) {
          const parts = property.split(".");
          let obj = this.components[componentId].data;
          for (let i = 0; i < parts.length - 1; i++) {
            if (!obj[parts[i]]) obj[parts[i]] = {};
            obj = obj[parts[i]];
          }
          obj[parts[parts.length - 1]] = e.target.value;
        } else {
          this.components[componentId].data[property] = e.target.value;
        }
      });

      const prop = node.getAttribute("fuse:model");
      // Handle dot notation for value binding
      let value = this.components[componentId].data;
      if (prop.includes(".")) {
        const parts = prop.split(".");
        for (let part of parts) {
          if (value === undefined) break;
          value = value[part];
        }
      } else {
        value = value[prop];
      }

      if (value !== undefined) {
        node.value = value;
      }
    });

    // Dynamic fuse:* event listeners (excluding model/click/submit)
    const allNodes = [el, ...el.querySelectorAll("*")];
    allNodes.forEach((node) => {
      Array.from(node.attributes || []).forEach((attr) => {
        if (!attr || !attr.name || !attr.name.startsWith("fuse:")) return;
        const spec = attr.name.slice(5); // after "fuse:"
        if (spec === "model" || spec === "click" || spec === "submit") return;

        const rawAction = attr.value;
        const { action, params } = this.parseAction(rawAction);

        const parts = spec.split(".");
        const eventName = parts[0];
        const mods = parts.slice(1);

        const options = { once: mods.includes("once") };
        node.addEventListener(
          eventName,
          (e) => {
            if (mods.includes("self") && e.target !== node) return;
            if (mods.includes("prevent")) e.preventDefault();
            if (mods.includes("stop")) e.stopPropagation();

            if (eventName.startsWith("key")) {
              if (!this.matchKeyEvent(e, mods)) return;
            }

            const confirmMsg = node.getAttribute("fuse:confirm");
            if (confirmMsg && !window.confirm(confirmMsg)) {
              return;
            }

            // Debounce support: fuse:event.debounce.300
            let delay = 0;
            const debounceIndex = mods.indexOf("debounce");
            if (debounceIndex !== -1) {
              const next = mods[debounceIndex + 1];
              const ms = next && /^\d+$/.test(next) ? parseInt(next, 10) : 300;
              delay = ms;
            }

            if (delay > 0) {
              clearTimeout(node.__fuseDebounceTimer);
              node.__fuseDebounceTimer = setTimeout(() => {
                this.sendRequest(componentId, action, params, node);
              }, delay);
            } else {
              this.sendRequest(componentId, action, params, node);
            }
          },
          options
        );
      });
    });

    // fuse:submit
    el.querySelectorAll("form[fuse\\:submit]").forEach((form) => {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        const confirmMsg = form.getAttribute("fuse:confirm");
        if (confirmMsg && !window.confirm(confirmMsg)) {
          return;
        }
        const rawAction = form.getAttribute("fuse:submit");
        this.sendRequest(componentId, rawAction, [], form);
      });
    });
  },

  /**
   * Send an AJAX request to the server to update the component.
   *
   * @param {string} componentId
   * @param {string} action Method name to call
   * @param {Array} params Method arguments
   * @param {HTMLElement|null} triggerEl Element that triggered the request
   */
  async sendRequest(componentId, action, params = [], triggerEl = null) {
    let loadingTarget = null;
    let globalLoading = true;

    if (triggerEl) {
      const targetSelector = triggerEl.getAttribute("fuse:loading-target");
      if (targetSelector) {
        // Try to find within component first, then globally
        loadingTarget =
          this.components[componentId].el.querySelector(targetSelector) ||
          document.querySelector(targetSelector);

        if (loadingTarget) {
          globalLoading = false;
        }
      }
    }

    if (globalLoading) {
      this.showLoading();
    }

    if (loadingTarget) {
      // Store original display if needed, but for now assume toggling "none"
      if (loadingTarget.style.display === "none") {
        loadingTarget.style.display = "block";
      } else {
        loadingTarget.style.visibility = "visible";
      }
    }

    const component = this.components[componentId];

    const payload = {
      id: componentId,
      name: component.name,
      data: component.data,
      action: action,
      params: params,
    };

    try {
      const basePath =
        document
          .querySelector('meta[name="base-path"]')
          ?.getAttribute("content") || "";
      const url = `${basePath}/fuse/update`;

      const response = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": this.getCsrfToken(),
        },
        body: JSON.stringify({ payload: payload }),
      });

      const text = await response.text();
      let res;
      try {
        res = text ? JSON.parse(text) : {};
      } catch (e) {
        console.error("Fuse Parse Error:", e, "Response:", text);
        return;
      }

      // Handle Redirect
      if (res.redirect) {
        if (res.navigate) {
          this.navigate(res.redirect);
        } else {
          window.location.href = res.redirect;
        }
        return;
      }

      if (res.html) {
        this.updateDom(component.el, res.html);
        if (res.data) {
          component.data = res.data;
        }
        if (res.events) {
          res.events.forEach((event) => {
            window.dispatchEvent(
              new CustomEvent(event.name, { detail: event.detail })
            );
          });
        }
      }
    } catch (error) {
      console.error("Fuse Error:", error);
    } finally {
      if (globalLoading) {
        this.finishLoading();
      }
      if (loadingTarget) {
        if (loadingTarget.style.display === "block") {
          loadingTarget.style.display = "none";
        } else {
          loadingTarget.style.visibility = "hidden";
        }
      }
    }
  },

  /**
   * Replace the DOM element with new HTML.
   *
   * @param {HTMLElement} oldEl
   * @param {string} newHtml
   */
  updateDom(oldEl, newHtml) {
    const temp = document.createElement("div");
    temp.innerHTML = newHtml;
    const newEl = temp.firstElementChild;

    oldEl.replaceWith(newEl);

    const id = newEl.getAttribute("fuse:id");
    this.components[id].el = newEl;
    this.attachListeners(newEl, id);
  },

  /**
   * Get the CSRF token from the meta tag.
   *
   * @return {string}
   */
  getCsrfToken() {
    return (
      document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") || ""
    );
  },
};
