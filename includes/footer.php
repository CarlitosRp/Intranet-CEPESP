    <script src="/intranet-CEPESP/assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cerrar automáticamente todos los alerts de Bootstrap después de 4 segundos
        document.addEventListener("DOMContentLoaded", () => {
            const alerts = document.querySelectorAll(".alert-dismissible.auto-hide");
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.remove("show");
                    alert.classList.add("fade");
                    setTimeout(() => alert.remove(), 500); // esperar animación
                }, 4000); // 4 segundos
            });
        });
    </script>
    </body>

    </html>