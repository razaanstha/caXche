import quicklink from "quicklink/dist/quicklink.umd.js";

// Initialize QuickLink to prefetch pages when in viewport
window.addEventListener("load", () => {
  quicklink.listen();
});
