<script src="<?= htmlspecialchars($BASE . '/assets/js/bootstrap.bundle.min.js') ?>"></script>
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const alerts = document.querySelectorAll(".alert-dismissible.auto-hide");
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.classList.remove("show");
                alert.classList.add("fade");
                setTimeout(() => alert.remove(), 500);
            }, 4000);
        });
    });
</script>

</body>

</html>