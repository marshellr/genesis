import mermaid from "https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs";

mermaid.initialize({
  startOnLoad: true,
  theme: "base",
  themeVariables: {
    primaryColor: "#d4efe9",
    primaryTextColor: "#18211f",
    primaryBorderColor: "#0f766e",
    lineColor: "#18211f",
    secondaryColor: "#ebe4d8",
    tertiaryColor: "#ffffff",
    fontFamily: "IBM Plex Mono, monospace"
  }
});

const currentPage = document.body.dataset.page || "";
document.querySelectorAll(".site-nav a").forEach((link) => {
  if (link.dataset.page === currentPage) {
    link.classList.add("is-active");
  }
});
