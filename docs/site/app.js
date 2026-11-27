const currentPage = document.body.dataset.page || "";
document.querySelectorAll(".site-nav a").forEach((link) => {
  if (link.dataset.page === currentPage) {
    link.classList.add("is-active");
  }
});
