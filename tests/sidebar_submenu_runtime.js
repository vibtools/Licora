'use strict';
const fs = require('fs');
const vm = require('vm');

function assert(condition, message) {
  if (!condition) {
    console.error('FAIL:', message);
    process.exit(1);
  }
}
function eventTarget() {
  const handlers = {};
  return {
    handlers,
    addEventListener(type, fn) { handlers[type] = fn; },
    dispatch(type, event = {}) { if (handlers[type]) handlers[type](event); }
  };
}
const bodyClasses = new Set();
const body = {
  classList: {
    toggle(name, force) { if (force) bodyClasses.add(name); else bodyClasses.delete(name); },
    contains(name) { return bodyClasses.has(name); }
  }
};
const mobileToggle = Object.assign(eventTarget(), {
  attrs: { 'aria-expanded': 'false' },
  setAttribute(name, value) { this.attrs[name] = String(value); },
  getAttribute(name) { return this.attrs[name] || null; }
});
const backdrop = eventTarget();
const submenu = { hidden: true };
const submenuToggle = Object.assign(eventTarget(), {
  attrs: { 'aria-expanded': 'false', 'aria-controls': 'licoraSubmenuSettings' },
  setAttribute(name, value) { this.attrs[name] = String(value); },
  getAttribute(name) { return this.attrs[name] || null; }
});
const sidebar = {
  querySelectorAll(selector) {
    if (selector === '[data-ui-submenu-toggle]') return [submenuToggle];
    if (selector === 'a') return [];
    return [];
  }
};
const documentTarget = eventTarget();
const document = Object.assign(documentTarget, {
  readyState: 'complete',
  body,
  getElementById(id) {
    return {
      licoraSidebarToggle: mobileToggle,
      licoraSidebar: sidebar,
      licoraSidebarBackdrop: backdrop,
      licoraSubmenuSettings: submenu
    }[id] || null;
  }
});
const windowTarget = eventTarget();
const window = Object.assign(windowTarget, { innerWidth: 1200 });
const context = { document, window, console };
vm.createContext(context);
vm.runInContext(fs.readFileSync('admin/assets/js/components/sidebar.js', 'utf8'), context, { filename: 'sidebar.js' });

assert(submenu.hidden === true, 'submenu should initialize collapsed when aria-expanded is false');
submenuToggle.dispatch('click');
assert(submenuToggle.getAttribute('aria-expanded') === 'true', 'submenu toggle must set aria-expanded=true');
assert(submenu.hidden === false, 'submenu must become visible after expand');
submenuToggle.dispatch('click');
assert(submenuToggle.getAttribute('aria-expanded') === 'false', 'submenu toggle must set aria-expanded=false');
assert(submenu.hidden === true, 'submenu must become hidden after collapse');

console.log('Sidebar submenu runtime checks passed.');
