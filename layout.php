
<script>
document.addEventListener("DOMContentLoaded", function () {
  const popovers = [].slice.call(document.querySelectorAll("[data-bs-toggle='popover']"));
  popovers.map(function (el) {
    return new bootstrap.Popover(el);
  });
});
</script>
